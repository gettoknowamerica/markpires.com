<?php
/**
 * V98.1 Shakespeare Authority Engine Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function s981_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 $created=[];
 s981_exec("CREATE TABLE IF NOT EXISTS shakespeare_content_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  package_uid VARCHAR(80) UNIQUE,
  content_type VARCHAR(120),
  title VARCHAR(255),
  slug VARCHAR(255),
  town VARCHAR(120) NULL,
  scenario VARCHAR(120) NULL,
  audience VARCHAR(120) NULL,
  primary_keyword VARCHAR(255) NULL,
  secondary_keywords MEDIUMTEXT NULL,
  recommended_for VARCHAR(255) NULL,
  status VARCHAR(80) DEFAULT 'draft',
  priority INT DEFAULT 100,
  hero_image_prompt MEDIUMTEXT NULL,
  summary MEDIUMTEXT NULL,
  html_content LONGTEXT NULL,
  text_content LONGTEXT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description TEXT NULL,
  schema_json LONGTEXT NULL,
  social_captions_json JSON NULL,
  email_blurb MEDIUMTEXT NULL,
  jessica_use_case MEDIUMTEXT NULL,
  einstein_status VARCHAR(80) DEFAULT 'pending',
  approval_status VARCHAR(80) DEFAULT 'needs_review',
  published_path TEXT NULL,
  created_by VARCHAR(80) DEFAULT 'shakespeare',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  published_at DATETIME NULL,
  INDEX(status),
  INDEX(content_type),
  INDEX(town),
  INDEX(scenario),
  INDEX(approval_status),
  INDEX(priority),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='shakespeare_content_packages';

 s981_exec("CREATE TABLE IF NOT EXISTS shakespeare_content_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_uid VARCHAR(80) UNIQUE,
  request_type VARCHAR(120),
  title VARCHAR(255),
  town VARCHAR(120) NULL,
  scenario VARCHAR(120) NULL,
  audience VARCHAR(120) NULL,
  prompt MEDIUMTEXT,
  status VARCHAR(80) DEFAULT 'queued',
  priority INT DEFAULT 100,
  source_executive VARCHAR(80) DEFAULT 'mark',
  package_id INT NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status),
  INDEX(request_type),
  INDEX(town),
  INDEX(scenario),
  INDEX(priority)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='shakespeare_content_queue';

 s981_exec("CREATE TABLE IF NOT EXISTS shakespeare_content_library (
  id INT AUTO_INCREMENT PRIMARY KEY,
  library_uid VARCHAR(80) UNIQUE,
  package_id INT NULL,
  label VARCHAR(255),
  scenario VARCHAR(120),
  audience VARCHAR(120),
  recommended_blog TEXT,
  email_blurb MEDIUMTEXT,
  status VARCHAR(80) DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(scenario),
  INDEX(audience),
  INDEX(status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='shakespeare_content_library';

 echo json_encode(['ok'=>true,'version'=>'V98.1 Shakespeare Authority Engine Installer','created'=>$created,'next'=>'Run /lead-engine/shakespeare-seed-v98-1.php?key=timetomakethedonuts','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.1 Shakespeare Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>