<?php
/**
 * V13.1 Contact Acquisition Engine
 * Upload: /public_html/lead-engine/build-contact-acquisition.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb131($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows131($table,$query){ $r=sb131('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function phone131($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }
  function val131($town){
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)) return 1400000;
    if(in_array($town,['Wilton','Fairfield'],true)) return 950000;
    if(in_array($town,['Stamford','Norwalk'],true)) return 750000;
    return 700000;
  }
  function motivation131($c){
    $text=strtolower(($c['motivation']??'').' '.($c['notes']??'').' '.($c['property_type']??''));
    $years=(float)($c['years_owned']??0); $equity=(float)($c['estimated_equity']??0);
    if(str_contains($text,'downsize') || $years>=18) return ['Likely Downsizer / Long-Term Owner',88];
    if(str_contains($text,'relocat')) return ['Relocation / Move Planning',84];
    if(str_contains($text,'estate')) return ['Estate / Family Transition',82];
    if($equity>=750000) return ['High Equity Seller Window',78];
    if($years>=10) return ['Long Ownership / Equity Curiosity',74];
    return ['Market Timing / Home Value Curiosity',65];
  }
  function candidate131($o){
    $base=[
      'batch_id'=>null,'source_table'=>'','source_id'=>'','source_name'=>'','source_type'=>'owner_record',
      'owner_name'=>'','phone'=>'','email'=>'','property_address'=>'','mailing_address'=>'','town'=>'','state'=>'CT','market'=>'',
      'property_type'=>'','years_owned'=>0,'last_sale_price'=>0,'estimated_value'=>0,'estimated_equity'=>0,'owner_occupied'=>false,
      'dnc_status'=>'unchecked','dnc_checked'=>false,'dnc_match'=>false,'realtor_checked'=>false,'realtor_match'=>false,'realtor_name'=>'',
      'consent_status'=>'unknown','approval_status'=>'review','approved_contact'=>false,'call_eligible'=>false,'sms_eligible'=>false,'email_eligible'=>false,
      'motivation'=>'','motivation_score'=>0,'contact_score'=>0,'priority_band'=>'C','recommended_action'=>'Review source/DNC/realtor status before outreach.','next_step'=>'review',
      'pushed_to_compliant_imports'=>false,'pushed_to_approved_pool'=>false,'raw_payload'=>[],'status'=>'review','created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }

  $today=date('Y-m-d');

  // Realtor exclusion lookup
  $realtors=rows131('realtor_exclusion_list','select=*&status=eq.active&limit=10000');
  $rPhones=[]; $rEmails=[]; $rNames=[];
  foreach($realtors as $r){
    $p=phone131($r['phone']??''); if($p)$rPhones[$p]=$r['name']??'Realtor';
    $e=strtolower(trim($r['email']??'')); if($e)$rEmails[$e]=$r['name']??'Realtor';
    $n=strtolower(trim($r['name']??'')); if($n)$rNames[$n]=$r['name']??'Realtor';
  }

  $existing=rows131('contact_acquisition_candidates','select=*&status=in.(review,approved)&order=created_at.desc&limit=2000');
  $existingKeys=[];
  foreach($existing as $e){
    $p=phone131($e['phone']??''); $em=strtolower(trim($e['email']??'')); $addr=strtolower(trim($e['property_address']??''));
    if($p)$existingKeys['p:'.$p]=true;
    if($em)$existingKeys['e:'.$em]=true;
    if($addr)$existingKeys['a:'.$addr]=true;
  }

  $sources=[];

  // 1. Real homeowner intelligence records, if populated.
  $homeowners=rows131('homeowner_intelligence','select=*&order=lead_score.desc,created_at.desc&limit=1000');
  foreach($homeowners as $h){
    $sources[]=[
      'source_table'=>'homeowner_intelligence','source_id'=>$h['id']??'','source_name'=>$h['source']??'homeowner_intelligence','source_type'=>'homeowner_record',
      'owner_name'=>$h['owner_name']??'','phone'=>$h['phone']??'','email'=>$h['email']??'','property_address'=>$h['address']??'',
      'town'=>$h['town']??'','state'=>$h['state']??'CT','property_type'=>$h['property_type']??'','years_owned'=>$h['years_owned']??0,
      'last_sale_price'=>$h['last_sale_price']??0,'estimated_value'=>$h['estimated_value']??0,'estimated_equity'=>$h['estimated_equity']??0,
      'dnc_status'=>$h['dnc_status']??'unknown','consent_status'=>'unknown','approval_status'=>'review','notes'=>$h['notes']??'','raw_payload'=>$h
    ];
  }

  // 2. Clean list items, if user imports them.
  $clean=rows131('jessica_clean_list_items','select=*&order=lead_score.desc,created_at.desc&limit=1000');
  foreach($clean as $i){
    $sources[]=[
      'source_table'=>'jessica_clean_list_items','source_id'=>$i['id']??'','source_name'=>'jessica_clean_list','source_type'=>'clean_list',
      'owner_name'=>$i['name']??'','phone'=>$i['phone']??'','email'=>$i['email']??'','property_address'=>$i['address']??'',
      'town'=>$i['town']??'','state'=>$i['state']??'CT','property_type'=>$i['property_type']??'','years_owned'=>$i['years_owned']??0,
      'last_sale_price'=>$i['last_sale_price']??0,'estimated_value'=>$i['estimated_value']??0,'estimated_equity'=>$i['estimated_equity']??0,
      'dnc_status'=>$i['dnc_status']??'unchecked','consent_status'=>$i['consent_status']??'unknown','approval_status'=>$i['approval_status']??'review','notes'=>$i['notes']??'','raw_payload'=>$i
    ];
  }

  // 3. Inbound website leads are approved source candidates.
  $leads=rows131('leads','select=*&order=created_at.desc&limit=1000');
  foreach($leads as $l){
    $sources[]=[
      'source_table'=>'leads','source_id'=>$l['id']??'','source_name'=>'website_lead','source_type'=>'inbound_opt_in',
      'owner_name'=>$l['name']??'','phone'=>$l['phone']??'','email'=>$l['email']??'','property_address'=>$l['address']??'',
      'town'=>$l['town']??'','state'=>'CT','property_type'=>'','years_owned'=>0,'last_sale_price'=>0,
      'estimated_value'=>is_numeric($l['estimated_value']??null)?$l['estimated_value']:0,'estimated_equity'=>0,
      'dnc_status'=>'clear','consent_status'=>'opt_in','approval_status'=>'approved','notes'=>$l['message']??($l['goal']??''),'raw_payload'=>$l
    ];
  }

  $candidates=[]; $seen=[];
  foreach($sources as $s){
    $phone=phone131($s['phone']??''); $email=strtolower(trim($s['email']??'')); $addr=strtolower(trim($s['property_address']??''));
    $key=$phone ? 'p:'.$phone : ($email ? 'e:'.$email : ($addr ? 'a:'.$addr : 'x:'.($s['source_table'].'-'.$s['source_id'])));
    if(isset($seen[$key]) || isset($existingKeys[$key])) continue;
    $seen[$key]=true;

    $town=$s['town'] ?: 'Unknown';
    $value=(float)($s['estimated_value']??0); if($value<=0)$value=val131($town);
    $equity=(float)($s['estimated_equity']??0); if($equity<=0)$equity=$value*.45;
    $s['estimated_value']=$value; $s['estimated_equity']=$equity;

    [$mot,$motScore]=motivation131($s);

    $dnc=$s['dnc_status']??'unchecked';
    $dncMatch=in_array($dnc,['blocked','listed','do_not_call'],true);
    $realtorMatch=false; $realtorName='';
    if($phone && isset($rPhones[$phone])){ $realtorMatch=true; $realtorName=$rPhones[$phone]; }
    if($email && isset($rEmails[$email])){ $realtorMatch=true; $realtorName=$rEmails[$email]; }
    $nameKey=strtolower(trim($s['owner_name']??'')); if($nameKey && isset($rNames[$nameKey])){ $realtorMatch=true; $realtorName=$rNames[$nameKey]; }

    $consent=$s['consent_status']??'unknown';
    $approval=$s['approval_status']??'review';

    $contactScore=0;
    if($phone)$contactScore+=28;
    if($email)$contactScore+=14;
    if($s['property_address']??'')$contactScore+=16;
    if($town && $town!=='Unknown')$contactScore+=10;
    if($motScore>=80)$contactScore+=12;
    if($equity>=500000)$contactScore+=10;
    if($consent==='opt_in')$contactScore+=20;
    if($dnc==='clear')$contactScore+=10;
    if($realtorMatch || $dncMatch)$contactScore=0;
    $contactScore=max(0,min(100,$contactScore));

    $approved=(!$realtorMatch && !$dncMatch && in_array($approval,['approved','imported'],true));
    $callEligible=($approved && $phone && $dnc==='clear' && in_array($consent,['opt_in','business_contact','prior_relationship'],true));
    $smsEligible=($callEligible && $consent==='opt_in');
    $emailEligible=($approved && $email && !$realtorMatch && !$dncMatch);

    $next='review';
    $action='Review source, DNC status, and realtor exclusion before outreach.';
    $status='review';
    if($realtorMatch){ $next='exclude_realtor'; $action='Exclude: matched realtor list.'; $status='rejected'; }
    elseif($dncMatch){ $next='do_not_call'; $action='Do not call: DNC matched/blocked.'; $status='rejected'; }
    elseif($callEligible){ $next='push_to_call_queue'; $action='Approved and call eligible. Push to Jessica queue.'; $status='approved'; }
    elseif($emailEligible){ $next='email_or_review'; $action='Approved for email/nurture; not phone-call eligible yet.'; $status='approved'; }

    $candidates[]=candidate131([
      'source_table'=>$s['source_table'],'source_id'=>(string)$s['source_id'],'source_name'=>$s['source_name'],'source_type'=>$s['source_type'],
      'owner_name'=>$s['owner_name']??'','phone'=>$phone,'email'=>$email,'property_address'=>$s['property_address']??'',
      'mailing_address'=>$s['mailing_address']??'','town'=>$town,'state'=>$s['state']??'CT','market'=>'Lower Fairfield County',
      'property_type'=>$s['property_type']??'','years_owned'=>(float)($s['years_owned']??0),'last_sale_price'=>(float)($s['last_sale_price']??0),
      'estimated_value'=>$value,'estimated_equity'=>$equity,'owner_occupied'=>!empty($s['owner_occupied']),
      'dnc_status'=>$dnc,'dnc_checked'=>$dnc!=='unchecked','dnc_match'=>$dncMatch,'realtor_checked'=>true,'realtor_match'=>$realtorMatch,'realtor_name'=>$realtorName,
      'consent_status'=>$consent,'approval_status'=>$approval,'approved_contact'=>$approved,'call_eligible'=>$callEligible,'sms_eligible'=>$smsEligible,'email_eligible'=>$emailEligible,
      'motivation'=>$mot,'motivation_score'=>$motScore,'contact_score'=>$contactScore,'priority_band'=>$contactScore>=85?'A':($contactScore>=70?'B':'C'),
      'recommended_action'=>$action,'next_step'=>$next,'raw_payload'=>$s['raw_payload']??$s,'status'=>$status
    ]);
  }

  usort($candidates,function($a,$b){return $b['contact_score']<=>$a['contact_score'];});

  $inserted=[]; $errors=[];
  foreach(array_chunk(array_slice($candidates,0,1000),100) as $chunk){
    $r=sb131('POST','contact_acquisition_candidates',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  // Push approved candidates into compliant_lead_imports and approved_contact_pool.
  $approvedRows=rows131('contact_acquisition_candidates','select=*&status=eq.approved&pushed_to_compliant_imports=eq.false&order=contact_score.desc&limit=500');
  $pushImports=[]; $pushPool=[];
  foreach($approvedRows as $c){
    $pushImports[]=[
      'source_name'=>'V13.1 Contact Acquisition','source_type'=>$c['source_type']??'owner_record','lead_type'=>'seller',
      'name'=>$c['owner_name']??'','phone'=>$c['phone']??'','email'=>$c['email']??'','address'=>$c['property_address']??'',
      'town'=>$c['town']??'','state'=>$c['state']??'CT','market'=>$c['market']??'Lower Fairfield County',
      'consent_status'=>$c['consent_status']??'unknown','dnc_status'=>$c['dnc_status']??'unchecked','approval_status'=>$c['approval_status']??'review',
      'lead_score'=>(int)($c['contact_score']??0),'call_eligible'=>!empty($c['call_eligible']),'sms_eligible'=>!empty($c['sms_eligible']),'email_eligible'=>!empty($c['email_eligible']),
      'notes'=>$c['recommended_action']??'','raw_payload'=>$c,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    $pushPool[]=[
      'pool_date'=>$today,'source_table'=>'contact_acquisition_candidates','source_id'=>(string)($c['id']??''),'source_type'=>$c['source_type']??'owner_record',
      'name'=>$c['owner_name']??'','phone'=>$c['phone']??'','email'=>$c['email']??'','address'=>$c['property_address']??'',
      'town'=>$c['town']??'','market'=>$c['market']??'Lower Fairfield County','lead_type'=>'seller','estimated_value'=>(float)($c['estimated_value']??0),
      'timeline'=>'','motivation'=>$c['motivation']??'','consent_status'=>$c['consent_status']??'unknown','dnc_status'=>$c['dnc_status']??'unchecked',
      'realtor_flag'=>!empty($c['realtor_match']),'do_not_call'=>!empty($c['dnc_match']),'approval_status'=>$c['approval_status']??'review',
      'call_eligible'=>!empty($c['call_eligible']),'sms_eligible'=>!empty($c['sms_eligible']),'email_eligible'=>!empty($c['email_eligible']),
      'contact_score'=>(int)($c['contact_score']??0),'priority_band'=>$c['priority_band']??'C','recommended_channel'=>!empty($c['call_eligible'])?'call':(!empty($c['email_eligible'])?'email':'review'),
      'recommended_action'=>$c['recommended_action']??'','recommended_script'=>'Use seller equity / market-position review approach.','notes'=>$c['recommended_action']??'',
      'raw_payload'=>$c,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }

  $pushed=['imports'=>0,'pool'=>0];
  foreach(array_chunk($pushImports,100) as $chunk){
    $r=sb131('POST','compliant_lead_imports',$chunk); if($r['ok'])$pushed['imports']+=count($chunk); else $errors[]=['push_imports'=>$r['body']];
  }
  foreach(array_chunk($pushPool,100) as $chunk){
    $r=sb131('POST','approved_contact_pool',$chunk); if($r['ok'])$pushed['pool']+=count($chunk); else $errors[]=['push_pool'=>$r['body']];
  }
  foreach($approvedRows as $c){
    sb131('PATCH','contact_acquisition_candidates?id=eq.'.rawurlencode($c['id']),['pushed_to_compliant_imports'=>true,'pushed_to_approved_pool'=>true,'updated_at'=>date('c')]);
  }

  $all=rows131('contact_acquisition_candidates','select=*&order=contact_score.desc,created_at.desc&limit=2000');
  $counts=['approved'=>0,'call'=>0,'email'=>0,'dnc'=>0,'realtor'=>0,'review'=>0]; $towns=[];
  foreach($all as $c){
    if(!empty($c['approved_contact']))$counts['approved']++;
    if(!empty($c['call_eligible']))$counts['call']++;
    if(!empty($c['email_eligible']))$counts['email']++;
    if(!empty($c['dnc_match']))$counts['dnc']++;
    if(!empty($c['realtor_match']))$counts['realtor']++;
    if(($c['status']??'')==='review')$counts['review']++;
    $t=$c['town']?:'Unknown'; $towns[$t]=($towns[$t]??0)+1;
  }
  arsort($towns);

  $top=array_slice($all,0,25);
  $recs=[];
  if($counts['call']===0)$recs[]='Still no call-eligible contacts. Import owner/phone data with DNC clear + opt-in/prior relationship to unlock calls.';
  if($counts['approved']>0)$recs[]='Approved contacts exist. Re-run approved-contact-pipeline, queue-intelligence, and listing-intelligence.';
  $recs[]='Next operational step: upload owner/contact CSVs or inbound leads into homeowner_intelligence, leads, or jessica_clean_list_items.';

  $brief="V13.1 CONTACT ACQUISITION ENGINE\\n";
  $brief.="========================================\\n\\n";
  $brief.="Total Candidates:     ".count($all)."\\n";
  $brief.="Approved Contacts:    ".$counts['approved']."\\n";
  $brief.="Call Eligible:        ".$counts['call']."\\n";
  $brief.="Email Eligible:       ".$counts['email']."\\n";
  $brief.="DNC Blocked:          ".$counts['dnc']."\\n";
  $brief.="Realtors Removed:     ".$counts['realtor']."\\n";
  $brief.="Review Needed:        ".$counts['review']."\\n";
  $brief.="Top Town:             ".(array_key_first($towns)?:'n/a')."\\n";
  $brief.="Pushed to Imports:    ".$pushed['imports']."\\n";
  $brief.="Pushed to Pool:       ".$pushed['pool']."\\n\\n";
  $brief.="TOP CANDIDATES\\n----------------------------------------\\n";
  foreach(array_slice($top,0,15) as $i=>$c){
    $brief.=($i+1).". ".(($c['owner_name']??'')?:'Unnamed Owner')." — ".($c['town']??'')." — Score ".($c['contact_score']??0)." — ".($c['next_step']??'review')."\\n";
  }
  $brief.="\\nRECOMMENDATIONS\\n----------------------------------------\\n";
  foreach($recs as $i=>$r){ $brief.=($i+1).". {$r}\\n"; }

  $daily=[[
    'briefing_date'=>$today,'total_candidates'=>count($all),'approved_contacts'=>$counts['approved'],'call_eligible'=>$counts['call'],'email_eligible'=>$counts['email'],
    'dnc_blocked'=>$counts['dnc'],'realtors_removed'=>$counts['realtor'],'review_needed'=>$counts['review'],'top_town'=>array_key_first($towns)?:'',
    'top_candidates'=>$top,'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb131('POST','contact_acquisition_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb131('PATCH','contact_acquisition_briefings?briefing_date=eq.'.rawurlencode($today),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'new_candidates_created'=>count($candidates),
    'total_candidates'=>count($all),
    'approved_contacts'=>$counts['approved'],
    'call_eligible'=>$counts['call'],
    'email_eligible'=>$counts['email'],
    'dnc_blocked'=>$counts['dnc'],
    'realtors_removed'=>$counts['realtor'],
    'review_needed'=>$counts['review'],
    'pushed'=>$pushed,
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>