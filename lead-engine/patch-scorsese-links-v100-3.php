<?php
/**
 * V100.3 Patch Scorsese Links
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $files=glob(__DIR__.'/../dashboard/*.php')?:[];
 $changed=[];
 foreach($files as $f){
   $s=file_get_contents($f);
   $orig=$s;
   $s=str_replace(['/dashboard/scorsese-media-suite.php','scorsese-media-suite.php','/dashboard/scorsese-completed-media.php','ScorseseCompletedMedia.php','GoliathCompletedMedia.php'],['/dashboard/scorsese-studio-pro.php','scorsese-studio-pro.php','/dashboard/scorsese-studio-pro.php','scorsese-studio-pro.php','scorsese-studio-pro.php'],$s);
   if($s!==$orig){
     copy($f,$f.'.bak-v100-3-'.date('Ymd-His'));
     file_put_contents($f,$s);
     $changed[]=basename($f);
   }
 }
 echo json_encode(['ok'=>true,'version'=>'V100.3 Patch Scorsese Links','changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);}
?>