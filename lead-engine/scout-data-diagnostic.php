<?php
require_once __DIR__.'/scout-data-internal-crm-core.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$install=scout774_install(); $files=scout774_data_files(); $counts=[];
foreach(['internal_contact_sources','internal_crm_contacts','scout_research_batches','local_ai_tasks'] as $t){ try{$counts[$t]=scout774_table($t)?(int)((gdb_one("SELECT COUNT(*) c FROM {$t}")?:['c'=>0])['c']):false;}catch(Throwable $e){$counts[$t]='error: '.$e->getMessage();}}
try{
  $counts['homeowner_queued']=(int)((gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE research_status IN ('queued','needs_research','retry') AND property_address IS NOT NULL AND property_address<>''")?:['c'=>0])['c']);
  $counts['archived_agent_list']=(int)((gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE contact_status='archived_agent_list'")?:['c'=>0])['c']);
  $counts['contacts_verified_phone']=(int)((gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE phone_1 IS NOT NULL AND phone_1<>''")?:['c'=>0])['c']);
}catch(Throwable $e){}
echo json_encode(['ok'=>true,'version'=>'V77.4.1 Scout Homeowner Diagnostic','data_dir'=>scout774_data_dir(),'install'=>$install,'files'=>$files,'counts'=>$counts,'next'=>'Run import-data-to-internal-crm.php. This version recursively reads /data and handles CRS DATA_START exports.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>