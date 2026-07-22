<?php
require_once __DIR__.'/goliath-v79-asset-os.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$r=gv79_build_morning_priorities();$items=gdb_all("SELECT * FROM goliath_morning_priorities WHERE priority_date=? ORDER BY rank_order ASC LIMIT 5",[date('Y-m-d')]);
echo json_encode(['ok'=>true,'version'=>'V79 Morning Brief','build'=>$r,'items'=>$items,'counts'=>gv79_counts(),'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>