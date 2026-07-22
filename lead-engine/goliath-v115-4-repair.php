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
 $archived=0;
 // Archive duplicate stage tasks that are not the task currently linked by a stage.
 $stmt=gdb()->prepare(
  "UPDATE local_ai_tasks t
   LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id
   SET t.status='archived',t.updated_at=NOW()
   WHERE t.task_type='goliath_v112_stage'
   AND t.status IN ('queued','working')
   AND s.id IS NULL"
 );
 $stmt->execute();
 $archived=$stmt->rowCount();

 // Restore stages whose linked tasks were archived/failed or vanished.
 $restored=0;
 $stages=gdb_all(
  "SELECT s.id,s.local_task_id,t.id task_exists,t.status task_status
   FROM goliath_v112_stages s
   LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
   WHERE s.status IN ('dispatching','queued_local','working')"
 )?:[];
 foreach($stages as $s){
  if(!$s['task_exists']||in_array(strtolower((string)$s['task_status']),['archived','failed','error'],true)){
   gdb_update('goliath_v112_stages',[
    'status'=>'ready','local_task_id'=>null,
    'last_error'=>'V115.4 restored stage after orphaned or failed task',
    'updated_at'=>gdb_now()
   ],'id=:id',['id'=>(int)$s['id']]);
   $restored++;
  }
 }

 $active=gdb_all(
  "SELECT m.id mission_id,m.title,m.current_stage_no,s.id stage_id,s.executive_key,s.status,s.local_task_id,t.status task_status
   FROM goliath_v112_missions m
   JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
   WHERE m.status IN ('queued','working')
   ORDER BY m.priority DESC,m.id"
 )?:[];

 echo json_encode([
  'ok'=>true,
  'version'=>'V115.4 Verified Orchestration Repair',
  'orphan_tasks_archived'=>$archived,
  'stages_restored'=>$restored,
  'active_current_stages'=>$active,
  'next'=>'Restart F:\\GoliathOmni\\start-goliath-v115.bat',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>