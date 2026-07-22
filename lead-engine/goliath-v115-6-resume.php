<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $lock=gdb_one("SELECT GET_LOCK('goliath_v1156_resume',0) acquired");
 if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'version'=>'V115.6 Resume','status'=>'locked']);exit;}

 $requeued=0;$restored=0;$orphans=0;$completeWaiting=0;
 try{
  // Archive stage tasks that are not linked to any stage.
  $stmt=gdb()->prepare(
   "UPDATE local_ai_tasks t
    LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id
    SET t.status='archived',t.updated_at=NOW()
    WHERE t.task_type='goliath_v112_stage'
      AND t.status IN ('queued','working')
      AND s.id IS NULL"
  );
  $stmt->execute();$orphans=$stmt->rowCount();

  $rows=gdb_all(
   "SELECT m.id mission_id,m.current_stage_no,m.status mission_status,
           s.id stage_id,s.status stage_status,s.local_task_id,
           t.id task_id,t.status task_status,t.result,t.updated_at task_updated
    FROM goliath_v112_missions m
    JOIN goliath_v112_stages s
      ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
    LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
    WHERE m.status IN ('queued','working')
    ORDER BY m.priority DESC,m.id"
  )?:[];

  foreach($rows as $r){
   $stageId=(int)$r['stage_id'];
   $taskId=(int)($r['task_id']??0);
   $taskStatus=strtolower((string)($r['task_status']??''));
   $hasResult=trim((string)($r['result']??''))!=='';

   if(!$taskId || in_array($taskStatus,['failed','error','archived'],true)){
    gdb_update('goliath_v112_stages',[
     'status'=>'ready','local_task_id'=>null,
     'last_error'=>'V115.6 restored missing/failed task','updated_at'=>gdb_now()
    ],'id=:id',['id'=>$stageId]);
    $restored++;
    continue;
   }

   if(in_array($taskStatus,['complete','completed','done','success'],true) && $hasResult){
    // Leave it complete so the engine can consume and advance it.
    gdb_update('goliath_v112_stages',[
     'status'=>'queued_local','updated_at'=>gdb_now()
    ],'id=:id',['id'=>$stageId]);
    $completeWaiting++;
    continue;
   }

   if($taskStatus==='working' && !$hasResult){
    // A prior runtime claimed it but failed before saving. Make it pullable again.
    gdb_update('local_ai_tasks',[
     'status'=>'queued','progress'=>0,'updated_at'=>gdb_now()
    ],'id=:id',['id'=>$taskId]);
    gdb_update('goliath_v112_stages',[
     'status'=>'queued_local','last_error'=>'V115.6 requeued interrupted local task','updated_at'=>gdb_now()
    ],'id=:id',['id'=>$stageId]);
    $requeued++;
   }
  }

  $current=gdb_all(
   "SELECT m.id mission_id,m.title,m.current_stage_no,s.executive_key,s.status stage_status,
           s.local_task_id,t.status task_status,LENGTH(t.result) result_chars
    FROM goliath_v112_missions m
    JOIN goliath_v112_stages s
      ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
    LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
    WHERE m.status IN ('queued','working')
    ORDER BY m.priority DESC,m.id"
  )?:[];

  echo json_encode([
   'ok'=>true,
   'version'=>'V115.6 Resume + Engine Loop Fix',
   'orphan_tasks_archived'=>$orphans,
   'interrupted_tasks_requeued'=>$requeued,
   'stages_restored'=>$restored,
   'completed_tasks_waiting_for_engine'=>$completeWaiting,
   'current_stages'=>$current,
   'next'=>'Restart F:\\GoliathOmni\\start-goliath-v115.bat. The runtime now calls the engine continuously before polling.',
   'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 }finally{
  try{gdb_one("SELECT RELEASE_LOCK('goliath_v1156_resume') released");}catch(Throwable $ignored){}
 }
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>