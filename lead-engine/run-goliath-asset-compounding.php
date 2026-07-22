<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-asset-compounding-engine.php';
header('Content-Type: application/json');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit;}
echo json_encode(gac_run(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>