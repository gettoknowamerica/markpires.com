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
 $cols=gdb_all("SELECT column_name,column_type,data_type,is_nullable,column_default
                FROM information_schema.columns
                WHERE table_schema=DATABASE() AND table_name='local_ai_tasks'
                ORDER BY ordinal_position")?:[];
 $tasks=gdb_all("SELECT id,task_uid,agent,task_type,status,progress,LENGTH(result) result_chars,created_at,updated_at
                 FROM local_ai_tasks ORDER BY id DESC LIMIT 10")?:[];
 echo json_encode(['ok'=>true,'version'=>'V115.3 Completion Diagnostic','columns'=>$cols,'latest_tasks'=>$tasks,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>