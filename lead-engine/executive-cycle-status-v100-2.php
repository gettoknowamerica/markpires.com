<?php
/**
 * V100.2 Executive Cycle Status
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function c102($sql){try{return gdb_one($sql)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
 function a102($sql){try{return gdb_all($sql)?:[];}catch(Throwable $e){return [['error'=>$e->getMessage()]];}}

 $status=[
  'executive_tasks'=>c102("SELECT COUNT(*) total, SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) queued, SUM(CASE WHEN status IN ('working','in_progress') THEN 1 ELSE 0 END) working FROM executive_tasks"),
  'collaboration_tasks'=>c102("SELECT COUNT(*) total, SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) queued, SUM(CASE WHEN status='working' THEN 1 ELSE 0 END) working FROM executive_collaboration_tasks"),
  'packages'=>c102("SELECT COUNT(*) total, SUM(CASE WHEN status IN ('assembling','ready_for_review') THEN 1 ELSE 0 END) active FROM production_packages"),
  'scorsese'=>c102("SELECT COUNT(*) total, SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) queued, SUM(CASE WHEN status IN ('working','rendering') THEN 1 ELSE 0 END) rendering, SUM(CASE WHEN status IN ('failed','error') THEN 1 ELSE 0 END) failed FROM scorsese_comfy_jobs"),
  'browser_jobs'=>c102("SELECT COUNT(*) total, SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) queued, SUM(CASE WHEN status='working' THEN 1 ELSE 0 END) working FROM goliath_browser_jobs"),
  'recent_cycles'=>a102("SELECT cycle_type,status,summary,created_at FROM executive_cycle_heartbeats ORDER BY id DESC LIMIT 10")
 ];

 echo json_encode(['ok'=>true,'version'=>'V100.2 Executive Cycle Status','status'=>$status,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>