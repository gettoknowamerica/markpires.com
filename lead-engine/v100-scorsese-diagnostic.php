<?php
/**
 * V100.1 Scorsese Queue Diagnostic
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 $counts=gdb_one("SELECT
   COUNT(*) total,
   SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) queued,
   SUM(CASE WHEN status IN ('working','rendering') THEN 1 ELSE 0 END) working,
   SUM(CASE WHEN status IN ('failed','error') THEN 1 ELSE 0 END) failed,
   SUM(CASE WHEN status IN ('complete','completed','ready') THEN 1 ELSE 0 END) complete,
   SUM(CASE WHEN workflow_json IS NULL OR workflow_json='' OR workflow_json='null' OR workflow_json='[]' OR workflow_json='{}' THEN 1 ELSE 0 END) bad_workflow
 FROM scorsese_comfy_jobs")?:[];

 $recent=gdb_all("SELECT id,title,status,progress,
   CASE WHEN workflow_json IS NULL THEN 'NULL'
        WHEN workflow_json='' THEN 'EMPTY'
        ELSE LEFT(workflow_json,80)
   END workflow_preview,
   error_message,updated_at
 FROM scorsese_comfy_jobs
 ORDER BY id DESC
 LIMIT 25")?:[];

 echo json_encode(['ok'=>true,'version'=>'V100.1 Scorsese Queue Diagnostic','counts'=>$counts,'recent'=>$recent,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.1 Scorsese Queue Diagnostic','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>