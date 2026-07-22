<?php
/**
 * V96.3 Relationship Timeline + Morning Brief Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function e963($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function t963($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function c963($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function add963($t,$c,$def,&$added,&$skipped){if(!t963($t)){$skipped[]="$t missing";return;}if(c963($t,$c)){$skipped[]="$t.$c exists";return;}e963("ALTER TABLE `$t` ADD COLUMN `$c` $def");$added[]="$t.$c";}
 $created=[];$added=[];$skipped=[];
 e963("CREATE TABLE IF NOT EXISTS relationship_memory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  memory_uid VARCHAR(80) UNIQUE,
  contact_id INT NULL,
  dossier_id INT NULL,
  lead_id INT NULL,
  memory_type VARCHAR(120),
  title VARCHAR(255),
  content MEDIUMTEXT,
  importance INT DEFAULT 50,
  source_executive VARCHAR(80),
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(contact_id), INDEX(dossier_id), INDEX(memory_type), INDEX(source_executive), INDEX(importance), INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='relationship_memory';
 e963("CREATE TABLE IF NOT EXISTS daily_briefs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brief_uid VARCHAR(80) UNIQUE,
  brief_date DATE,
  title VARCHAR(255),
  summary MEDIUMTEXT,
  metrics_json JSON NULL,
  action_items_json JSON NULL,
  status VARCHAR(80) DEFAULT 'new',
  viewed TINYINT(1) DEFAULT 0,
  viewed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(brief_date), INDEX(status), INDEX(viewed)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='daily_briefs';
 add963('relationship_timeline','priority',"INT DEFAULT 50",$added,$skipped);
 add963('relationship_timeline','is_new',"TINYINT(1) DEFAULT 1",$added,$skipped);
 add963('relationship_timeline','viewed_at',"DATETIME NULL",$added,$skipped);
 add963('jessica_relationship_queue','next_touch_at',"DATETIME NULL",$added,$skipped);
 add963('jessica_relationship_queue','last_touch_summary',"MEDIUMTEXT NULL",$added,$skipped);
 echo json_encode(['ok'=>true,'version'=>'V96.3 Relationship Timeline Installer','created'=>$created,'added'=>$added,'skipped'=>$skipped,'next'=>'Run /lead-engine/morning-brief-v96-3.php?key=timetomakethedonuts','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V96.3 Relationship Timeline Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>