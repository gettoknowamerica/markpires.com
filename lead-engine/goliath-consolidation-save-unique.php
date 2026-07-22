<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$in=json_decode(file_get_contents('php://input'),true);
$rows=$in['rows']??[];
if(!is_array($rows) || !$rows){
  echo json_encode(['success'=>true,'inserted'=>0,'message'=>'No rows supplied']); exit;
}

$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/jessica_opportunity_engine');
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_CUSTOMREQUEST=>'POST',
  CURLOPT_HTTPHEADER=>[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ],
  CURLOPT_POSTFIELDS=>json_encode($rows),
  CURLOPT_TIMEOUT=>90
]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
$data=json_decode($body,true);
echo json_encode(['success'=>$http>=200&&$http<300,'http'=>$http,'inserted'=>$http>=200&&$http<300?count($rows):0,'error'=>$err,'details'=>is_array($data)?$data:$body],JSON_PRETTY_PRINT);
?>