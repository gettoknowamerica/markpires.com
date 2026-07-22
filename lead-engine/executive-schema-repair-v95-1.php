<?php
/**
 * Goliath V95.1 Schema Repair
 * Fixes older executive_deliverables tables that existed before V95 source_table/source_id columns.
 * Upload to: /lead-engine/executive-schema-repair-v95-1.php
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

  function v951_exec($sql){
    if(function_exists('gdb_exec')) return gdb_exec($sql);
    $pdo=gdb(); return $pdo->exec($sql);
  }
  function v951_table($t){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v951_col($t,$c){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v951_add($t,$c,$def,&$added,&$skipped){
    if(!v951_table($t)){ $skipped[]="$t missing"; return; }
    if(v951_col($t,$c)){ $skipped[]="$t.$c exists"; return; }
    v951_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
    $added[]="$t.$c";
  }
  function v951_index_exists($table,$index){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$table,$index]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v951_add_index($table,$name,$cols,&$idxAdded,&$idxSkipped){
    if(!v951_table($table)){ $idxSkipped[]="$table missing"; return; }
    if(v951_index_exists($table,$name)){ $idxSkipped[]="$table.$name exists"; return; }
    try{ v951_exec("ALTER TABLE `$table` ADD INDEX `$name` ($cols)"); $idxAdded[]="$table.$name"; }
    catch(Throwable $e){ $idxSkipped[]="$table.$name failed: ".$e->getMessage(); }
  }

  $added=[]; $skipped=[]; $idxAdded=[]; $idxSkipped=[];

  v951_exec("CREATE TABLE IF NOT EXISTS executive_deliverables (
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $cols=[
    'deliverable_uid'=>"VARCHAR(80) NULL",
    'source_table'=>"VARCHAR(80) NULL",
    'source_id'=>"INT NULL",
    'commission_id'=>"INT NULL",
    'task_id'=>"INT NULL",
    'browser_job_id'=>"INT NULL",
    'executive_key'=>"VARCHAR(80) NULL",
    'title'=>"VARCHAR(255) NULL",
    'deliverable_type'=>"VARCHAR(120) NULL",
    'priority'=>"INT DEFAULT 100",
    'status'=>"VARCHAR(80) DEFAULT 'new'",
    'lead_status'=>"VARCHAR(80) NULL",
    'preview'=>"MEDIUMTEXT NULL",
    'deliverable_json'=>"JSON NULL",
    'evidence'=>"MEDIUMTEXT NULL",
    'source_url'=>"TEXT NULL",
    'action_url'=>"TEXT NULL",
    'viewed'=>"TINYINT(1) DEFAULT 0",
    'viewed_at'=>"DATETIME NULL",
    'viewed_by'=>"VARCHAR(120) NULL",
    'archived'=>"TINYINT(1) DEFAULT 0",
    'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",
    'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
  ];
  foreach($cols as $c=>$def){ v951_add('executive_deliverables',$c,$def,$added,$skipped); }

  v951_add_index('executive_deliverables','idx_exec_del_source','`source_table`,`source_id`',$idxAdded,$idxSkipped);
  v951_add_index('executive_deliverables','idx_exec_del_exec','`executive_key`',$idxAdded,$idxSkipped);
  v951_add_index('executive_deliverables','idx_exec_del_status','`status`',$idxAdded,$idxSkipped);
  v951_add_index('executive_deliverables','idx_exec_del_viewed','`viewed`',$idxAdded,$idxSkipped);
  v951_add_index('executive_deliverables','idx_exec_del_created','`created_at`',$idxAdded,$idxSkipped);

  // Make sure core V95 tables exist too.
  v951_exec("CREATE TABLE IF NOT EXISTS executive_events (
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

  v951_exec("CREATE TABLE IF NOT EXISTS executive_memory (
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

  $verify=[
    'source_table'=>v951_col('executive_deliverables','source_table'),
    'source_id'=>v951_col('executive_deliverables','source_id'),
    'browser_job_id'=>v951_col('executive_deliverables','browser_job_id'),
    'viewed'=>v951_col('executive_deliverables','viewed')
  ];

  echo json_encode([
    'ok'=>true,
    'version'=>'V95.1 Schema Repair',
    'added'=>$added,
    'skipped'=>$skipped,
    'indexes_added'=>$idxAdded,
    'indexes_skipped'=>$idxSkipped,
    'verify'=>$verify,
    'next'=>'Run /lead-engine/executive-engine/executive-dispatcher.php?key=timetomakethedonuts&limit=200 again.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode([
    'ok'=>false,
    'version'=>'V95.1 Schema Repair',
    'error'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>