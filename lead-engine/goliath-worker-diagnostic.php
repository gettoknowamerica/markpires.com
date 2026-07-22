<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php'; header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function dt($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function cols($t){try{return gdb_all("SELECT column_name,data_type,is_nullable,column_default FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",[$t]);}catch(Throwable $e){return [];} }
$out=['ok'=>true,'version'=>'V79.1 Worker Diagnostic','tables'=>[],'next_tasks'=>[]];
foreach(['local_ai_tasks','executive_commissions','goliath_worker_completions','goliath_deliverables','goliath_review_queue'] as $t)$out['tables'][$t]=['exists'=>dt($t),'columns'=>cols($t)];
if(dt('local_ai_tasks'))$out['next_tasks']=gdb_all("SELECT id,commission_id,agent,task_type,status,priority,progress,updated_at FROM local_ai_tasks WHERE status IN ('queued','working') ORDER BY priority DESC, updated_at ASC LIMIT 20");
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>