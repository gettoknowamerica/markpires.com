<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function i1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function i1193_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
function i1193_add(string $table,string $column,string $definition,array &$changes):void{
 $cols=i1193_cols($table);if(isset($cols[$column]))return;
 gdb()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");$changes[]="$table.$column";
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(i1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $changes=[];
 $sql=[
 "CREATE TABLE IF NOT EXISTS internal_crm_contacts (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   contact_uid VARCHAR(96) NOT NULL UNIQUE,
   lead_uid VARCHAR(96) NULL,
   source_type VARCHAR(64) NOT NULL DEFAULT 'website_form',
   lead_type VARCHAR(64) NOT NULL DEFAULT 'website_lead',
   name VARCHAR(255) NULL,
   owner_name VARCHAR(255) NULL,
   email VARCHAR(255) NULL,
   existing_email VARCHAR(255) NULL,
   phone VARCHAR(80) NULL,
   existing_phone VARCHAR(80) NULL,
   best_email VARCHAR(255) NULL,
   best_phone VARCHAR(80) NULL,
   property_address TEXT NULL,
   address TEXT NULL,
   town VARCHAR(150) NULL,
   city VARCHAR(150) NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'new',
   relationship_status VARCHAR(60) NOT NULL DEFAULT 'new_lead',
   research_status VARCHAR(60) NOT NULL DEFAULT 'queued',
   contact_enrichment_status VARCHAR(60) NOT NULL DEFAULT 'queued',
   drip_status VARCHAR(60) NOT NULL DEFAULT 'not_enrolled',
   priority INT NOT NULL DEFAULT 3,
   lead_score INT NOT NULL DEFAULT 0,
   route VARCHAR(80) NULL,
   notes LONGTEXT NULL,
   evidence LONGTEXT NULL,
   raw_json LONGTEXT NULL,
   metadata LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_email(email),
   KEY idx_phone(phone),
   KEY idx_status(status,created_at),
   KEY idx_research(research_status,priority)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS leads (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   uid VARCHAR(96) NOT NULL UNIQUE,
   crm_contact_id BIGINT UNSIGNED NULL,
   type VARCHAR(64) NOT NULL DEFAULT 'website_lead',
   tag VARCHAR(80) NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'new',
   name VARCHAR(255) NULL,
   email VARCHAR(255) NULL,
   phone VARCHAR(80) NULL,
   address TEXT NULL,
   town VARCHAR(150) NULL,
   timeline VARCHAR(120) NULL,
   goal TEXT NULL,
   message LONGTEXT NULL,
   price_range VARCHAR(120) NULL,
   estimated_value VARCHAR(120) NULL,
   budget VARCHAR(120) NULL,
   source VARCHAR(150) NULL,
   page_url TEXT NULL,
   lead_score INT NOT NULL DEFAULT 0,
   route VARCHAR(80) NULL,
   lead_temperature VARCHAR(40) NULL,
   lead_origin VARCHAR(80) NULL,
   drip_status VARCHAR(60) NOT NULL DEFAULT 'not_enrolled',
   raw_payload LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_email(email),
   KEY idx_phone(phone),
   KEY idx_status_created(status,created_at),
   KEY idx_crm(crm_contact_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_email_drip_queue (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   queue_uid VARCHAR(96) NOT NULL UNIQUE,
   lead_id BIGINT UNSIGNED NULL,
   crm_contact_id BIGINT UNSIGNED NULL,
   lead_uid VARCHAR(96) NULL,
   sequence_key VARCHAR(80) NOT NULL,
   step_no INT NOT NULL,
   scheduled_at DATETIME NOT NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'pending',
   subject VARCHAR(255) NOT NULL,
   body_html LONGTEXT NOT NULL,
   body_text LONGTEXT NULL,
   recipient_email VARCHAR(255) NOT NULL,
   recipient_name VARCHAR(255) NULL,
   sent_at DATETIME NULL,
   error_message LONGTEXT NULL,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY uq_lead_sequence_step(lead_uid,sequence_key,step_no),
   KEY idx_due(status,scheduled_at),
   KEY idx_contact(crm_contact_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_contact_enrichment_queue (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   queue_uid VARCHAR(96) NOT NULL UNIQUE,
   contact_id BIGINT UNSIGNED NOT NULL,
   lead_id BIGINT UNSIGNED NULL,
   owner_name VARCHAR(255) NULL,
   property_address TEXT NULL,
   town VARCHAR(150) NULL,
   current_phone VARCHAR(80) NULL,
   current_email VARCHAR(255) NULL,
   missing_phone TINYINT(1) NOT NULL DEFAULT 0,
   missing_email TINYINT(1) NOT NULL DEFAULT 0,
   status VARCHAR(40) NOT NULL DEFAULT 'queued',
   priority INT NOT NULL DEFAULT 300,
   local_task_id BIGINT UNSIGNED NULL,
   attempts INT NOT NULL DEFAULT 0,
   best_phone VARCHAR(80) NULL,
   best_email VARCHAR(255) NULL,
   phone_confidence INT NOT NULL DEFAULT 0,
   email_confidence INT NOT NULL DEFAULT 0,
   source_evidence LONGTEXT NULL,
   search_urls_json LONGTEXT NULL,
   error_message LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY uq_contact(contact_id),
   KEY idx_status_priority(status,priority,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_lead_timeline (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   event_uid VARCHAR(96) NOT NULL UNIQUE,
   lead_uid VARCHAR(96) NULL,
   crm_contact_id BIGINT UNSIGNED NULL,
   actor VARCHAR(80) NOT NULL,
   event_type VARCHAR(80) NOT NULL,
   title VARCHAR(255) NOT NULL,
   details LONGTEXT NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'complete',
   metadata LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_contact_created(crm_contact_id,created_at),
   KEY idx_lead_created(lead_uid,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_revenue_engine_failures (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   failure_uid VARCHAR(96) NOT NULL UNIQUE,
   lead_uid VARCHAR(96) NULL,
   service VARCHAR(100) NOT NULL,
   severity VARCHAR(40) NOT NULL DEFAULT 'warning',
   message LONGTEXT NOT NULL,
   payload LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_created(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 foreach($sql as $statement)gdb()->exec($statement);

 i1193_add('local_ai_tasks','metadata_json','LONGTEXT NULL',$changes);
 i1193_add('local_ai_tasks','executive_key','VARCHAR(80) NULL',$changes);
 i1193_add('local_ai_tasks','workflow_state',"VARCHAR(40) NULL DEFAULT 'queued'",$changes);
 i1193_add('local_ai_tasks','result','LONGTEXT NULL',$changes);
 i1193_add('local_ai_tasks','error','LONGTEXT NULL',$changes);
 i1193_add('local_ai_tasks','progress','INT NOT NULL DEFAULT 0',$changes);

 echo json_encode([
  'ok'=>true,'version'=>'V119.3 Internal CRM Lead Orchestration',
  'tables_ready'=>[
   'internal_crm_contacts','leads','goliath_email_drip_queue',
   'goliath_contact_enrichment_queue','goliath_lead_timeline'
  ],
  'columns_added'=>$changes,
  'law'=>'Every website submission is committed internally before email or executive dispatch. No HubSpot or Supabase write is used.',
  'next'=>'Upload all V119.3 files, run the repair/backfill endpoint for Sofia, then keep the unified runtime running.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119.3 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>