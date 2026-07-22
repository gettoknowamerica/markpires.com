<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-executive-runtime.php';
header('Content-Type: application/json');
$key=$_GET['key']??($_POST['key']??'');
$good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Invalid key']); exit; }
echo json_encode(gens_run_once(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
