<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{ require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php'; require_once __DIR__.'/gbi-helpers.php';
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$job=gdb_one("SELECT * FROM goliath_browser_jobs WHERE status='queued' ORDER BY priority DESC,id ASC LIMIT 1");
if(!$job){echo json_encode(['ok'=>true,'success'=>true,'job'=>null,'message'=>'No browser intelligence jobs queued.']);exit;}
gbi_update('goliath_browser_jobs',(int)$job['id'],['status'=>'working','progress'=>5,'current_step'=>'Pulled by local OpenClaw bridge','locked_at'=>gdb_now(),'updated_at'=>gdb_now()]);
gbi_heartbeat($job['executive_key']?:'browser',['status'=>'working','current_job_id'=>(int)$job['id'],'current_task_id'=>(int)($job['task_id']??0),'current_commission_id'=>(int)($job['commission_id']??0),'current_step'=>'Browser job pulled','progress'=>5,'browser_status'=>'starting']);
gbi_event((int)$job['id'],$job['executive_key']?:'browser','pulled','Browser job pulled','Local bridge accepted job.');
$job=gdb_one("SELECT * FROM goliath_browser_jobs WHERE id=?",[(int)$job['id']]);
echo json_encode(['ok'=>true,'success'=>true,'version'=>'V94 Browser Job Pull','job'=>$job,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>