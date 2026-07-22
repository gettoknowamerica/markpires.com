<?php
/**
 * V90.1 Mission Control Scorsese Link Patcher
 * Upload to /lead-engine/ and run once.
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$file=dirname(__DIR__).'/dashboard/goliath-mission-control.php';
if(!file_exists($file)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'mission_control_not_found','file'=>$file]);exit;}
$src=file_get_contents($file);
$backup=$file.'.bak-v90-1-'.date('Ymd-His');
file_put_contents($backup,$src);
$replacements=[
  '/dashboard/scorsese-media-center.php'=>'/dashboard/scorsese-studio-pro.php',
  'Scorsese Media Center'=>'Scorsese Studio Pro',
  'Scorsese Live Media Command'=>'Scorsese Studio Pro Preview',
  'Classic Media Center'=>'Scorsese Studio Pro'
];
$new=str_replace(array_keys($replacements),array_values($replacements),$src);
file_put_contents($file,$new);
echo json_encode([
  'ok'=>true,
  'version'=>'V90.1 Mission Control Scorsese Link Patcher',
  'file'=>$file,
  'backup'=>$backup,
  'changed'=>($new!==$src),
  'next'=>'Open /dashboard/goliath-mission-control.php and confirm Scorsese links to Studio Pro.',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>