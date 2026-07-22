<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $file=__DIR__.'/../dashboard/goliath-mission-control.php';
 if(!file_exists($file)){echo json_encode(['ok'=>false,'error'=>'mission_control_missing']);exit;}
 $html=file_get_contents($file); $changed=false;
 if(strpos($html,'maximum-scale=1')===false){
  $html=preg_replace('/<meta name="viewport"[^>]*>/','<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">',$html,1);
  $changed=true;
 }
 if(strpos($html,'goliath-mission-control-mobile-v95-5.css')===false){
  $html=str_replace('</head>','<link rel="stylesheet" href="/dashboard/assets/goliath-mission-control-mobile-v95-5.css?v=955"></head>',$html);
  $changed=true;
 }
 if(strpos($html,'scout-ready-contacts.php')===false){
  $html=str_replace('/dashboard/scout-contact-workspace.php','/dashboard/scout-ready-contacts.php',$html);
  $changed=true;
 }
 if($changed){copy($file,$file.'.bak-v95-5-'.date('Ymd-His'));file_put_contents($file,$html);}
 echo json_encode(['ok'=>true,'version'=>'V95.5 Mission Mobile + Scout Link Patch','changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>