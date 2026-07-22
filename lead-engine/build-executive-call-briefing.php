<?php
/**
 * V12.18 Executive Call Briefing
 * Upload: /public_html/lead-engine/build-executive-call-briefing.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb182($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }

  $calls=sb182('GET','executive_call_inbox?select=*&status=eq.new&order=call_date.desc&limit=100')['data'];
  $new=count($calls); $urgent=0; $callbacks=0; $leads=0; $appts=0;
  foreach($calls as $c){
    if(($c['urgency']??'')==='urgent') $urgent++;
    if(!empty($c['callback_needed'])) $callbacks++;
    if(!empty($c['lead_related'])) $leads++;
    if(!empty($c['appointment_requested'])) $appts++;
  }

  $brief="Jessica Executive Inbox — ".date('Y-m-d')."\\n\\n";
  $brief.="New forwarded calls: {$new}\\n";
  $brief.="Urgent: {$urgent}\\n";
  $brief.="Callbacks needed: {$callbacks}\\n";
  $brief.="Lead-related: {$leads}\\n";
  $brief.="Appointment requests: {$appts}\\n\\n";
  $brief.="Top calls:\\n";
  foreach(array_slice($calls,0,15) as $i=>$c){
    $brief.=($i+1).". ".($c['caller_name']?:$c['caller_phone'])." — ".($c['caller_category']??'unknown')." — ".($c['urgency']??'normal')." — ".($c['recommended_action']??'Review')."\\n";
  }

  $payload=[[
    'briefing_date'=>date('Y-m-d'),
    'new_calls'=>$new,
    'urgent_calls'=>$urgent,
    'callbacks_needed'=>$callbacks,
    'lead_related_calls'=>$leads,
    'appointment_requests'=>$appts,
    'top_calls'=>array_slice($calls,0,25),
    'briefing_text'=>$brief,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb182('POST','executive_call_briefings',$payload);
  if(!$res['ok'] && str_contains($res['body'],'duplicate key')){
    $res=sb182('PATCH','executive_call_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$payload[0]);
  }

  echo json_encode(['success'=>$res['ok'],'briefing'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>