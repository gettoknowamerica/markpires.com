<?php
/**
 * V93.2 Mission Control Scout Link Patch
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$file=dirname(__DIR__).'/dashboard/goliath-mission-control.php';
if(!file_exists($file)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'mission_control_not_found']);exit;}
$src=file_get_contents($file);
$backup=$file.'.bak-scout-v93-2-'.date('Ymd-His');
file_put_contents($backup,$src);
$new=$src;
$new=str_replace("if(\$k==='scorsese')return '/dashboard/scorsese-media-center.php'; if(\$k==='goliath')return '#executive-council';", "if(\$k==='scout')return '/dashboard/scout-intelligence-center.php'; if(\$k==='scorsese')return '/dashboard/scorsese-studio-pro.php'; if(\$k==='goliath')return '#executive-council';", $new);
$new=str_replace('/dashboard/scorsese-media-center.php','/dashboard/scorsese-studio-pro.php',$new);
if(strpos($new,'Scout Intelligence')===false){
  $new=str_replace('<a class="btn purple" href="/dashboard/scorsese-studio-pro.php">🎬 Scorsese</a>', '<a class="btn purple" href="/dashboard/scorsese-studio-pro.php">🎬 Scorsese</a><a class="btn green" href="/dashboard/scout-intelligence-center.php">🕵️ Scout Intelligence</a>', $new);
}
file_put_contents($file,$new);
echo json_encode(['ok'=>true,'version'=>'V93.2 Mission Control Scout Link Patch','changed'=>$new!==$src,'backup'=>$backup,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>