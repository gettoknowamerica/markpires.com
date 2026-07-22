<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function v113_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function v113_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[$r['column_name']]=true;return $out;
}
$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(v113_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $cols=v113_cols('local_ai_tasks');
 if(!$cols)throw new RuntimeException('local_ai_tasks is unavailable.');

 $lock=gdb_one("SELECT GET_LOCK('goliath_v113_local_pull',0) acquired");
 if((int)($lock['acquired']??0)!==1){
  echo json_encode(['ok'=>true,'version'=>'V113.0 Local Pull','task'=>null,'status'=>'locked']);
  exit;
 }

 try{
  $conditions=[];
  if(isset($cols['status']))$conditions[]="status='queued'";
  if(isset($cols['workflow_state']))$conditions[]="workflow_state IN ('queued','dispatched')";
  $where=$conditions?'WHERE ('.implode(' OR ',$conditions).')':'';

  $priorityOrder=[];
  if(isset($cols['task_type'])){
   $priorityOrder[]="CASE WHEN task_type='goliath_v112_stage' THEN 0 WHEN task_type='ask_goliath_live_v111' THEN 1 ELSE 2 END";
  }
  if(isset($cols['priority']))$priorityOrder[]='priority DESC';
  if(isset($cols['created_at']))$priorityOrder[]='created_at ASC';
  $priorityOrder[]='id ASC';

  $task=gdb_one("SELECT * FROM local_ai_tasks $where ORDER BY ".implode(',',$priorityOrder)." LIMIT 1");
  if(!$task){
   echo json_encode(['ok'=>true,'version'=>'V113.0 Local Pull','task'=>null,'status'=>'idle']);
   exit;
  }

  $update=[];
  if(isset($cols['status']))$update['status']='working';
  if(isset($cols['workflow_state']))$update['workflow_state']='claimed';
  if(isset($cols['progress']))$update['progress']=5;
  if(isset($cols['claimed_by']))$update['claimed_by']='goliath-v113-runtime';
  if(isset($cols['updated_at']))$update['updated_at']=gdb_now();
  if($update)gdb_update('local_ai_tasks',$update,'id=:id',['id'=>(int)$task['id']]);

  $task=array_merge($task,$update);
  echo json_encode(['ok'=>true,'version'=>'V113.0 Local Pull','task'=>$task],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 }finally{
  try{gdb_one("SELECT RELEASE_LOCK('goliath_v113_local_pull') released");}catch(Throwable $ignored){}
 }
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V113.0 Local Pull','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>