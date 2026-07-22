<?php
require_once __DIR__.'/goliath-v76-operating-system.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$install=gv76_install();
$counts=$install['ok']?gv76_counts():[];
echo json_encode(['ok'=>true,'version'=>'V76.0 Executive Operating System','install'=>$install,'counts'=>$counts,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>