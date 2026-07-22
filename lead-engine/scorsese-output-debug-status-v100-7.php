<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $jobs=gdb_all("SELECT id,title,status,progress,remote_prompt_id,output_url,output_path,error_message,updated_at,completed_at FROM scorsese_comfy_jobs ORDER BY id DESC LIMIT 30")?:[];
 $counts=gdb_one("SELECT COUNT(*) total, SUM(status='queued') queued, SUM(status IN ('rendering','working')) rendering, SUM(status IN ('failed','error')) failed, SUM(status IN ('complete','completed','ready')) complete, SUM(COALESCE(output_url,'')<>'') with_output FROM scorsese_comfy_jobs")?:[];
 echo json_encode(['ok'=>true,'version'=>'V100.7 Scorsese Output Debug Status','counts'=>$counts,'recent_jobs'=>$jobs,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>