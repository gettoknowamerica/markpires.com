<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$limit=max(1,min(10,(int)($_GET['limit']??2)));
function sb($method,$ep,$body=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35]);
  if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $data=json_decode($raw,true);return ['ok'=>$http>=200&&$http<300,'http'=>$http,'data'=>is_array($data)?$data:[],'raw'=>$raw,'error'=>$err];
}
$q='local_ai_tasks?select=*&task_type=eq.v55_deliverable_commission&status=eq.queued&order=priority.desc,created_at.asc&limit='.$limit;
$found=sb('GET',$q);
$tasks=[];
foreach(($found['data']??[]) as $t){
  $id=$t['id']??''; if(!$id)continue;
  $u=sb('PATCH','local_ai_tasks?id=eq.'.rawurlencode($id),['status'=>'running','updated_at'=>date('c')]);
  if($u['ok'] && !empty($u['data'][0])) $tasks[]=$u['data'][0];
}
echo json_encode(['success'=>true,'version'=>'55.2','claimed_count'=>count($tasks),'tasks'=>$tasks],JSON_PRETTY_PRINT);
