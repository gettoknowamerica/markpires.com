<?php
require_once __DIR__.'/scout-data-internal-crm-core.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$limit=(int)($_GET['limit']??75);
$import=scout774_import_all_data_files(['include_agents'=>false]);
$batch=scout774_create_batch($limit);
echo json_encode(['ok'=>true,'version'=>'V77.4.1 Scout Homeowner Research Cycle','import'=>$import,'batch'=>$batch,'recommended_cron'=>'Every 4 hours with limit=75 until clean. Then increase to 125 or hourly.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>