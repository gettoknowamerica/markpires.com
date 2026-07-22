<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function vd_cols(string $table):array{
 try{return gdb_all("SELECT column_name,data_type,is_nullable FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",[$table])?:[];}
 catch(Throwable $e){return [];}
}
try{
 $ready=gdb_one("SELECT s.*,m.title mission_title FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id WHERE s.status='ready' ORDER BY m.priority DESC,s.stage_no ASC LIMIT 1");
 echo json_encode([
  'ok'=>true,
  'version'=>'V112.1 Production Diagnostic',
  'php'=>PHP_VERSION,
  'gdb_enabled'=>function_exists('gdb_enabled')?gdb_enabled():null,
  'local_ai_tasks_columns'=>vd_cols('local_ai_tasks'),
  'v112_stage_columns'=>vd_cols('goliath_v112_stages'),
  'ready_stage'=>$ready,
  'last_insert_id_test_type'=>'gdb_insert commonly returns PDO lastInsertId as a numeric string; V112.1 casts it to int.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>