<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-knowledge-vault.php';
header('Content-Type: application/json');
$key=$_GET['key'] ?? '';
$valid=defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key!==$valid){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
$limit=(int)($_GET['limit'] ?? 50);
echo json_encode(gkv_import_runtime(max(1,min(250,$limit))), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
