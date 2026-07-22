<?php
require_once __DIR__.'/scout-data-internal-crm-core.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$includeAgents=(int)($_GET['include_agents']??0)===1;
$results=scout774_import_all_data_files(['include_agents'=>$includeAgents]);
echo json_encode(['ok'=>true,'version'=>'V77.4.1 /data Homeowner Import','include_agents'=>$includeAgents,'data_dir'=>scout774_data_dir(),'results'=>$results,'message'=>'Imported homeowner/property CSVs from /data recursively into internal_crm_contacts. Agent master list is archived/skipped by default.','next'=>'Run /lead-engine/run-scout-data-cycle.php?key=...&limit=75','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>