<?php
/**
 * V100 Production Collaboration Engine Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function v100_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function v100_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v100_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v100_add($t,$c,$def,&$added,&$skipped){if(!v100_table($t)){$skipped[]="$t missing";return;}if(v100_col($t,$c)){$skipped[]="$t.$c exists";return;}v100_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");$added[]="$t.$c";}

 $created=[];$added=[];$skipped=[];

 v100_exec("CREATE TABLE IF NOT EXISTS production_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  package_uid VARCHAR(80) UNIQUE,
  title VARCHAR(255),
  package_type VARCHAR(120) DEFAULT 'marketing_campaign',
  primary_executive VARCHAR(80) DEFAULT 'goliath',
  source_table VARCHAR(120) NULL,
  source_id INT NULL,
  status VARCHAR(80) DEFAULT 'assembling',
  priority INT DEFAULT 100,
  completion_score INT DEFAULT 0,
  approval_status VARCHAR(80) DEFAULT 'needs_review',
  package_summary MEDIUMTEXT NULL,
  direct_url TEXT NULL,
  tv_payload_json JSON NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  approved_at DATETIME NULL,
  INDEX(status),
  INDEX(approval_status),
  INDEX(source_table,source_id),
  INDEX(priority),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='production_packages';

 v100_exec("CREATE TABLE IF NOT EXISTS production_package_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_uid VARCHAR(80) UNIQUE,
  package_id INT NULL,
  executive_key VARCHAR(80),
  item_type VARCHAR(120),
  title VARCHAR(255),
  status VARCHAR(80) DEFAULT 'needed',
  source_table VARCHAR(120) NULL,
  source_id INT NULL,
  direct_url TEXT NULL,
  preview_text MEDIUMTEXT NULL,
  asset_url TEXT NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(package_id),
  INDEX(executive_key),
  INDEX(item_type),
  INDEX(status),
  INDEX(source_table,source_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='production_package_items';

 v100_exec("CREATE TABLE IF NOT EXISTS executive_collaboration_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_uid VARCHAR(80) UNIQUE,
  package_id INT NULL,
  from_executive VARCHAR(80),
  to_executive VARCHAR(80),
  task_type VARCHAR(120),
  title VARCHAR(255),
  instructions MEDIUMTEXT,
  status VARCHAR(80) DEFAULT 'queued',
  priority INT DEFAULT 100,
  source_table VARCHAR(120) NULL,
  source_id INT NULL,
  result_table VARCHAR(120) NULL,
  result_id INT NULL,
  due_at DATETIME NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX(package_id),
  INDEX(from_executive),
  INDEX(to_executive),
  INDEX(task_type),
  INDEX(status),
  INDEX(priority)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='executive_collaboration_tasks';

 v100_add('shakespeare_content_packages','production_package_id',"INT NULL",$added,$skipped);
 v100_add('jessica_email_drafts','production_package_id',"INT NULL",$added,$skipped);
 v100_add('scout_intel_dossiers','production_package_id',"INT NULL",$added,$skipped);
 v100_add('scorsese_comfy_jobs','production_package_id',"INT NULL",$added,$skipped);

 echo json_encode(['ok'=>true,'version'=>'V100 Production Collaboration Installer','created'=>$created,'added'=>$added,'skipped'=>$skipped,'next'=>'Run /lead-engine/v100-production-collaboration-engine.php?key=timetomakethedonuts&limit=50','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100 Production Collaboration Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>