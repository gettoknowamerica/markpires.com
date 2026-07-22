<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$limit=max(5,min(80,(int)($_GET['limit']??50)));
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_events?select=*&order=created_at.desc&limit='.$limit);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
echo json_encode(['success'=>$h>=200&&$h<300,'events'=>json_decode($b,true)?:[]],JSON_PRETTY_PRINT);
?>