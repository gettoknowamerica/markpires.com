<?php
/**
 * V11.8 Process Confirmed Appointments Into Google Calendar
 * /lead-engine/process-calendar-appointments.php?key=YOUR_KEY&limit=10
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb118p($endpoint){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPGET=>true,
    CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],
    CURLOPT_TIMEOUT=>25
  ]);
  $body=curl_exec($ch);curl_close($ch);$d=json_decode($body,true);
  return is_array($d)?$d:[];
}

$limit=max(1,min(25,(int)($_GET['limit']??10)));
$rows=sb118p('appointment_requests?select=id&status=in.(confirmed,mark_confirmed)&calendar_status=in.(not_created,error)&confirmed_start=not.is.null&confirmed_end=not.is.null&order=confirmed_start.asc&limit='.$limit);

$results=[];
$host=$_SERVER['HTTP_HOST']??'markpires.com';
foreach($rows as $row){
  $id=$row['id']??'';
  if(!$id)continue;
  $url='https://'.$host.'/lead-engine/calendar-create-appointment.php?key='.rawurlencode(AFTER_HOURS_CRON_KEY).'&id='.rawurlencode($id);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>45]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $data=json_decode($body,true);
  $results[]=['id'=>$id,'http'=>$http,'response'=>is_array($data)?$data:$body];
}

echo json_encode(['success'=>true,'processed'=>count($results),'results'=>$results],JSON_PRETTY_PRINT);
?>