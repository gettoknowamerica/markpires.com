<?php
/**
 * V10.8 Jessica Opportunity Router
 * Upload: /public_html/lead-engine/route-jessica-opportunities.php
 *
 * Run:
 * /lead-engine/route-jessica-opportunities.php?key=YOUR_KEY
 *
 * Routes hot hunter/future/mission items into:
 * - appointment_requests
 * - mark_action_queue
 * - future_seller_pipeline
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb108($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function log108($sourceTable,$sourceId,$routeType,$to,$ok,$reason,$payload,$response){
  sb108('POST','jessica_opportunity_router_log',[[
    'source_table'=>$sourceTable,'source_id'=>(string)$sourceId,'route_type'=>$routeType,'routed_to'=>$to,
    'ok'=>$ok,'reason'=>$reason,'payload'=>$payload,'response'=>$response,'created_at'=>date('c')
  ]]);
}
function existing_appt108($phone,$sourceId){
  if($sourceId){
    $r=sb108('GET','appointment_requests?select=id&related_id=eq.'.rawurlencode((string)$sourceId).'&status=in.(requested,offered,confirmed)&limit=1');
    if(!empty($r['data']))return true;
  }
  if($phone){
    $r=sb108('GET','appointment_requests?select=id&phone=eq.'.rawurlencode((string)$phone).'&status=in.(requested,offered,confirmed)&limit=1');
    if(!empty($r['data']))return true;
  }
  return false;
}
function existing_action108($phone,$sourceId){
  if($sourceId){
    $r=sb108('GET','mark_action_queue?select=id&related_id=eq.'.rawurlencode((string)$sourceId).'&status=in.(open,pending)&limit=1');
    if(!empty($r['data']))return true;
  }
  if($phone){
    $r=sb108('GET','mark_action_queue?select=id&phone=eq.'.rawurlencode((string)$phone).'&status=in.(open,pending)&limit=1');
    if(!empty($r['data']))return true;
  }
  return false;
}
function create_appt108($sourceTable,$row,$reason){
  $id=$row['id']??'';
  $phone=$row['phone']??'';
  if(existing_appt108($phone,$id))return ['ok'=>false,'skipped'=>true,'reason'=>'appointment already exists'];
  $name=$row['owner_name']??($row['name']??'');
  $score=(int)($row['hunter_score']??($row['priority_score']??($row['lead_score']??0)));
  $payload=[[
    'related_type'=>$sourceTable,
    'related_id'=>(string)$id,
    'jessica_priority_id'=>$sourceTable==='jessica_priority_queue'?(string)$id:'',
    'name'=>$name,
    'phone'=>$phone,
    'email'=>$row['email']??'',
    'address'=>$row['address']??'',
    'town'=>$row['town']??'',
    'appointment_type'=>'seller_consultation',
    'requested_window'=>'Jessica detected appointment-level intent or hot lead urgency.',
    'status'=>'requested',
    'source'=>'jessica_opportunity_router',
    'lead_score'=>$score,
    'notes'=>$reason,
    'jessica_summary'=>$row['jessica_summary']??($row['learning_notes']??($row['reason']??'')),
    'raw_payload'=>$row,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb108('POST','appointment_requests',$payload);
  return ['ok'=>$r['ok'],'http'=>$r['http'],'body'=>$r['body'],'payload'=>$payload];
}
function create_action108($sourceTable,$row,$actionType,$reason){
  $id=$row['id']??'';
  $phone=$row['phone']??'';
  if(existing_action108($phone,$id))return ['ok'=>false,'skipped'=>true,'reason'=>'action already exists'];
  $name=$row['owner_name']??($row['name']??'');
  $score=(int)($row['hunter_score']??($row['priority_score']??($row['lead_score']??0)));
  $payload=[[
    'related_type'=>$sourceTable,
    'related_id'=>(string)$id,
    'action_type'=>$actionType,
    'priority'=>$score>=100?'hot':($score>=85?'high':'medium'),
    'name'=>$name,
    'phone'=>$phone,
    'email'=>$row['email']??'',
    'address'=>$row['address']??'',
    'town'=>$row['town']??'',
    'source'=>'jessica_opportunity_router',
    'recommended_action'=>$reason,
    'status'=>'open',
    'due_at'=>date('c',strtotime('+2 hours')),
    'raw_payload'=>$row,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb108('POST','mark_action_queue',$payload);
  return ['ok'=>$r['ok'],'http'=>$r['http'],'body'=>$r['body'],'payload'=>$payload];
}

$summary=['appointments'=>0,'actions'=>0,'future_sellers'=>0,'skipped'=>0,'errors'=>0];
$results=[];

/* 1. Hot hunter outcomes into appointment/action */
$hunters=sb108('GET','hunter_queue?select=*&status=in.(hot,future_seller,called)&order=hunter_score.desc&limit=300')['data'];
foreach($hunters as $h){
  if(!is_array($h))continue;
  $score=(int)($h['hunter_score']??0);
  $outcome=strtolower((string)($h['last_outcome']??''));
  $shouldAppt=($h['status']??'')==='hot'||$outcome==='appointment'||str_contains(strtolower((string)($h['learning_notes']??'')),'appointment');
  $shouldAction=$score>=90||in_array(($h['status']??''),['hot','future_seller'],true);

  if($shouldAppt && empty($h['routed_to_appointment'])){
    $res=create_appt108('hunter_queue',$h,'Hunter lead requires seller appointment follow-up.');
    if($res['ok']){$summary['appointments']++;sb108('PATCH','hunter_queue?id=eq.'.rawurlencode($h['id']),['routed_to_appointment'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('hunter_queue',$h['id'],'appointment','appointment_requests',$res['ok'],'hot/appointment hunter signal',$h,$res);
    $results[]=['source'=>'hunter_queue','id'=>$h['id'],'route'=>'appointment','ok'=>$res['ok'],'reason'=>$res['reason']??''];
  }

  if($shouldAction && empty($h['routed_to_action_queue'])){
    $res=create_action108('hunter_queue',$h,'hunter_followup','Call personally or review Jessica summary before next outreach.');
    if($res['ok']){$summary['actions']++;sb108('PATCH','hunter_queue?id=eq.'.rawurlencode($h['id']),['routed_to_action_queue'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('hunter_queue',$h['id'],'action','mark_action_queue',$res['ok'],'hunter follow-up signal',$h,$res);
    $results[]=['source'=>'hunter_queue','id'=>$h['id'],'route'=>'action','ok'=>$res['ok'],'reason'=>$res['reason']??''];
  }
}

/* 2. Future seller pipeline due soon into action/appointment */
$future=sb108('GET','future_seller_pipeline?select=*&status=eq.active&order=lead_score.desc&limit=300')['data'];
foreach($future as $f){
  if(!is_array($f))continue;
  $score=(int)($f['lead_score']??0);
  $due=!empty($f['next_followup_at']) && strtotime($f['next_followup_at'])<=strtotime('+7 days');
  $hot=($f['priority']??'')==='hot'||$score>=85;
  $appt=str_contains(strtolower((string)(($f['recommended_action']??'').' '.($f['jessica_summary']??'').' '.($f['notes']??''))),'appointment');

  if($appt && empty($f['routed_to_appointment'])){
    $res=create_appt108('future_seller_pipeline',$f,'Future seller has appointment-level signal.');
    if($res['ok']){$summary['appointments']++;sb108('PATCH','future_seller_pipeline?id=eq.'.rawurlencode($f['id']),['routed_to_appointment'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('future_seller_pipeline',$f['id'],'appointment','appointment_requests',$res['ok'],'future seller appointment signal',$f,$res);
  }

  if(($due||$hot) && empty($f['routed_to_action_queue'])){
    $res=create_action108('future_seller_pipeline',$f,'future_seller_followup',$due?'Future seller follow-up is due within 7 days.':'High-priority future seller requires review.');
    if($res['ok']){$summary['actions']++;sb108('PATCH','future_seller_pipeline?id=eq.'.rawurlencode($f['id']),['routed_to_action_queue'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('future_seller_pipeline',$f['id'],'action','mark_action_queue',$res['ok'],'future seller action signal',$f,$res);
  }
}

/* 3. Jessica priority BOOK_APPOINTMENT into appointment request */
$mission=sb108('GET','jessica_priority_queue?select=*&status=in.(pending,queued)&mission_type=in.(BOOK_APPOINTMENT,CALL_NOW,VALUATION_FOLLOWUP)&order=priority_score.desc&limit=300')['data'];
foreach($mission as $m){
  if(!is_array($m))continue;
  $score=(int)($m['priority_score']??0);
  $missionType=$m['mission_type']??'';
  $shouldAppt=$missionType==='BOOK_APPOINTMENT'||($score>=105 && $missionType==='CALL_NOW');
  if($shouldAppt && empty($m['routed_to_appointment'])){
    $res=create_appt108('jessica_priority_queue',$m,'Jessica priority queue indicates appointment-level urgency.');
    if($res['ok']){$summary['appointments']++;sb108('PATCH','jessica_priority_queue?id=eq.'.rawurlencode($m['id']),['routed_to_appointment'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('jessica_priority_queue',$m['id'],'appointment','appointment_requests',$res['ok'],'mission appointment signal',$m,$res);
  }

  if($score>=90 && empty($m['routed_to_action_queue'])){
    $res=create_action108('jessica_priority_queue',$m,'priority_lead_review','High-priority Jessica mission item requires Mark review.');
    if($res['ok']){$summary['actions']++;sb108('PATCH','jessica_priority_queue?id=eq.'.rawurlencode($m['id']),['routed_to_action_queue'=>true,'updated_at'=>date('c')]);}
    elseif(!empty($res['skipped']))$summary['skipped']++; else $summary['errors']++;
    log108('jessica_priority_queue',$m['id'],'action','mark_action_queue',$res['ok'],'mission action signal',$m,$res);
  }
}

echo json_encode([
  'success'=>$summary['errors']===0,
  'summary'=>$summary,
  'results'=>array_slice($results,0,100)
],JSON_PRETTY_PRINT);
?>