<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$in=json_decode(file_get_contents('php://input'),true)?:$_POST; $key=$in['key']??($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'bad_key']);exit;}
$msg=trim((string)($in['message']??'')); if($msg===''){echo json_encode(['success'=>false,'error'=>'missing_message']);exit;}
function ag_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
if(!gdb_enabled()){echo json_encode(['success'=>false,'error'=>'Goliath MySQL not configured']);exit;}
$prompt="You are Goliath, Mark Pires' local executive operating system. Speak conversationally, warmly, and directly to Mark. If Mark says 'Hey Goliath, I have a question,' answer like: 'Yeah Mark, what can I answer?' Use useful, concise answers unless he asks for depth.\n\nMark asked:\n".$msg;
$id=null;
if(ag_table('local_ai_tasks')){
  $id=gdb_insert('local_ai_tasks',['task_type'=>'ask_goliath_chat','model'=>'llama3.1:8b','prompt'=>$prompt,'status'=>'queued','priority'=>99,'agent'=>'Goliath','title'=>'Ask Goliath Conversation','metadata'=>gdb_json(['source'=>'ask_goliath_v73','message'=>$msg])]);
}
if(!$id && ag_table('executive_commissions')){
  $id=gdb_insert('executive_commissions',['commission_uid'=>gdb_uid('com'),'executive_key'=>'goliath','title'=>'Ask Goliath Conversation','commission_type'=>'ask_goliath_chat','status'=>'queued','priority'=>99,'progress'=>0,'current_task'=>$msg,'prompt'=>$prompt,'metadata'=>gdb_json(['source'=>'ask_goliath_v73','message'=>$msg])]);
}
echo json_encode(['success'=>true,'task_id'=>$id,'message'=>'Queued for local Goliath LLM worker.']);
?>
