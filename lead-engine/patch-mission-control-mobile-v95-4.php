<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $file=__DIR__.'/../dashboard/goliath-mission-control.php';
 $css='/dashboard/assets/goliath-mission-control-mobile-v95-4.css';
 if(!file_exists($file)){echo json_encode(['ok'=>false,'error'=>'mission_control_missing']);exit;}
 $html=file_get_contents($file);
 $changed=false;
 if(strpos($html,'goliath-mission-control-mobile-v95-4.css')===false){
   $html=str_replace('</head>','<link rel="stylesheet" href="'.$css.'?v=954"></head>',$html);
   $changed=true;
 }
 if(strpos($html,'scout-ready-contacts.php')===false){
   $html=str_replace('<a class="btn blue" href="/dashboard/scout-contact-workspace.php">Open Scout Workspace</a>','<a class="btn blue" href="/dashboard/scout-ready-contacts.php">Open Scout Contacts</a><a class="btn" href="/dashboard/scout-contact-workspace.php">Workspace</a>',$html);
   $changed=true;
 }
 if($changed){
   $bak=$file.'.bak-v95-4-'.date('Ymd-His');
   copy($file,$bak);
   file_put_contents($file,$html);
 }
 echo json_encode(['ok'=>true,'version'=>'V95.4 Mission Control Mobile Patch','changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>