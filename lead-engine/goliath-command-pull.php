<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_commands?select=*&status=eq.queued&order=priority.desc,created_at.asc&limit=1');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
$data=json_decode($body,true) ?: [];
echo json_encode(['success'=>$http>=200&&$http<300,'command'=>$data[0]??null],JSON_PRETTY_PRINT);
?>