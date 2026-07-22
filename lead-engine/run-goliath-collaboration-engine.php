<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-collaboration-engine.php';
header('Content-Type: application/json');
$key=$_GET['key'] ?? '';
$expected=defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key !== $expected){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
$limit=isset($_GET['limit']) ? max(1,min(100,(int)$_GET['limit'])) : 25;
echo json_encode(gce_run($limit), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
