<?php
require_once __DIR__.'/goliath-company-brain.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
echo json_encode(gcb_company_snapshot(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
?>