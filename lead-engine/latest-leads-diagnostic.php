<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$limit=max(1,min(50,(int)($_GET['limit']??15)));
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.SUPABASE_LEADS_TABLE.'?select=*&order=created_at.desc&limit='.$limit);
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HTTPHEADER=>[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ],
  CURLOPT_TIMEOUT=>30
]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
$data=json_decode($body,true);

echo json_encode([
  'success'=>$http>=200&&$http<300,
  'http'=>$http,
  'curl_error'=>$err,
  'count'=>is_array($data)?count($data):0,
  'leads'=>is_array($data)?$data:$body
],JSON_PRETTY_PRINT);
?>