<?php
/**
 * V95 Mission Control Patch
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
  $file=__DIR__.'/../dashboard/goliath-mission-control.php';
  if(!file_exists($file)){echo json_encode(['ok'=>false,'error'=>'mission_control_not_found','file'=>$file]);exit;}
  $txt=file_get_contents($file);
  $backup=$file.'.bak-v95-'.date('Ymd-His');
  file_put_contents($backup,$txt);
  $changed=false;
  if(strpos($txt,'goliath-executive-inbox.php')===false){
    $txt=str_replace('<a class="btn gold" href="/dashboard/goliath-deliverables.php">📦 Deliverables</a>','<a class="btn gold" href="/dashboard/goliath-executive-inbox.php">🔴 Executive Inbox</a><a class="btn gold" href="/dashboard/goliath-deliverables.php">📦 Deliverables</a>',$txt);
    $changed=true;
  }
  if(strpos($txt,'includes/executive-inbox-widget-v95.php')===false){
    $needle='<div class="radarBox"><h3>Opportunity Radar</h3>';
    $insert='<?php if(file_exists(__DIR__.\'/includes/executive-inbox-widget-v95.php\')) require __DIR__.\'/includes/executive-inbox-widget-v95.php\'; ?>'."\n".$needle;
    if(strpos($txt,$needle)!==false){$txt=str_replace($needle,$insert,$txt);$changed=true;}
  }
  if($changed) file_put_contents($file,$txt);
  echo json_encode(['ok'=>true,'version'=>'V95 Mission Control Patch','changed'=>$changed,'backup'=>$backup,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>