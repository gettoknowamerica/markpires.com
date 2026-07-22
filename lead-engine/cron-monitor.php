<?php
/**
 * V12.2 Cron Monitor
 * Upload: /public_html/lead-engine/cron-monitor.php
 *
 * Run:
 * /lead-engine/cron-monitor.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb122m($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}

$jobs=sb122m('GET','cron_schedule_registry?select=*&status=eq.active&limit=100')['data'];
$out=[];
foreach($jobs as $j){
  $out[]=[
    'job_name'=>$j['job_name']??'',
    'recommended_time'=>$j['recommended_time']??'',
    'frequency'=>$j['frequency']??'',
    'url'=>$j['job_url']??'',
    'notes'=>$j['notes']??''
  ];
}
echo json_encode(['success'=>true,'jobs'=>$out,'note'=>'Hostinger cron jobs do not need end times. Each entry simply runs at its scheduled time/frequency.'],JSON_PRETTY_PRINT);
?>