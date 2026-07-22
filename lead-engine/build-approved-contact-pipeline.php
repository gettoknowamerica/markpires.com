<?php
/**
 * V13.1.2 Approved Contact Pipeline Priority Patch
 * Upload over: /public_html/lead-engine/build-approved-contact-pipeline.php
 *
 * Fixes: old research-only records burying newly approved contacts.
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

  function sb132($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows132($table,$query){ $r=sb132('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function phone132($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }
  function score132($r){
    $s=0;
    if(!empty($r['phone']))$s+=28;
    if(!empty($r['email']))$s+=14;
    if(!empty($r['address']))$s+=14;
    if(!empty($r['town']))$s+=10;
    if(($r['consent_status']??'')==='opt_in')$s+=20;
    if(in_array(($r['consent_status']??''),['prior_relationship','business_contact'],true))$s+=14;
    if(($r['dnc_status']??'')==='clear')$s+=10;
    if(($r['approval_status']??'')==='approved')$s+=10;
    if(!empty($r['call_eligible']))$s+=18;
    return min(100,max(0,$s));
  }
  function item132($r){
    $phone=phone132($r['phone']??'');
    $email=strtolower(trim((string)($r['email']??'')));
    $dnc=$r['dnc_status']??'unchecked';
    $consent=$r['consent_status']??'unknown';
    $approval=$r['approval_status']??'review';
    $realtor=!empty($r['realtor_flag']) || !empty($r['realtor_match']);
    $doNot=!empty($r['do_not_call']) || in_array($dnc,['listed','blocked','do_not_call'],true) || $realtor;
    $call=(!$doNot && $phone && $dnc==='clear' && in_array($consent,['opt_in','business_contact','prior_relationship'],true) && in_array($approval,['approved','imported'],true));
    $sms=$call && $consent==='opt_in';
    $em=(!$doNot && $email && in_array($approval,['approved','imported'],true));
    $score=score132(['phone'=>$phone,'email'=>$email,'address'=>$r['address']??'','town'=>$r['town']??'','consent_status'=>$consent,'dnc_status'=>$dnc,'approval_status'=>$approval,'call_eligible'=>$call]);
    return [
      'pool_date'=>date('Y-m-d'),
      'source_table'=>$r['source_table']??'',
      'source_id'=>(string)($r['source_id']??''),
      'source_type'=>$r['source_type']??'unknown',
      'name'=>$r['name']??($r['owner_name']??''),
      'phone'=>$phone,
      'email'=>$email,
      'address'=>$r['address']??($r['property_address']??''),
      'town'=>$r['town']??'',
      'market'=>$r['market']??'Lower Fairfield County',
      'lead_type'=>$r['lead_type']??'seller',
      'estimated_value'=>(float)($r['estimated_value']??0),
      'timeline'=>$r['timeline']??'',
      'motivation'=>$r['motivation']??'',
      'consent_status'=>$consent,
      'dnc_status'=>$dnc,
      'realtor_flag'=>$realtor,
      'do_not_call'=>$doNot,
      'approval_status'=>$approval,
      'call_eligible'=>$call,
      'sms_eligible'=>$sms,
      'email_eligible'=>$em,
      'contact_score'=>$score,
      'priority_band'=>$score>=85?'A':($score>=70?'B':'C'),
      'recommended_channel'=>$call?'call':($em?'email':'review'),
      'recommended_action'=>$call?'Call today with seller market-position script.':($em?'Email/nurture; phone not approved.':'Review compliance/source before contact.'),
      'recommended_script'=>$call?'Use seller equity and market-position review approach.':'Do not call until DNC/consent/source are approved.',
      'notes'=>$r['notes']??'',
      'raw_payload'=>$r['raw_payload']??$r,
      'status'=>'active',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
  }

  $sources=[];

  // PRIORITY 1: V13.1 acquisition approved/callable candidates
  $acq=rows132('contact_acquisition_candidates','select=*&status=eq.approved&order=contact_score.desc,created_at.desc&limit=500');
  foreach($acq as $c){
    $sources[]=[
      'source_table'=>'contact_acquisition_candidates','source_id'=>$c['id']??'','source_type'=>$c['source_type']??'contact_acquisition',
      'name'=>$c['owner_name']??'','phone'=>$c['phone']??'','email'=>$c['email']??'','address'=>$c['property_address']??'',
      'town'=>$c['town']??'','market'=>$c['market']??'Lower Fairfield County','lead_type'=>'seller','estimated_value'=>$c['estimated_value']??0,
      'motivation'=>$c['motivation']??'','consent_status'=>$c['consent_status']??'unknown','dnc_status'=>$c['dnc_status']??'unchecked',
      'approval_status'=>$c['approval_status']??'review','realtor_match'=>$c['realtor_match']??false,'call_eligible'=>$c['call_eligible']??false,'notes'=>$c['recommended_action']??'','raw_payload'=>$c
    ];
  }

  // PRIORITY 2: existing approved_contact_pool call/email eligible
  $pool=rows132('approved_contact_pool','select=*&or=(call_eligible.eq.true,email_eligible.eq.true)&order=contact_score.desc,created_at.desc&limit=500');
  foreach($pool as $p){ $p['source_table']='approved_contact_pool'; $p['source_id']=$p['id']??''; $sources[]=$p; }

  // PRIORITY 3: compliant imports approved/call eligible
  $imports=rows132('compliant_lead_imports','select=*&or=(call_eligible.eq.true,email_eligible.eq.true)&order=lead_score.desc,created_at.desc&limit=500');
  foreach($imports as $i){
    $sources[]=[
      'source_table'=>'compliant_lead_imports','source_id'=>$i['id']??'','source_type'=>$i['source_type']??'compliant_import',
      'name'=>$i['name']??'','phone'=>$i['phone']??'','email'=>$i['email']??'','address'=>$i['address']??'','town'=>$i['town']??'',
      'market'=>$i['market']??'Lower Fairfield County','lead_type'=>$i['lead_type']??'seller','estimated_value'=>$i['estimated_value']??0,
      'motivation'=>$i['notes']??'','consent_status'=>$i['consent_status']??'unknown','dnc_status'=>$i['dnc_status']??'unchecked','approval_status'=>$i['approval_status']??'review',
      'call_eligible'=>$i['call_eligible']??false,'notes'=>$i['notes']??'','raw_payload'=>$i
    ];
  }

  // PRIORITY 4: limited research-only review records, not allowed to bury real contacts
  $review=rows132('compliant_lead_imports','select=*&call_eligible=neq.true&order=lead_score.desc,created_at.desc&limit=200');
  foreach($review as $i){
    $sources[]=[
      'source_table'=>'compliant_lead_imports','source_id'=>$i['id']??'','source_type'=>$i['source_type']??'research_plan',
      'name'=>$i['name']??'','phone'=>$i['phone']??'','email'=>$i['email']??'','address'=>$i['address']??'','town'=>$i['town']??'',
      'market'=>$i['market']??'Lower Fairfield County','lead_type'=>$i['lead_type']??'seller','estimated_value'=>$i['estimated_value']??0,
      'motivation'=>$i['notes']??'','consent_status'=>$i['consent_status']??'unknown','dnc_status'=>$i['dnc_status']??'unchecked','approval_status'=>$i['approval_status']??'review',
      'call_eligible'=>false,'notes'=>$i['notes']??'','raw_payload'=>$i
    ];
  }

  $seen=[];$items=[];
  foreach($sources as $s){
    $it=item132($s);
    $key=$it['phone']?'p:'.$it['phone']:($it['email']?'e:'.$it['email']:'x:'.md5(($it['source_table']??'').($it['source_id']??'').json_encode($it)));
    if(isset($seen[$key])) continue;
    $seen[$key]=true;
    $items[]=$it;
  }
  usort($items,function($a,$b){
    if($a['call_eligible']!==$b['call_eligible']) return $a['call_eligible']?-1:1;
    if($a['email_eligible']!==$b['email_eligible']) return $a['email_eligible']?-1:1;
    return $b['contact_score']<=>$a['contact_score'];
  });

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($items,0,1000),100) as $chunk){
    $r=sb132('POST','approved_contact_pool',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $counts=['call'=>0,'sms'=>0,'email'=>0,'review'=>0,'dnc'=>0,'realtor'=>0];
  foreach($items as $p){
    if($p['call_eligible'])$counts['call']++;
    if($p['sms_eligible'])$counts['sms']++;
    if($p['email_eligible'])$counts['email']++;
    if($p['recommended_channel']==='review')$counts['review']++;
    if($p['do_not_call'])$counts['dnc']++;
    if($p['realtor_flag'])$counts['realtor']++;
  }
  $top=array_slice($items,0,25);
  $brief="Approved Contact Pipeline V13.1.2\\n========================================\\n\\n";
  $brief.="Total contacts: ".count($items)."\\nCall eligible: ".$counts['call']."\\nEmail eligible: ".$counts['email']."\\nReview needed: ".$counts['review']."\\n\\nTop contacts:\\n";
  foreach($top as $i=>$p){$brief.=($i+1).". ".($p['name']?:'Unnamed')." — ".$p['town']." — ".$p['recommended_channel']." — Score ".$p['contact_score']."\\n";}

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_contacts'=>count($items),'call_eligible'=>$counts['call'],'sms_eligible'=>$counts['sms'],
    'email_eligible'=>$counts['email'],'review_needed'=>$counts['review'],'realtor_removed'=>$counts['realtor'],'do_not_call_count'=>$counts['dnc'],
    'top_contacts'=>$top,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb132('POST','approved_contact_pipeline_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb132('PATCH','approved_contact_pipeline_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'total_contacts'=>count($items),'call_eligible'=>$counts['call'],'sms_eligible'=>$counts['sms'],'email_eligible'=>$counts['email'],'review_needed'=>$counts['review'],'inserted'=>$inserted,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>