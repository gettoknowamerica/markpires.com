<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';

$key=trim((string)($_GET['key']??$_SERVER['HTTP_X_GOLIATH_KEY']??''));
$expected=defined('AFTER_HOURS_CRON_KEY')?(string)AFTER_HOURS_CRON_KEY:
 (defined('RETELL_WEBHOOK_KEY')?(string)RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if($key===''){
 http_response_code(400);
 echo json_encode([
  'ok'=>false,
  'version'=>'V117.2 Brain Diagnostic',
  'error'=>'missing_key',
  'usage'=>'Add ?key=YOUR_CONFIGURED_AFTER_HOURS_CRON_KEY'
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 exit;
}
if(!hash_equals(trim($expected),$key)){
 http_response_code(403);
 echo json_encode([
  'ok'=>false,
  'version'=>'V117.2 Brain Diagnostic',
  'error'=>'bad_key',
  'hint'=>'Use the exact AFTER_HOURS_CRON_KEY value from lead-engine/config.php.'
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 exit;
}

$url='https://'.($_SERVER['HTTP_HOST']??'www.markpires.com').'/lead-engine/goliath-brain-context-v117.php?key='.rawurlencode($key).'&_='.time();
$context=stream_context_create(['http'=>['timeout'=>45,'ignore_errors'=>true]]);
$raw=@file_get_contents($url,false,$context);
$data=json_decode((string)$raw,true);

if(!is_array($data)){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V117.2 Brain Diagnostic','error'=>'invalid_brain_response','preview'=>substr((string)$raw,0,500)],JSON_PRETTY_PRINT);
 exit;
}

echo json_encode([
 'ok'=>(bool)($data['ok']??false),
 'version'=>'V117.2 Brain Diagnostic',
 'constitution_loaded'=>(bool)($data['constitution_loaded']??false),
 'constitution_directory'=>$data['constitution_directory']??'',
 'team_count'=>count($data['team']??[]),
 'expected_team_count'=>12,
 'roster_valid'=>count($data['team']??[])===12,
 'active_work_count'=>count($data['current_work']??[]),
 'recent_asset_count'=>count($data['recent_assets']??[]),
 'tool_count'=>count($data['tools']??[]),
 'brain_characters'=>mb_strlen((string)($data['brain_text']??'')),
 'team_names'=>array_map(fn($x)=>$x['name']??'', $data['team']??[]),
 'brain_preview'=>mb_substr((string)($data['brain_text']??''),0,1800),
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
?>