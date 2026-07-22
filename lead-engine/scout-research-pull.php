<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/scout_research_queue?select=*&status=eq.queued&order=priority.desc,created_at.asc&limit=1');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
$b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true);
$item=is_array($d)&&count($d)?$d[0]:null;
if($item){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/scout_research_queue?id=eq.'.rawurlencode($item['id']));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_POSTFIELDS=>json_encode(['status'=>'running','updated_at'=>date('c')]),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  curl_exec($ch); curl_close($ch);
}
echo json_encode(['success'=>true,'assignment'=>$item],JSON_PRETTY_PRINT);
?>