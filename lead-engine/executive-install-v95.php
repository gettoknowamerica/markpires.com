<?php
/**
 * Goliath V95 Drop 1 — Universal Executive Engine Installer
 * Upload to: /lead-engine/executive-install-v95.php
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
  }

  function v95_exec($sql){
    if(function_exists('gdb_exec')) return gdb_exec($sql);
    $pdo=gdb(); return $pdo->exec($sql);
  }
  function v95_table($t){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v95_col($t,$c){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v95_add_col($t,$c,$def,&$added,&$skipped){
    if(!v95_table($t)){ $skipped[]="$t missing"; return; }
    if(v95_col($t,$c)){ $skipped[]="$t.$c exists"; return; }
    v95_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
    $added[]="$t.$c";
  }

  $created=[]; $added=[]; $skipped=[];

  v95_exec("CREATE TABLE IF NOT EXISTS executive_deliverables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deliverable_uid VARCHAR(80) UNIQUE,
    source_table VARCHAR(80),
    source_id INT NULL,
    commission_id INT NULL,
    task_id INT NULL,
    browser_job_id INT NULL,
    executive_key VARCHAR(80),
    title VARCHAR(255),
    deliverable_type VARCHAR(120),
    priority INT DEFAULT 100,
    status VARCHAR(80) DEFAULT 'new',
    lead_status VARCHAR(80) NULL,
    preview MEDIUMTEXT,
    deliverable_json JSON NULL,
    evidence MEDIUMTEXT,
    source_url TEXT NULL,
    action_url TEXT NULL,
    viewed TINYINT(1) DEFAULT 0,
    viewed_at DATETIME NULL,
    viewed_by VARCHAR(120) NULL,
    archived TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(status),
    INDEX(viewed),
    INDEX(priority),
    INDEX(created_at),
    INDEX(source_table,source_id),
    INDEX(commission_id),
    INDEX(task_id),
    INDEX(browser_job_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_deliverables';

  v95_exec("CREATE TABLE IF NOT EXISTS executive_memory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memory_uid VARCHAR(80) UNIQUE,
    executive_key VARCHAR(80),
    related_deliverable_id INT NULL,
    related_lead_id INT NULL,
    related_contact_id INT NULL,
    memory_type VARCHAR(120),
    title VARCHAR(255),
    content MEDIUMTEXT,
    metadata JSON NULL,
    importance INT DEFAULT 50,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(memory_type),
    INDEX(related_contact_id),
    INDEX(importance)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_memory';

  v95_exec("CREATE TABLE IF NOT EXISTS executive_qa_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qa_uid VARCHAR(80) UNIQUE,
    deliverable_id INT NULL,
    executive_key VARCHAR(80),
    qa_type VARCHAR(120),
    passed TINYINT(1) DEFAULT 0,
    score INT DEFAULT 0,
    notes MEDIUMTEXT,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(deliverable_id),
    INDEX(executive_key),
    INDEX(passed)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_qa_checks';

  v95_exec("CREATE TABLE IF NOT EXISTS executive_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_uid VARCHAR(80) UNIQUE,
    executive_key VARCHAR(80),
    event_type VARCHAR(120),
    title VARCHAR(255),
    details MEDIUMTEXT,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(event_type),
    INDEX(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_events';

  v95_exec("CREATE TABLE IF NOT EXISTS executive_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    followup_uid VARCHAR(80) UNIQUE,
    contact_id INT NULL,
    lead_id INT NULL,
    executive_key VARCHAR(80) DEFAULT 'jessica',
    status VARCHAR(80) DEFAULT 'queued',
    campaign VARCHAR(120),
    title VARCHAR(255),
    message MEDIUMTEXT,
    due_at DATETIME NULL,
    completed_at DATETIME NULL,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(contact_id),
    INDEX(lead_id),
    INDEX(status),
    INDEX(due_at),
    INDEX(campaign)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_followups';

  // Shared heartbeat exists from V94, but create if missing.
  v95_exec("CREATE TABLE IF NOT EXISTS goliath_executive_heartbeat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executive_key VARCHAR(80) UNIQUE,
    status VARCHAR(80) DEFAULT 'idle',
    current_job_id INT NULL,
    current_task_id INT NULL,
    current_commission_id INT NULL,
    current_step VARCHAR(255),
    progress INT DEFAULT 0,
    browser_status VARCHAR(120),
    pages_read INT DEFAULT 0,
    evidence_count INT DEFAULT 0,
    phones_found INT DEFAULT 0,
    emails_found INT DEFAULT 0,
    confidence_score INT DEFAULT 0,
    message MEDIUMTEXT,
    metadata JSON NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(updated_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='goliath_executive_heartbeat';

  // Lead status fields for internal CRM.
  v95_add_col('internal_crm_contacts','lead_status',"VARCHAR(80) DEFAULT 'new' AFTER research_status",$added,$skipped);
  v95_add_col('internal_crm_contacts','campaign_status',"VARCHAR(80) NULL AFTER lead_status",$added,$skipped);
  v95_add_col('internal_crm_contacts','last_mark_viewed_at',"DATETIME NULL AFTER campaign_status",$added,$skipped);
  v95_add_col('internal_crm_contacts','do_not_contact',"TINYINT(1) DEFAULT 0 AFTER last_mark_viewed_at",$added,$skipped);
  v95_add_col('internal_crm_contacts','hot_lead',"TINYINT(1) DEFAULT 0 AFTER do_not_contact",$added,$skipped);
  v95_add_col('internal_crm_contacts','longterm_drip',"TINYINT(1) DEFAULT 0 AFTER hot_lead",$added,$skipped);
  v95_add_col('internal_crm_contacts','next_followup_at',"DATETIME NULL AFTER longterm_drip",$added,$skipped);

  // Make GBI jobs compatible if V94 tables already exist.
  v95_add_col('goliath_browser_jobs','viewed',"TINYINT(1) DEFAULT 0 AFTER status",$added,$skipped);
  v95_add_col('goliath_browser_jobs','viewed_at',"DATETIME NULL AFTER viewed",$added,$skipped);

  echo json_encode([
    'ok'=>true,
    'version'=>'V95 Drop 1 Universal Executive Engine Installer',
    'created_or_confirmed'=>$created,
    'columns_added'=>$added,
    'columns_skipped'=>$skipped,
    'next'=>[
      'run_dispatcher'=>'/lead-engine/executive-engine/executive-dispatcher.php?key=...&limit=100',
      'inbox'=>'/dashboard/goliath-executive-inbox.php',
      'live'=>'/dashboard/goliath-live-executives.php',
      'patch_mission_control'=>'/lead-engine/patch-mission-control-v95.php?key=...'
    ],
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V95 Drop 1 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>