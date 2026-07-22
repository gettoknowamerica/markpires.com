<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function t926($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function c926($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function x926($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function add926($table,$col,$def,&$added,&$skipped){if(!t926($table)){$skipped[]="$table.$col missing table";return;} if(c926($table,$col)){$skipped[]="$table.$col exists";return;} x926("ALTER TABLE `$table` ADD COLUMN `$col` $def");$added[]="$table.$col";}
 $added=[];$skipped=[];
 add926('internal_crm_contacts','phone_3',"VARCHAR(120) NULL AFTER phone_2",$added,$skipped);
 add926('internal_crm_contacts','phone_mobile',"VARCHAR(120) NULL AFTER phone_3",$added,$skipped);
 add926('internal_crm_contacts','phone_landline',"VARCHAR(120) NULL AFTER phone_mobile",$added,$skipped);
 add926('internal_crm_contacts','best_phone',"VARCHAR(120) NULL AFTER phone_landline",$added,$skipped);
 add926('internal_crm_contacts','best_email',"VARCHAR(255) NULL AFTER email_2",$added,$skipped);
 add926('internal_crm_contacts','contact_source',"VARCHAR(255) NULL AFTER compliance_status",$added,$skipped);
 add926('internal_crm_contacts','contact_source_url',"TEXT NULL AFTER contact_source",$added,$skipped);
 add926('internal_crm_contacts','contact_verified_at',"DATETIME NULL AFTER contact_source_url",$added,$skipped);
 add926('internal_crm_contacts','contact_enrichment_status',"VARCHAR(80) DEFAULT 'not_started' AFTER contact_verified_at",$added,$skipped);
 add926('internal_crm_contacts','contact_enrichment_notes',"MEDIUMTEXT NULL AFTER contact_enrichment_status",$added,$skipped);
 add926('internal_crm_contacts','search_urls',"JSON NULL AFTER contact_enrichment_notes",$added,$skipped);
 add926('scout_intel_dossiers','phone_1',"VARCHAR(120) NULL AFTER phone",$added,$skipped);
 add926('scout_intel_dossiers','phone_2',"VARCHAR(120) NULL AFTER phone_1",$added,$skipped);
 add926('scout_intel_dossiers','phone_3',"VARCHAR(120) NULL AFTER phone_2",$added,$skipped);
 add926('scout_intel_dossiers','phone_mobile',"VARCHAR(120) NULL AFTER phone_3",$added,$skipped);
 add926('scout_intel_dossiers','best_phone',"VARCHAR(120) NULL AFTER phone_mobile",$added,$skipped);
 add926('scout_intel_dossiers','email_1',"VARCHAR(255) NULL AFTER email",$added,$skipped);
 add926('scout_intel_dossiers','email_2',"VARCHAR(255) NULL AFTER email_1",$added,$skipped);
 add926('scout_intel_dossiers','best_email',"VARCHAR(255) NULL AFTER email_2",$added,$skipped);
 add926('scout_intel_dossiers','contact_source',"VARCHAR(255) NULL AFTER source_label",$added,$skipped);
 add926('scout_intel_dossiers','contact_source_url',"TEXT NULL AFTER contact_source",$added,$skipped);
 add926('scout_intel_dossiers','contact_verified_at',"DATETIME NULL AFTER contact_source_url",$added,$skipped);
 add926('scout_intel_dossiers','search_urls',"JSON NULL AFTER raw_json",$added,$skipped);
 if(t926('internal_crm_contacts')){
  x926("UPDATE internal_crm_contacts SET best_phone=COALESCE(NULLIF(phone_1,''),NULLIF(phone_2,''),NULLIF(phone_3,''),NULLIF(phone_mobile,''),NULLIF(phone_landline,'')), best_email=COALESCE(NULLIF(email_1,''),NULLIF(email_2,'')) WHERE (best_phone IS NULL OR best_phone='') OR (best_email IS NULL OR best_email='')");
 }
 if(t926('scout_intel_dossiers')){
  x926("UPDATE scout_intel_dossiers d LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id SET d.phone_1=COALESCE(NULLIF(d.phone_1,''),c.phone_1), d.phone_2=COALESCE(NULLIF(d.phone_2,''),c.phone_2), d.email_1=COALESCE(NULLIF(d.email_1,''),c.email_1), d.email_2=COALESCE(NULLIF(d.email_2,''),c.email_2), d.best_phone=COALESCE(NULLIF(d.best_phone,''),c.best_phone,c.phone_1,c.phone_2), d.best_email=COALESCE(NULLIF(d.best_email,''),c.best_email,c.email_1,c.email_2), d.phone=COALESCE(NULLIF(d.phone,''),c.best_phone,c.phone_1,c.phone_2), d.email=COALESCE(NULLIF(d.email,''),c.best_email,c.email_1,c.email_2) WHERE d.contact_id IS NOT NULL");
 }
 $counts=['crm_with_any_contact'=>t926('internal_crm_contacts')?(int)(gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(phone_1,'')<>'' OR COALESCE(phone_2,'')<>'' OR COALESCE(phone_3,'')<>'' OR COALESCE(phone_mobile,'')<>'' OR COALESCE(best_phone,'')<>'' OR COALESCE(email_1,'')<>'' OR COALESCE(email_2,'')<>'' OR COALESCE(best_email,'')<>''")['c']??0):0];
 echo json_encode(['ok'=>true,'version'=>'V93.2.6 Scout CRM Column Cleanup','added'=>$added,'skipped'=>$skipped,'counts'=>$counts,'important'=>'Columns are ready, but empty contact fields still need a real data source, upload, or manual verification.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V93.2.6 Scout CRM Column Cleanup','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>