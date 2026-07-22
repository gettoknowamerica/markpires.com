<?php
/**
 * V96.2 Jessica Relationship Engine Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function v962_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function v962_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v962_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v962_add($t,$c,$def,&$added,&$skipped){if(!v962_table($t)){$skipped[]="$t missing";return;}if(v962_col($t,$c)){$skipped[]="$t.$c exists";return;}v962_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");$added[]="$t.$c";}
 $created=[];$added=[];$skipped=[];

 v962_exec("CREATE TABLE IF NOT EXISTS jessica_relationship_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_uid VARCHAR(80) UNIQUE,
  contact_id INT NULL,
  dossier_id INT NULL,
  lead_id INT NULL,
  source_table VARCHAR(120) NULL,
  source_id INT NULL,
  owner_name VARCHAR(255),
  property_address VARCHAR(255),
  town VARCHAR(120),
  lead_type VARCHAR(120),
  campaign VARCHAR(120),
  recommended_blog TEXT NULL,
  relationship_stage VARCHAR(80) DEFAULT 'new',
  priority INT DEFAULT 100,
  status VARCHAR(80) DEFAULT 'queued',
  due_at DATETIME NULL,
  last_action_at DATETIME NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status),
  INDEX(contact_id),
  INDEX(dossier_id),
  INDEX(relationship_stage),
  INDEX(due_at),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='jessica_relationship_queue';

 v962_exec("CREATE TABLE IF NOT EXISTS jessica_email_drafts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draft_uid VARCHAR(80) UNIQUE,
  queue_id INT NULL,
  contact_id INT NULL,
  dossier_id INT NULL,
  to_email VARCHAR(255) NULL,
  to_name VARCHAR(255) NULL,
  subject VARCHAR(255),
  body_html MEDIUMTEXT,
  body_text MEDIUMTEXT,
  recommended_blog TEXT NULL,
  status VARCHAR(80) DEFAULT 'pending_approval',
  approved_at DATETIME NULL,
  sent_at DATETIME NULL,
  send_result JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(queue_id),
  INDEX(status),
  INDEX(contact_id),
  INDEX(dossier_id),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='jessica_email_drafts';

 v962_exec("CREATE TABLE IF NOT EXISTS relationship_timeline (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_uid VARCHAR(80) UNIQUE,
  contact_id INT NULL,
  dossier_id INT NULL,
  lead_id INT NULL,
  executive_key VARCHAR(80),
  event_type VARCHAR(120),
  title VARCHAR(255),
  details MEDIUMTEXT,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(contact_id),
  INDEX(dossier_id),
  INDEX(executive_key),
  INDEX(event_type),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='relationship_timeline';

 v962_exec("CREATE TABLE IF NOT EXISTS appointment_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_uid VARCHAR(80) UNIQUE,
  contact_id INT NULL,
  dossier_id INT NULL,
  title VARCHAR(255),
  location VARCHAR(255),
  start_time DATETIME NULL,
  end_time DATETIME NULL,
  client_name VARCHAR(255),
  client_email VARCHAR(255),
  client_phone VARCHAR(80),
  status VARCHAR(80) DEFAULT 'draft',
  calendar_status VARCHAR(80) DEFAULT 'not_sent',
  email_status VARCHAR(80) DEFAULT 'not_sent',
  sms_status VARCHAR(80) DEFAULT 'not_sent',
  notes MEDIUMTEXT,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(contact_id),
  INDEX(dossier_id),
  INDEX(status),
  INDEX(start_time)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='appointment_requests';

 v962_add('scout_intel_dossiers','jessica_queue_id',"INT NULL",$added,$skipped);
 v962_add('scout_intel_dossiers','relationship_stage',"VARCHAR(80) NULL",$added,$skipped);
 v962_add('internal_crm_contacts','relationship_stage',"VARCHAR(80) NULL",$added,$skipped);
 v962_add('internal_crm_contacts','last_jessica_touch_at',"DATETIME NULL",$added,$skipped);

 echo json_encode(['ok'=>true,'version'=>'V96.2 Jessica Relationship Engine Installer','created'=>$created,'added'=>$added,'skipped'=>$skipped,'next'=>'Run /lead-engine/jessica-relationship-engine-v96-2.php?key=timetomakethedonuts&limit=50','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V96.2 Jessica Relationship Engine Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>