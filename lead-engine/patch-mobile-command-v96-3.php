<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $file=__DIR__.'/../dashboard/goliath-mission-control.php'; $changed=false;
 if(file_exists($file)){
  $html=file_get_contents($file);
  if(strpos($html,'goliath-mobile-command.php')===false){
   $link='<a class="btn gold" href="/dashboard/goliath-mobile-command.php">Mobile Command</a>';
   $html=str_replace('</body>',$link.'</body>',$html);
   copy($file,$file.'.bak-v96-3-'.date('Ymd-His')); file_put_contents($file,$html); $changed=true;
  }
 }
 echo json_encode(['ok'=>true,'version'=>'V96.3 Mission Control Mobile Command Patch','changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);}
?>