<?php
/**
 * V95.7 Sherlock Rename Patch
 * Replaces Holmes display with Sherlock where possible.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
  $files=[__DIR__.'/../dashboard/goliath-mission-control.php',__DIR__.'/../dashboard/goliath-live-executives.php',__DIR__.'/../dashboard/goliath-executive-inbox.php'];
  $changed=[];
  foreach($files as $file){
    if(!file_exists($file)) continue;
    $s=file_get_contents($file);
    $orig=$s;
    $s=str_replace(["'holmes'=>'Holmes'","Holmes","holmes"],["'sherlock'=>'Sherlock'","Sherlock","sherlock"],$s);
    // Avoid breaking URLs too aggressively; this is mostly display-level rename.
    if($s!==$orig){
      copy($file,$file.'.bak-sherlock-v95-7-'.date('Ymd-His'));
      file_put_contents($file,$s);
      $changed[]=$file;
    }
  }
  echo json_encode(['ok'=>true,'version'=>'V95.7 Sherlock Rename Patch','changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>