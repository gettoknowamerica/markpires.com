<?php
/**
 * Goliath V93.3 Mission Control Business Health Patcher
 * Upload to /public_html/lead-engine/patch-mission-control-business-health-v93-3.php
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$file=dirname(__DIR__).'/dashboard/goliath-mission-control.php';
$widget=dirname(__DIR__).'/dashboard/includes/business-health-widget-v93-3.php';
if(!file_exists($file)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'mission_control_not_found','file'=>$file]);exit;}
if(!file_exists($widget)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'widget_not_found','widget'=>$widget]);exit;}

$src=file_get_contents($file);
$backup=$file.'.bak-v93-3-'.date('Ymd-His');
file_put_contents($backup,$src);

$changed=false;
if(strpos($src,'business-health-widget-v93-3.php')===false){
  $needle='<section class="commandFrame">';
  $insert='<?php if(file_exists(__DIR__."/includes/business-health-widget-v93-3.php")) require __DIR__."/includes/business-health-widget-v93-3.php"; ?>'."\n".$needle;
  if(strpos($src,$needle)!==false){
    $src=str_replace($needle,$insert,$src);
    $changed=true;
  }
}
$src=str_replace('/dashboard/scorsese-media-center.php','/dashboard/scorsese-studio-pro.php',$src);
$src=str_replace("if($k==='scorsese')return '/dashboard/scorsese-media-center.php';","if($k==='scorsese')return '/dashboard/scorsese-studio-pro.php'; if($k==='scout')return '/dashboard/scout-intelligence-center.php';",$src);
if(strpos($src,"if($k==='scout')return '/dashboard/scout-intelligence-center.php';")===false){
  $src=str_replace("if($k==='goliath')return '#executive-council';","if($k==='scout')return '/dashboard/scout-intelligence-center.php'; if($k==='goliath')return '#executive-council';",$src);
}
file_put_contents($file,$src);

echo json_encode([
 'ok'=>true,
 'version'=>'V93.3 Mission Control Business Health Patch',
 'changed'=>$changed,
 'backup'=>$backup,
 'mission_control'=>$file,
 'next'=>'Open /dashboard/goliath-mission-control.php and confirm Business Health appears under the header.',
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>