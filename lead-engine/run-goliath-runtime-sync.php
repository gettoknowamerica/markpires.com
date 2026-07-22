<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-runtime-sync.php';
header('Content-Type: application/json');
$key=$_GET['key'] ?? '';
$valid=defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key!==$valid){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
echo json_encode(grs_run(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
