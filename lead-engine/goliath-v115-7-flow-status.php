<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $missions=gdb_all(
  "SELECT m.id,m.mission_uid,m.title,m.originator_key,m.status,m.current_stage_no,
          s.id stage_id,s.stage_no,s.executive_key,s.stage_key,s.title stage_title,
          s.status stage_status,s.local_task_id,s.attempt_count,s.last_error,
          t.status task_status,LENGTH(t.result) result_chars,
          (SELECT COUNT(*) FROM goliath_v112_artifacts a WHERE a.mission_id=m.id AND a.is_tangible=1) tangible_artifacts,
          (SELECT COUNT(*) FROM goliath_v112_stages sx WHERE sx.mission_id=m.id) total_stages
   FROM goliath_v112_missions m
   LEFT JOIN goliath_v112_stages s
     ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
   WHERE m.status IN ('queued','working','delivered')
   ORDER BY CASE WHEN m.status='delivered' THEN 1 ELSE 0 END,m.priority DESC,m.id"
 )?:[];

 $byExecutive=gdb_all(
  "SELECT s.executive_key,
          SUM(s.status='ready') ready,
          SUM(s.status IN ('queued_local','working','dispatching')) active,
          SUM(s.status='complete') completed_stages
   FROM goliath_v112_stages s
   JOIN goliath_v112_missions m ON m.id=s.mission_id
   WHERE m.status IN ('queued','working')
   GROUP BY s.executive_key
   ORDER BY s.executive_key"
 )?:[];

 $ask=gdb_all(
  "SELECT id,task_uid,status,created_at,updated_at,LENGTH(result) result_chars
   FROM local_ai_tasks
   WHERE task_type='ask_goliath_live_v111'
   ORDER BY id DESC LIMIT 10"
 )?:[];

 echo json_encode([
  'ok'=>true,
  'version'=>'V115.7 Organization Flow Status',
  'missions'=>$missions,
  'executives'=>$byExecutive,
  'ask_goliath'=>$ask,
  'interpretation'=>[
   'working'=>'A task is claimed by the local runtime.',
   'queued_local'=>'The stage has a local task waiting to be claimed.',
   'complete'=>'The stage created a result and should advance.',
   'waiting'=>'The stage is correctly waiting for the prior station.',
   'tangible_artifacts'=>'Proof that actual shared work has been accepted, not merely queued.'
  ],
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>