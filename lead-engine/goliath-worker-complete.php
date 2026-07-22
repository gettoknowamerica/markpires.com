<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$id=$data['id']??''; if(!$id){echo json_encode(['success'=>false,'error'=>'Missing id']); exit;}
$agent=$data['agent']??'Goliath'; $status=$data['status']??'done';
function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS=>json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
$patch=['status'=>$status,'progress'=>($status==='done'?100:0),'current_phase'=>($status==='done'?'complete':'failed'),'result'=>$data['result']??null,'error'=>$data['error']??null,'ready_url'=>$data['ready_url']??null,'updated_at'=>date('c'),'last_heartbeat_at'=>date('c')];
[$http,$res]=sb('PATCH','local_ai_tasks',$patch,'?id=eq.'.rawurlencode($id));
$event=['department'=>$agent,'event_type'=>'executive_task_'.$status,'title'=>$agent.($status==='done'?' completed work':' hit a blocker'),'detail'=>$data['summary']??($data['error']??'Work updated'),'confidence'=>($status==='done'?96:45),'status'=>$status,'phase'=>$patch['current_phase'],'progress'=>$patch['progress'],'link_url'=>$data['ready_url']??null,'metadata'=>['task_id'=>$id,'ready_url'=>$data['ready_url']??null]];
sb('POST','goliath_events',[$event]);
// optional notification hook via existing Resend config can be added later; event is now universal.
echo json_encode(['success'=>$http>=200&&$http<300,'updated'=>$res],JSON_PRETTY_PRINT);
