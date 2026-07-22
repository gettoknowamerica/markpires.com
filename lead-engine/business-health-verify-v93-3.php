<?php
/**
 * V93.3 Business Health Install / Verify
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$tables=['internal_crm_contacts','goliath_callback_tasks','scout_dossiers','local_ai_tasks'];
$out=[];
foreach($tables as $t){
 try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);$out[$t]=((int)($r['c']??0))>0;}catch(Throwable $e){$out[$t]=false;}
}
echo json_encode(['ok'=>true,'version'=>'V93.3 Business Health Verify','tables'=>$out,'time'=>date('c')],JSON_PRETTY_PRINT);
?>