<?php
/**
 * V93.2.4 Scout Enrichment Reality Check
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
  function allx($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [['error'=>$e->getMessage()]];}}
  $counts=[
    'crm_total'=>(int)(one("SELECT COUNT(*) c FROM internal_crm_contacts")['c']??0),
    'crm_with_any_contact'=>(int)(one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(phone_1,'')<>'' OR COALESCE(phone_2,'')<>'' OR COALESCE(email_1,'')<>'' OR COALESCE(email_2,'')<>''")['c']??0),
    'dossiers_total'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers")['c']??0),
    'dossiers_ready'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),
    'completed_enrichment_tasks'=>(int)(one("SELECT COUNT(*) c FROM local_ai_tasks WHERE task_type='scout_contact_enrichment' AND status='completed'")['c']??0),
    'completed_enrichment_with_blank_result'=>(int)(one("SELECT COUNT(*) c FROM local_ai_tasks WHERE task_type='scout_contact_enrichment' AND status='completed' AND (COALESCE(result,'')='' OR result NOT LIKE '%phone_1%' OR result NOT LIKE '%email_1%')")['c']??0),
  ];
  $recent=allx("SELECT id,status,LEFT(result,900) result_preview,metadata,completed_at FROM local_ai_tasks WHERE task_type='scout_contact_enrichment' ORDER BY id DESC LIMIT 10");
  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.4 Scout Enrichment Reality Check',
    'counts'=>$counts,
    'recent_enrichment_tasks'=>$recent,
    'verdict'=>$counts['crm_with_any_contact']===0?'CRM has zero phone/email values. Scout can build property dossiers, but cannot create call-ready dossiers unless enrichment worker has real web/API access or you upload a contact-enriched file.':'CRM has contact values; mapping should produce ready dossiers.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>