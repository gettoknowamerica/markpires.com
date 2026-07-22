<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php'; header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{gdb_exec("UPDATE executive_commissions SET status='blocked_legacy', current_step='Archived by V79.1 to stop repeated Production Mission loop', updated_at=NOW() WHERE status IN ('queued','claimed','in_progress','processing','working','review','ready_for_review') AND title LIKE 'Production Mission:%'");$n=(int)((gdb_one("SELECT ROW_COUNT() c")?:['c'=>0])['c']);}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);exit;}
echo json_encode(['ok'=>true,'version'=>'V79.1 Unblock Legacy Production Missions','blocked_commissions'=>$n,'message'=>'Worker should now pull real local_ai_tasks such as Scout 730+ and V78 mission assignments.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>