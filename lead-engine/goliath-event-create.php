<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key']??$_GET['key']??'';
if(defined('AFTER_HOURS_CRON_KEY') && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$row=['department'=>$data['department']??'Goliath','event_type'=>$data['event_type']??'info','title'=>$data['title']??'Goliath activity','detail'=>$data['detail']??'','roi_estimate'=>(float)($data['roi_estimate']??0),'confidence'=>(int)($data['confidence']??80),'status'=>$data['status']??'new','link_url'=>$data['link_url']??'/dashboard/goliath-mission-control.php','metadata'=>$data['metadata']??new stdClass()];
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_events');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode([$row]),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>20]);
$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
echo json_encode(['success'=>$h>=200&&$h<300,'http'=>$h,'body'=>json_decode($b,true)?:$b],JSON_PRETTY_PRINT);
?>