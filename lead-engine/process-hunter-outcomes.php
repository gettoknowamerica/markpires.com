<?php
/**
 * V10.3 Hunter Learning Processor
 * Upload: /public_html/lead-engine/process-hunter-outcomes.php
 *
 * Run:
 * /lead-engine/process-hunter-outcomes.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
if(!function_exists('str_contains')){
  function str_contains($haystack,$needle){return $needle!==''&&strpos((string)$haystack,(string)$needle)!==false;}
}
function sb103($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function normphone103($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)===11&&substr($d,0,1)==='1')$d=substr($d,1);return $d;}
function classify103($call){
  $text=strtolower(
    ($call['summary']??'').' '.($call['transcript']??'').' '.($call['end_reason']??'').' '.($call['sentiment']??'').' '.json_encode($call['metadata']??[])
  );
  $score=(int)($call['lead_score']??0);
  $outcome='nurture'; $conf=60;
  $answered=false;$vm=false;$future=false;$appt=false;$wrong=false;$dnc=false;

  if(str_contains($text,'voicemail')||str_contains($text,'answering machine')||str_contains($text,'left a message')){$outcome='voicemail';$vm=true;$conf=80;}
  if(str_contains($text,'wrong number')||str_contains($text,'not the right number')){$outcome='wrong_number';$wrong=true;$conf=90;}
  if(str_contains($text,'do not call')||str_contains($text,'take me off')||str_contains($text,'remove me')){$outcome='dnc_request';$dnc=true;$conf=95;}
  if(str_contains($text,'not interested')){$outcome='not_interested';$answered=true;$conf=80;}
  if(str_contains($text,'maybe')||str_contains($text,'next year')||str_contains($text,'few months')||str_contains($text,'spring')||str_contains($text,'summer')||str_contains($text,'future')){$outcome='future_seller';$answered=true;$future=true;$conf=82;}
  if(str_contains($text,'appointment')||str_contains($text,'meet')||str_contains($text,'come by')||str_contains($text,'schedule')||!empty($call['appointment_requested'])){$outcome='appointment';$answered=true;$appt=true;$future=true;$conf=92;}
  if(!empty($call['hot_lead'])||$score>=90){$answered=true;if($outcome==='nurture'){$outcome='interested';$conf=88;}}
  if(str_contains($text,'hello')||str_contains($text,'yes')||str_contains($text,'speaking'))$answered=true;

  $follow=null;
  if($outcome==='voicemail')$follow=date('c',strtotime('+3 days 10:00'));
  elseif($outcome==='not_interested')$follow=date('c',strtotime('+180 days 10:00'));
  elseif($outcome==='future_seller')$follow=date('c',strtotime('+60 days 10:00'));
  elseif($outcome==='appointment')$follow=date('c',strtotime('+1 day 9:00'));
  elseif($outcome==='interested')$follow=date('c',strtotime('+7 days 10:00'));

  return compact('outcome','conf','answered','vm','future','appt','wrong','dnc','follow');
}

$limit=max(25,min(500,(int)($_GET['limit']??250)));
$calls=sb103('GET','call_intelligence?select=*&source=ilike.*hunter*&order=created_at.desc&limit='.$limit)['data'];
if(empty($calls)){
  $calls=sb103('GET','call_intelligence?select=*&order=created_at.desc&limit='.$limit)['data'];
}

$created=[];$skipped=[];$updatedCampaigns=[];

foreach($calls as $call){
  if(!is_array($call))continue;
  $callId=(string)($call['call_id']??'');
  $phone=normphone103($call['phone']??($call['to_number']??''));
  if(!$callId||!$phone){$skipped[]=['call_id'=>$callId,'reason'=>'missing call id or phone'];continue;}

  $exists=sb103('GET','hunter_outcomes?select=id&call_id=eq.'.rawurlencode($callId).'&limit=1');
  if(!empty($exists['data'])){$skipped[]=['call_id'=>$callId,'reason'=>'already processed'];continue;}

  $q=sb103('GET','hunter_queue?select=*&phone=eq.'.rawurlencode($phone).'&order=created_at.desc&limit=1');
  $hunter=$q['data'][0]??null;
  if(!$hunter){$skipped[]=['call_id'=>$callId,'phone'=>$phone,'reason'=>'no hunter queue match'];continue;}

  $class=classify103($call);
  $payload=[[
    'hunter_queue_id'=>$hunter['id']??null,
    'homeowner_id'=>$hunter['homeowner_id']??null,
    'campaign_id'=>$hunter['campaign_id']??null,
    'campaign_name'=>$hunter['campaign_name']??'',
    'call_id'=>$callId,
    'phone'=>$phone,
    'owner_name'=>$hunter['owner_name']??($call['name']??''),
    'town'=>$hunter['town']??($call['town']??''),
    'outcome'=>$class['outcome'],
    'answered'=>$class['answered'],
    'voicemail'=>$class['vm'],
    'future_seller'=>$class['future'],
    'appointment_requested'=>$class['appt'],
    'wrong_number'=>$class['wrong'],
    'dnc_request'=>$class['dnc'],
    'next_followup_at'=>$class['follow'],
    'notes'=>$call['summary']??'',
    'jessica_summary'=>$call['summary']??'',
    'lead_score'=>(int)($call['lead_score']??0),
    'raw_payload'=>$call,
    'created_at'=>date('c')
  ]];
  $ins=sb103('POST','hunter_outcomes',$payload);
  if(!$ins['ok']){$skipped[]=['call_id'=>$callId,'reason'=>'insert failed','body'=>$ins['body']];continue;}

  $newStatus='called';
  if($class['dnc'])$newStatus='dnc';
  elseif($class['wrong'])$newStatus='dead';
  elseif($class['appt'])$newStatus='hot';
  elseif($class['future'])$newStatus='future_seller';

  sb103('PATCH','hunter_queue?id=eq.'.rawurlencode($hunter['id']),[
    'status'=>$newStatus,
    'last_outcome'=>$class['outcome'],
    'outcome_confidence'=>$class['conf'],
    'next_followup_at'=>$class['follow'],
    'last_attempt_at'=>date('c'),
    'attempts'=>(int)($hunter['attempts']??0)+1,
    'learning_notes'=>$call['summary']??'',
    'updated_at'=>date('c')
  ]);

  if($class['dnc'] && !empty($hunter['homeowner_id'])){
    sb103('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($hunter['homeowner_id']),[
      'dnc_status'=>'listed',
      'dnc_reason'=>'Jessica hunter call DNC request',
      'last_outcome'=>'dnc_request',
      'last_outcome_at'=>date('c'),
      'updated_at'=>date('c')
    ]);
  } elseif(!empty($hunter['homeowner_id'])){
    sb103('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($hunter['homeowner_id']),[
      'last_outcome'=>$class['outcome'],
      'last_outcome_at'=>date('c'),
      'next_followup_at'=>$class['follow'],
      'call_attempts'=>(int)($hunter['attempts']??0)+1,
      'learning_notes'=>$call['summary']??'',
      'updated_at'=>date('c')
    ]);
  }

  if($class['future'] || $class['appt']){
    sb103('POST','future_seller_pipeline',[[
      'related_type'=>'hunter_outcome',
      'related_id'=>$callId,
      'homeowner_id'=>$hunter['homeowner_id']??null,
      'call_id'=>$callId,
      'name'=>$hunter['owner_name']??($call['name']??''),
      'phone'=>$phone,
      'email'=>$hunter['email']??'',
      'address'=>$hunter['address']??'',
      'town'=>$hunter['town']??($call['town']??''),
      'source'=>'homeowner_hunter',
      'expected_timeline'=>$class['appt']?'Appointment requested':'Future seller',
      'next_followup_at'=>$class['follow']?:date('c',strtotime('+30 days')),
      'followup_bucket'=>'0_30',
      'lead_score'=>(int)($call['lead_score']??0),
      'priority'=>$class['appt']?'hot':'high',
      'status'=>'active',
      'recommended_action'=>$class['appt']?'Book appointment immediately.':'Queue future seller follow-up.',
      'motivation'=>$call['motivation']??'',
      'notes'=>$call['summary']??'',
      'jessica_summary'=>$call['summary']??'',
      'raw_payload'=>$call,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
  }

  $created[]=['call_id'=>$callId,'phone'=>$phone,'outcome'=>$class['outcome'],'status'=>$newStatus];
}

$campaigns=sb103('GET','hunter_campaigns?select=*&limit=200')['data'];
foreach($campaigns as $c){
  if(empty($c['id']))continue;
  $cid=$c['id'];
  $out=sb103('GET','hunter_outcomes?select=outcome,answered,future_seller,appointment_requested,wrong_number,dnc_request&campaign_id=eq.'.rawurlencode($cid).'&limit=1000')['data'];
  $attempts=count($out);$answered=0;$future=0;$appt=0;$wrong=0;$dnc=0;
  foreach($out as $o){if(!empty($o['answered']))$answered++;if(!empty($o['future_seller']))$future++;if(!empty($o['appointment_requested']))$appt++;if(!empty($o['wrong_number']))$wrong++;if(!empty($o['dnc_request']))$dnc++;}
  $conv=$attempts?round(($future+$appt)/$attempts,4):0;
  $r=sb103('PATCH','hunter_campaigns?id=eq.'.rawurlencode($cid),[
    'calls_attempted'=>$attempts,
    'calls_answered'=>$answered,
    'future_sellers_found'=>$future,
    'appointments_found'=>$appt,
    'wrong_numbers'=>$wrong,
    'dnc_requests'=>$dnc,
    'conversion_rate'=>$conv,
    'updated_at'=>date('c')
  ]);
  if($r['ok'])$updatedCampaigns[]=['campaign'=>$c['name']??'','attempts'=>$attempts,'conversion_rate'=>$conv];
}

echo json_encode([
  'success'=>true,
  'created'=>count($created),
  'created_items'=>$created,
  'skipped'=>array_slice($skipped,0,100),
  'campaigns_updated'=>$updatedCampaigns
],JSON_PRETTY_PRINT);
?>