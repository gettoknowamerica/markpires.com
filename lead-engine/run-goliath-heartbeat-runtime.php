<?php
require_once __DIR__ . '/config.php';
$key=$_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && $key !== AFTER_HOURS_CRON_KEY){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
require_once __DIR__ . '/goliath-heartbeat-runtime.php';
header('Content-Type: application/json');
echo json_encode(ghr_run(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
