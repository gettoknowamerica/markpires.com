<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$id=$data['id'] ?? '';
if(!$id){echo json_encode(['success'=>false,'error'=>'Missing id']); exit;}
$status=$data['status'] ?? 'running';
$row=['status'=>$status];
if($status==='running')$row['started_at']=date('c');
if(in_array($status,['done','failed','complete']))$row['completed_at']=date('c');
if(isset($data['result']))$row['result']=$data['result'];
if(isset($data['error']))$row['error']=$data['error'];

$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_commands?id=eq.'.rawurlencode($id));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_POSTFIELDS=>json_encode($row),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>20]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
echo json_encode(['success'=>$http>=200&&$http<300,'http'=>$http,'body'=>json_decode($body,true)?:$body],JSON_PRETTY_PRINT);
?>