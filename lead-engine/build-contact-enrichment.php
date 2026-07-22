<?php
/**
 * V14.2 Contact Enrichment Queue Builder
 * Upload: /public_html/lead-engine/build-contact-enrichment.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb142($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows142($table,$query){ $r=sb142('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function phone142($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }
  function value142($town,$v=0){
    $v=(float)$v; if($v>0)return $v;
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)) return 1400000;
    if(in_array($town,['Wilton','Fairfield','Weston'],true)) return 950000;
    if(in_array($town,['Stamford','Norwalk','Trumbull'],true)) return 750000;
    return 650000;
  }
  function qitem142($o){
    $base=[
      'queue_date'=>date('Y-m-d'),'source_table'=>'','source_id'=>'','source_type'=>'','source_url'=>'',
      'owner_name'=>'','property_address'=>'','mailing_address'=>'','town'=>'','state'=>'CT','market'=>'Lower Fairfield County',
      'estimated_value'=>0,'estimated_commission'=>0,'expected_value'=>0,'current_phone'=>'','current_email'=>'',
      'enriched_phone'=>'','enriched_email'=>'','enrichment_provider'=>'','enrichment_status'=>'needs_contact',
      'dnc_status'=>'unchecked','realtor_status'=>'unchecked','approval_status'=>'review','call_eligible'=>false,'email_eligible'=>false,
      'priority_score'=>0,'contact_completeness_score'=>0,'seller_score'=>0,'priority_band'=>'C','recommended_action'=>'','next_step'=>'',
      'pushed_to_acquisition'=>false,'pushed_to_approved_pool'=>false,'pushed_to_pipeline'=>false,'notes'=>'','raw_payload'=>[],
      'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }

  $existing=rows142('contact_enrichment_queue','select=source_table,source_id,property_address,current_phone,current_email,enriched_phone,enriched_email&status=eq.active&limit=5000');
  $seen=[];
  foreach($existing as $e){
    if(!empty($e['source_table']) && !empty($e['source_id'])) $seen[$e['source_table'].':'.$e['source_id']]=true;
    $addr=strtolower(trim($e['property_address']??'')); if($addr) $seen['addr:'.$addr]=true;
  }

  $new=[];

  // Seller Opportunity Engine sources are first priority.
  $seller=rows142('seller_opportunity_sources','select=*&status=eq.active&order=total_seller_score.desc,created_at.desc&limit=1000');
  foreach($seller as $s){
    $key='seller_opportunity_sources:'.($s['id']??'');
    $addr=strtolower(trim($s['property_address']??''));
    if(isset($seen[$key]) || ($addr && isset($seen['addr:'.$addr]))) continue;
    $town=$s['town']??'';
    $val=value142($town,$s['estimated_value']??($s['list_price']??0));
    $commission=$val*.025;
    $phone=phone142($s['owner_phone']??'');
    $email=strtolower(trim($s['owner_email']??''));
    $complete=0; if($phone)$complete+=50; if($email)$complete+=30; if(!empty($s['owner_name']))$complete+=10; if(!empty($s['property_address']))$complete+=10;
    $sellerScore=(int)($s['total_seller_score']??0);
    $priority=min(100,round(($sellerScore*.55)+(($val>=1200000?90:($val>=750000?75:60))*.25)+($complete*.20)));
    $status=($phone||$email)?'contact_found':'needs_contact';
    if($phone && ($s['dnc_status']??'unchecked')==='unchecked') $status='needs_dnc';
    if($phone && ($s['dnc_status']??'')==='clear' && ($s['realtor_status']??'unchecked')==='unchecked') $status='needs_realtor_check';
    if($phone && ($s['dnc_status']??'')==='clear' && ($s['realtor_status']??'')==='clear') $status='needs_approval';

    $new[]=qitem142([
      'source_table'=>'seller_opportunity_sources','source_id'=>(string)($s['id']??''),'source_type'=>$s['source_type']??'seller_source','source_url'=>$s['source_url']??'',
      'owner_name'=>$s['owner_name']??'','property_address'=>$s['property_address']??'','town'=>$town,'state'=>$s['state']??'CT',
      'estimated_value'=>$val,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*0.15,2),
      'current_phone'=>$phone,'current_email'=>$email,'enriched_phone'=>$phone,'enriched_email'=>$email,'enrichment_status'=>$status,
      'dnc_status'=>$s['dnc_status']??'unchecked','realtor_status'=>$s['realtor_status']??'unchecked','approval_status'=>$s['approval_status']??'review',
      'priority_score'=>$priority,'contact_completeness_score'=>$complete,'seller_score'=>$sellerScore,
      'priority_band'=>$priority>=85?'A':($priority>=70?'B':'C'),
      'recommended_action'=>($phone||$email)?'Verify DNC/Realtor/approval, then push to approved pool.':'Enrich phone/email from owner-data provider or manual research.',
      'next_step'=>($phone||$email)?'compliance_review':'contact_enrichment',
      'notes'=>'Imported from V14.1 Seller Opportunity Engine.','raw_payload'=>$s
    ]);
  }

  // Contact acquisition review records with missing phone/email.
  $acq=rows142('contact_acquisition_candidates','select=*&status=eq.review&order=contact_score.desc,created_at.desc&limit=500');
  foreach($acq as $c){
    $key='contact_acquisition_candidates:'.($c['id']??'');
    $addr=strtolower(trim($c['property_address']??''));
    if(isset($seen[$key]) || ($addr && isset($seen['addr:'.$addr]))) continue;
    $town=$c['town']??'';
    $val=value142($town,$c['estimated_value']??0);
    $phone=phone142($c['phone']??'');
    $email=strtolower(trim($c['email']??''));
    if($phone || $email) continue;
    $commission=$val*.025;
    $score=(int)($c['contact_score']??0);
    $priority=min(100,round(($score*.50)+(($val>=1200000?90:($val>=750000?75:60))*.50)));
    $new[]=qitem142([
      'source_table'=>'contact_acquisition_candidates','source_id'=>(string)($c['id']??''),'source_type'=>$c['source_type']??'acquisition_review',
      'owner_name'=>$c['owner_name']??'','property_address'=>$c['property_address']??'','town'=>$town,'state'=>$c['state']??'CT',
      'estimated_value'=>$val,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*0.10,2),
      'enrichment_status'=>'needs_contact','priority_score'=>$priority,'seller_score'=>$score,'priority_band'=>$priority>=85?'A':($priority>=70?'B':'C'),
      'recommended_action'=>'Needs contact enrichment before outreach.','next_step'=>'contact_enrichment','notes'=>'Imported from acquisition review queue.','raw_payload'=>$c
    ]);
  }

  usort($new,function($a,$b){ return $b['priority_score']<=>$a['priority_score']; });

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,1000),100) as $chunk){
    $r=sb142('POST','contact_enrichment_queue',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  // Process items that now have contact + clear statuses into acquisition/pool/pipeline.
  $ready=rows142('contact_enrichment_queue','select=*&status=eq.active&enrichment_status=in.(needs_approval,approved,call_queue)&order=priority_score.desc&limit=500');
  $pushed=['acquisition'=>0,'pool'=>0,'pipeline'=>0];
  foreach($ready as $q){
    $phone=phone142($q['enriched_phone'] ?: $q['current_phone']);
    $email=strtolower(trim($q['enriched_email'] ?: $q['current_email']));
    $call=($phone && ($q['dnc_status']??'')==='clear' && ($q['realtor_status']??'')==='clear' && in_array(($q['approval_status']??''),['approved','imported'],true));
    $emailEligible=($email && in_array(($q['approval_status']??''),['approved','imported'],true) && ($q['realtor_status']??'')==='clear');
    if(!$call && !$emailEligible) continue;

    if(empty($q['pushed_to_acquisition'])){
      $acq=[[
        'source_table'=>'contact_enrichment_queue','source_id'=>(string)$q['id'],'source_name'=>'V14.2 Contact Enrichment Queue','source_type'=>$q['source_type']??'enriched_contact',
        'owner_name'=>$q['owner_name']??'','phone'=>$phone,'email'=>$email,'property_address'=>$q['property_address']??'','mailing_address'=>$q['mailing_address']??'',
        'town'=>$q['town']??'','state'=>$q['state']??'CT','market'=>$q['market']??'Lower Fairfield County','estimated_value'=>(float)($q['estimated_value']??0),
        'dnc_status'=>$q['dnc_status']??'unchecked','dnc_checked'=>($q['dnc_status']??'unchecked')!=='unchecked','dnc_match'=>($q['dnc_status']??'')!=='clear',
        'realtor_checked'=>($q['realtor_status']??'unchecked')!=='unchecked','realtor_match'=>($q['realtor_status']??'')!=='clear',
        'consent_status'=>'manual_enrichment','approval_status'=>$q['approval_status']??'review','approved_contact'=>$call||$emailEligible,'call_eligible'=>$call,'email_eligible'=>$emailEligible,
        'motivation'=>'Enriched seller opportunity','motivation_score'=>(int)($q['seller_score']??0),'contact_score'=>(int)($q['priority_score']??0),'priority_band'=>$q['priority_band']??'C',
        'recommended_action'=>$call?'Call approved enriched seller opportunity.':'Approved for email/nurture; phone not call eligible.','next_step'=>$call?'push_to_call_queue':'email_nurture',
        'raw_payload'=>$q,'status'=>($call||$emailEligible)?'approved':'review','created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $r=sb142('POST','contact_acquisition_candidates',$acq);
      if($r['ok']){$pushed['acquisition']++; sb142('PATCH','contact_enrichment_queue?id=eq.'.rawurlencode($q['id']),['pushed_to_acquisition'=>true,'call_eligible'=>$call,'email_eligible'=>$emailEligible,'enrichment_status'=>$call?'call_queue':'approved','updated_at'=>date('c')]);}
    }

    if($call && empty($q['pushed_to_approved_pool'])){
      $pool=[[
        'pool_date'=>date('Y-m-d'),'source_table'=>'contact_enrichment_queue','source_id'=>(string)$q['id'],'source_type'=>$q['source_type']??'enriched_contact',
        'name'=>$q['owner_name']??'','phone'=>$phone,'email'=>$email,'address'=>$q['property_address']??'','town'=>$q['town']??'','market'=>$q['market']??'Lower Fairfield County',
        'lead_type'=>'seller','estimated_value'=>(float)($q['estimated_value']??0),'motivation'=>'Enriched seller opportunity','consent_status'=>'manual_enrichment',
        'dnc_status'=>$q['dnc_status']??'clear','realtor_flag'=>false,'do_not_call'=>false,'approval_status'=>$q['approval_status']??'approved',
        'call_eligible'=>true,'sms_eligible'=>false,'email_eligible'=>$emailEligible,'contact_score'=>(int)($q['priority_score']??0),'priority_band'=>$q['priority_band']??'C',
        'recommended_channel'=>'call','recommended_action'=>'Call today with seller opportunity review approach.','recommended_script'=>'Use seller equity / market-position review approach.',
        'notes'=>$q['notes']??'','raw_payload'=>$q,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $r=sb142('POST','approved_contact_pool',$pool);
      if($r['ok']){$pushed['pool']++; sb142('PATCH','contact_enrichment_queue?id=eq.'.rawurlencode($q['id']),['pushed_to_approved_pool'=>true,'updated_at'=>date('c')]);}
    }

    if($call && empty($q['pushed_to_pipeline'])){
      $commission=(float)($q['estimated_commission']??0); if($commission<=0)$commission=(float)($q['estimated_value']??0)*.025;
      $pipe=[[
        'pipeline_date'=>date('Y-m-d'),'source_table'=>'contact_enrichment_queue','source_id'=>(string)$q['id'],'opportunity_type'=>'seller',
        'name'=>$q['owner_name']??'','phone'=>$phone,'email'=>$email,'address'=>$q['property_address']??'','town'=>$q['town']??'',
        'pipeline_stage'=>'call_queue','stage_score'=>45,'priority_score'=>(int)($q['priority_score']??0),'probability'=>35,
        'estimated_sale_price'=>(float)($q['estimated_value']??0),'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*.35,2),
        'next_step'=>'Call approved enriched seller opportunity.','next_followup_at'=>date('c',strtotime('+1 day')),'last_activity_at'=>date('c'),
        'notes'=>'V14.2 enriched opportunity.','raw_payload'=>$q,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $r=sb142('POST','jessica_opportunity_pipeline',$pipe);
      if($r['ok']){$pushed['pipeline']++; sb142('PATCH','contact_enrichment_queue?id=eq.'.rawurlencode($q['id']),['pushed_to_pipeline'=>true,'updated_at'=>date('c')]);}
    }
  }

  $all=rows142('contact_enrichment_queue','select=*&status=eq.active&order=priority_score.desc,created_at.desc&limit=1000');
  $counts=['needs_contact'=>0,'contact_found'=>0,'needs_dnc'=>0,'needs_realtor_check'=>0,'needs_approval'=>0,'approved'=>0,'call_queue'=>0,'no_contact_found'=>0];
  $towns=[];
  foreach($all as $q){
    $st=$q['enrichment_status']??'needs_contact';
    if(isset($counts[$st]))$counts[$st]++;
    $town=$q['town']?:'Unknown'; $towns[$town]=($towns[$town]??0)+1;
  }
  arsort($towns);

  $recs=[
    'Prioritize A/B enrichment records before buying broad lists.',
    'No phone/email should remain in enrichment, not the call queue.',
    'Once phone is found: DNC clear + realtor clear + approval are required before calling.'
  ];

  $brief="V14.2 CONTACT ENRICHMENT QUEUE\\n========================================\\n\\n";
  $brief.="Total Queue:          ".count($all)."\\n";
  $brief.="Needs Contact:        ".$counts['needs_contact']."\\n";
  $brief.="Contact Found:        ".$counts['contact_found']."\\n";
  $brief.="Needs DNC:            ".$counts['needs_dnc']."\\n";
  $brief.="Needs Realtor Check:  ".$counts['needs_realtor_check']."\\n";
  $brief.="Needs Approval:       ".$counts['needs_approval']."\\n";
  $brief.="Approved:             ".$counts['approved']."\\n";
  $brief.="Call Queue:           ".$counts['call_queue']."\\n";
  $brief.="Top Town:             ".(array_key_first($towns)?:'n/a')."\\n";
  $brief.="Pushed Acquisition:   ".$pushed['acquisition']."\\n";
  $brief.="Pushed Pool:          ".$pushed['pool']."\\n";
  $brief.="Pushed Pipeline:      ".$pushed['pipeline']."\\n\\n";
  $brief.="TOP ENRICHMENT RECORDS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,15) as $i=>$q){
    $brief.=($i+1).". ".(($q['property_address']??'')?:($q['owner_name']??'Opportunity'))." — ".$q['town']." — ".$q['enrichment_status']." — Score ".$q['priority_score']."\\n";
    $brief.="     Action: ".$q['recommended_action']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_queue'=>count($all),'needs_contact'=>$counts['needs_contact'],'contact_found'=>$counts['contact_found'],
    'needs_dnc'=>$counts['needs_dnc'],'needs_realtor_check'=>$counts['needs_realtor_check'],'needs_approval'=>$counts['needs_approval'],
    'approved'=>$counts['approved'],'call_queue'=>$counts['call_queue'],'no_contact_found'=>$counts['no_contact_found'],'top_town'=>array_key_first($towns)?:'',
    'top_records'=>array_slice($all,0,25),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb142('POST','contact_enrichment_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb142('PATCH','contact_enrichment_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'new_queue_created'=>count($new),'total_queue'=>count($all),'pushed'=>$pushed,'briefing'=>$brief,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>