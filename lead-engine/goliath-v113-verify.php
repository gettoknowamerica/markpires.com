<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$checks=[];
foreach(['local_ai_tasks','goliath_v112_missions','goliath_v112_stages','goliath_v112_artifacts','goliath_v112_events'] as $t){
 $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);
 $checks[$t]=(int)($r['c']??0)>0;
}
$cols=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='local_ai_tasks'")?:[];
$names=array_column($cols,'column_name');
echo json_encode([
 'ok'=>!in_array(false,$checks,true),
 'version'=>'V113.0 Full OS Verify',
 'tables'=>$checks,
 'local_ai_tasks_required'=>[
  'task_uid'=>in_array('task_uid',$names,true),
  'prompt'=>in_array('prompt',$names,true),
  'status'=>in_array('status',$names,true),
  'result_or_output'=>in_array('result',$names,true)||in_array('output',$names,true)||in_array('response',$names,true)
 ],
 'next'=>'Open goliath-v113-production-engine.php, then start F:\\GoliathOmni\\start-goliath-v113.bat',
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>