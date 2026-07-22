<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
if(!defined('GOOGLE_CALENDAR_BRIDGE_URL')||!GOOGLE_CALENDAR_BRIDGE_URL||!defined('GOOGLE_CALENDAR_BRIDGE_SECRET')||!GOOGLE_CALENDAR_BRIDGE_SECRET){echo json_encode(['success'=>false,'error'=>'Google Calendar bridge not configured in config.php']);exit;}
$params=['secret'=>GOOGLE_CALENDAR_BRIDGE_SECRET,'action'=>'availability','days'=>$_GET['days']??7,'duration_minutes'=>$_GET['duration_minutes']??45,'buffer_minutes'=>$_GET['buffer_minutes']??30,'max_slots'=>$_GET['max_slots']??6];
$ch=curl_init(GOOGLE_CALENDAR_BRIDGE_URL.'?'.http_build_query($params));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>25]);
$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
$data=json_decode($body,true);
echo json_encode(['success'=>$http>=200&&$http<300&&is_array($data)&&!empty($data['success']),'http'=>$http,'error'=>$err,'data'=>is_array($data)?$data:null,'raw'=>is_array($data)?null:$body],JSON_PRETTY_PRINT);
?>