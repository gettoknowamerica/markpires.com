<?php
/**
 * Goliath V93.2 Scout Intelligence Engine Install
 * Upload to: /public_html/lead-engine/scout-intelligence-install.php
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
  gdb_exec("CREATE TABLE IF NOT EXISTS scout_intel_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_uid VARCHAR(80) UNIQUE,
    title VARCHAR(255),
    mission_type VARCHAR(80) DEFAULT 'custom',
    source_file VARCHAR(255),
    original_filename VARCHAR(255),
    total_records INT DEFAULT 0,
    imported_records INT DEFAULT 0,
    completed_records INT DEFAULT 0,
    priority INT DEFAULT 5,
    status VARCHAR(60) DEFAULT 'queued',
    current_contact_id INT NULL,
    notes MEDIUMTEXT,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(priority)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS scout_intel_dossiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_uid VARCHAR(80) UNIQUE,
    mission_id INT NULL,
    contact_id INT NULL,
    lead_uid VARCHAR(80),
    owner_name VARCHAR(255),
    property_address VARCHAR(255),
    mailing_address VARCHAR(255),
    town VARCHAR(120),
    state VARCHAR(40),
    zip VARCHAR(40),
    phone VARCHAR(120),
    email VARCHAR(255),
    source_label VARCHAR(255),
    research_status VARCHAR(80) DEFAULT 'queued',
    handoff_status VARCHAR(80) DEFAULT 'not_ready',
    confidence_score INT DEFAULT 0,
    contact_confidence INT DEFAULT 0,
    property_confidence INT DEFAULT 0,
    market_confidence INT DEFAULT 0,
    listing_history MEDIUMTEXT,
    nearby_sales MEDIUMTEXT,
    public_notes MEDIUMTEXT,
    call_strategy MEDIUMTEXT,
    email_strategy MEDIUMTEXT,
    recommended_blog VARCHAR(255),
    next_action VARCHAR(255),
    evidence_log MEDIUMTEXT,
    raw_json JSON NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(mission_id),
    INDEX(contact_id),
    INDEX(research_status),
    INDEX(handoff_status),
    INDEX(completed_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS scout_intel_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_uid VARCHAR(80) UNIQUE,
    mission_id INT NULL,
    dossier_id INT NULL,
    contact_id INT NULL,
    event_type VARCHAR(120),
    title VARCHAR(255),
    details MEDIUMTEXT,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(mission_id),
    INDEX(dossier_id),
    INDEX(event_type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $dir=dirname(__DIR__).'/data/scout_uploads';
  if(!is_dir($dir)) @mkdir($dir,0755,true);

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2 Scout Intelligence Install',
    'tables'=>['scout_intel_missions','scout_intel_dossiers','scout_intel_events'],
    'upload_dir'=>$dir,
    'next'=>'Open /dashboard/scout-intelligence-center.php or run /lead-engine/scout-run-cycle.php?key=...&limit=20',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>