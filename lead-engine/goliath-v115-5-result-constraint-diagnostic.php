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
 $checks=gdb_all(
  "SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE
   FROM information_schema.TABLE_CONSTRAINTS tc
   JOIN information_schema.CHECK_CONSTRAINTS cc
    ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA
   AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
   WHERE tc.TABLE_SCHEMA=DATABASE()
   AND tc.TABLE_NAME='local_ai_tasks'
   AND tc.CONSTRAINT_TYPE='CHECK'"
 )?:[];
 echo json_encode([
  'ok'=>true,
  'version'=>'V115.5 Result Constraint Diagnostic',
  'checks'=>$checks,
  'explanation'=>'MariaDB JSON columns appear as LONGTEXT and enforce JSON_VALID through a CHECK constraint.',
  'fix'=>'Completion endpoint now stores a JSON envelope containing content_text/content_html instead of raw text.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>