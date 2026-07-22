<?php
/**
 * V96.1 Executive Boot Endpoint
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  require_once __DIR__.'/executive-kernel-v96.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  $exec=$_GET['exec']??'scout';
  $mission=[
    'mission_type'=>$_GET['mission_type']??'boot_test',
    'title'=>$_GET['title']??'Executive boot verification',
    'commission_id'=>(int)($_GET['commission_id']??0)?:null,
    'task_id'=>(int)($_GET['task_id']??0)?:null,
    'dossier_id'=>(int)($_GET['dossier_id']??0)?:null
  ];
  $boot=gx96_boot($exec,$mission);

  echo json_encode([
    'ok'=>true,
    'version'=>'V96.1 Executive Boot Endpoint',
    'boot'=>$boot,
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V96.1 Executive Boot Endpoint','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>