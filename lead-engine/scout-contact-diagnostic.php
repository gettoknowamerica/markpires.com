<?php
/**
 * Goliath V93.2.3 Scout Contact Diagnostic
 * Shows whether phone_1/email_1 fields actually contain data and previews raw_data keys.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function q1($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
  function qa($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [['error'=>$e->getMessage()]];}}
  $counts=[
    'total'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts")['c']??0),
    'phone_1'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(phone_1,'')<>''")['c']??0),
    'phone_2'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(phone_2,'')<>''")['c']??0),
    'email_1'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(email_1,'')<>''")['c']??0),
    'email_2'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(email_2,'')<>''")['c']??0),
    'any_contact'=>(int)(q1("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(phone_1,'')<>'' OR COALESCE(phone_2,'')<>'' OR COALESCE(email_1,'')<>'' OR COALESCE(email_2,'')<>''")['c']??0),
    'ready_dossiers'=>(int)(q1("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),
    'needs_contact_dossiers'=>(int)(q1("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE research_status='needs_contact_research'")['c']??0),
  ];

  $contactSamples=qa("SELECT id,owner_name,property_address,town,phone_1,phone_2,email_1,email_2,phone_confidence,email_confidence,priority_score,research_status FROM internal_crm_contacts WHERE COALESCE(phone_1,'')<>'' OR COALESCE(phone_2,'')<>'' OR COALESCE(email_1,'')<>'' OR COALESCE(email_2,'')<>'' ORDER BY COALESCE(priority_score,0) DESC,id ASC LIMIT 10");
  $rawSamples=qa("SELECT id,owner_name,property_address,town,LEFT(COALESCE(raw_data,''),1200) raw_preview FROM internal_crm_contacts WHERE COALESCE(raw_data,'')<>'' LIMIT 5");

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.3 Scout Contact Diagnostic',
    'counts'=>$counts,
    'contact_samples'=>$contactSamples,
    'raw_data_samples'=>$rawSamples,
    'verdict'=>$counts['any_contact']>0?'Contact data exists in CRM; Scout should target those records first.':'No contact data currently exists in phone_1/phone_2/email_1/email_2. Scout must run enrichment tasks before ready-for-call dossiers can exist.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>