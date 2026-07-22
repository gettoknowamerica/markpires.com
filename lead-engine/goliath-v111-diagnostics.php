<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function d111_cols($t){try{return gdb_all("SELECT column_name,data_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",[$t])?:[];}catch(Throwable $e){return [];}}
$latest=[];try{$latest=gdb_all("SELECT * FROM local_ai_tasks ORDER BY id DESC LIMIT 5")?:[];}catch(Throwable $e){}
foreach($latest as &$r){if(isset($r['prompt']))$r['prompt']=mb_substr((string)$r['prompt'],0,200);if(isset($r['result']))$r['result']=mb_substr((string)$r['result'],0,200);}unset($r);
echo json_encode(['ok'=>true,'version'=>'V111.1 Diagnostics','db_enabled'=>gdb_enabled(),'tables'=>['local_ai_tasks'=>d111_cols('local_ai_tasks'),'goliath_conversations_v111'=>d111_cols('goliath_conversations_v111'),'goliath_messages_v111'=>d111_cols('goliath_messages_v111')],'latest_tasks'=>$latest,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>