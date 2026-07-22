<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function p1163_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function p1163_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(p1163_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$mode=strtolower(trim((string)($_GET['mode']??'all')));
if(!in_array($mode,['all','voice','production'],true))$mode='all';

try{
 $cols=p1163_cols('local_ai_tasks');
 if(!$cols)throw new RuntimeException('local_ai_tasks is unavailable.');

 $lockName='goliath_v1163_pull_'.$mode;
 $lock=gdb_one("SELECT GET_LOCK(?,0) acquired",[$lockName]);
 if((int)($lock['acquired']??0)!==1){
  echo json_encode(['ok'=>true,'version'=>'V116.3 Split Pull','mode'=>$mode,'task'=>null,'status'=>'locked']);exit;
 }

 try{
  $productionActive=(int)(gdb_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE status IN ('queued','working')")['c']??0);

  $statusParts=[];
  if(isset($cols['status']))$statusParts[]="t.status='queued'";
  if(isset($cols['workflow_state']))$statusParts[]="t.workflow_state IN ('queued','dispatched')";
  $where=$statusParts?'('.implode(' OR ',$statusParts).')':'1=1';

  if(isset($cols['task_type'])){
   if($mode==='voice')$where.=" AND t.task_type='ask_goliath_live_v111'";
   elseif($mode==='production')$where.=" AND t.task_type IN ('goliath_v112_stage','goliath_v113_media_edit')";
   elseif($productionActive>0)$where.=" AND t.task_type IN ('ask_goliath_live_v111','goliath_v112_stage','goliath_v113_media_edit')";
  }

  $eligibility=isset($cols['task_type'])
   ? "(t.task_type<>'goliath_v112_stage' OR (
       s.id IS NOT NULL AND s.status='queued_local'
       AND s.stage_no=m.current_stage_no
       AND m.status IN ('queued','working')
      ))"
   : "1=1";

  $order=[];
  if(isset($cols['priority']))$order[]='t.priority DESC';
  if(isset($cols['created_at']))$order[]='t.created_at ASC';
  $order[]='t.id ASC';

  $task=gdb_one(
   "SELECT t.* FROM local_ai_tasks t
    LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id
    LEFT JOIN goliath_v112_missions m ON m.id=s.mission_id
    WHERE $where AND $eligibility
    ORDER BY ".implode(',',$order)." LIMIT 1"
  );

  if(!$task){
   echo json_encode([
    'ok'=>true,'version'=>'V116.3 Split Pull','mode'=>$mode,
    'task'=>null,'status'=>'idle','production_active'=>$productionActive
   ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
   exit;
  }

  $update=[];
  if(isset($cols['status']))$update['status']='working';
  if(isset($cols['workflow_state']))$update['workflow_state']='claimed';
  if(isset($cols['progress']))$update['progress']=5;
  if(isset($cols['claimed_by']))$update['claimed_by']=$mode==='voice'?'goliath-v1163-voice':'goliath-v1163-production';
  if(isset($cols['updated_at']))$update['updated_at']=gdb_now();
  if($update)gdb_update('local_ai_tasks',$update,'id=:id',['id'=>(int)$task['id']]);

  echo json_encode([
   'ok'=>true,'version'=>'V116.3 Split Pull','mode'=>$mode,
   'task'=>array_merge($task,$update),'production_active'=>$productionActive
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 }finally{
  try{gdb_one("SELECT RELEASE_LOCK(?) released",[$lockName]);}catch(Throwable $ignored){}
 }
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V116.3 Split Pull','mode'=>$mode,'error'=>'caught_exception',
  'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>