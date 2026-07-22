<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key = $_GET['key'] ?? ($_POST['key'] ?? '');
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$agent = trim($_GET['agent'] ?? ($_POST['agent'] ?? ''));
$worker = trim($_GET['worker'] ?? ($_POST['worker'] ?? 'local-worker'));
if(!$agent){ echo json_encode(['success'=>false,'error'=>'Missing agent']); exit; }
function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
$agentJson = json_encode(['agent'=>$agent]);
$query='?select=*&status=eq.queued&or=(metadata->>agent.eq.'.rawurlencode($agent).',prompt.ilike.*'.rawurlencode($agent).'*)&order=priority.desc,created_at.asc&limit=1';
[$http,$rows]=sb('GET','local_ai_tasks',null,$query);
$item=is_array($rows)&&isset($rows[0])?$rows[0]:null;
if(!$item){ echo json_encode(['success'=>true,'claimed'=>false,'agent'=>$agent,'message'=>'No queued task found']); exit; }
$id=$item['id'];
$patch=['status'=>'running','claimed_by'=>$worker,'progress'=>5,'current_phase'=>'claimed','last_heartbeat_at'=>date('c'),'updated_at'=>date('c')];
[$ph,$updated]=sb('PATCH','local_ai_tasks',$patch,'?id=eq.'.rawurlencode($id).'&status=eq.queued');
$claimed=is_array($updated)&&isset($updated[0])?$updated[0]:null;
if(!$claimed){ echo json_encode(['success'=>true,'claimed'=>false,'agent'=>$agent,'message'=>'Task was claimed by another worker','candidate'=>$item]); exit; }
$event=['department'=>$agent,'event_type'=>'executive_task_claimed','title'=>$agent.' started work','detail'=>substr($claimed['prompt']??'',0,220),'confidence'=>90,'status'=>'running','phase'=>'claimed','progress'=>5,'metadata'=>['task_id'=>$id,'worker'=>$worker,'task_type'=>$claimed['task_type']??null]];
sb('POST','goliath_events',[$event]);
echo json_encode(['success'=>true,'claimed'=>true,'task'=>$claimed],JSON_PRETTY_PRINT);
