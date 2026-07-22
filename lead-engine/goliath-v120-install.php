<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function v120_key():string{
 if(defined('AFTER_HOURS_CRON_KEY')) return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY')) return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function v120_col(string $table,string $column):bool{
 $r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$table,$column]);
 return (int)($r['c']??0)>0;
}
function v120_add(string $table,string $column,string $def,array &$changes):void{
 if(v120_col($table,$column)) return;
 gdb()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $def");
 $changes[]="$table.$column";
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(v120_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $changes=[];
 $tables=[
 "CREATE TABLE IF NOT EXISTS goliath_autonomous_backlog (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   backlog_uid VARCHAR(96) NOT NULL UNIQUE,
   executive_key VARCHAR(80) NOT NULL,
   work_type VARCHAR(100) NOT NULL,
   title VARCHAR(255) NOT NULL,
   directive LONGTEXT NOT NULL,
   source_table VARCHAR(100) NULL,
   source_id BIGINT UNSIGNED NULL,
   priority INT NOT NULL DEFAULT 100,
   status VARCHAR(40) NOT NULL DEFAULT 'ready',
   local_task_id BIGINT UNSIGNED NULL,
   artifact_version_id BIGINT UNSIGNED NULL,
   attempts INT NOT NULL DEFAULT 0,
   last_error LONGTEXT NULL,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY uq_source_work(executive_key,work_type,source_table,source_id),
   KEY idx_exec_status_priority(executive_key,status,priority,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_system_quarantine (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   quarantine_uid VARCHAR(96) NOT NULL UNIQUE,
   source_table VARCHAR(100) NOT NULL,
   source_id BIGINT UNSIGNED NOT NULL,
   reason VARCHAR(160) NOT NULL,
   snapshot_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   UNIQUE KEY uq_source(source_table,source_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_foundation_health (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   health_uid VARCHAR(96) NOT NULL UNIQUE,
   foundation_key VARCHAR(80) NOT NULL,
   status VARCHAR(40) NOT NULL,
   measured_value DECIMAL(14,3) NULL,
   details_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_foundation_created(foundation_key,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 foreach($tables as $sql) gdb()->exec($sql);

 v120_add('local_ai_tasks','artifact_contract',"VARCHAR(80) NULL",$changes);
 v120_add('local_ai_tasks','source_artifact_version_id',"BIGINT NULL",$changes);
 v120_add('local_ai_tasks','visible_in_production_studio',"TINYINT(1) NOT NULL DEFAULT 1",$changes);
 v120_add('goliath_v112_missions','architecture_version',"VARCHAR(40) NULL",$changes);
 v120_add('goliath_v112_missions','visible_in_production_studio',"TINYINT(1) NOT NULL DEFAULT 1",$changes);

 echo json_encode([
  'ok'=>true,
  'version'=>'V120 Four Foundations Installer',
  'changes'=>$changes,
  'foundations'=>[
   'living_asset_pipeline'=>'Only complete evolving artifacts may advance.',
   'autonomous_executive_loop'=>'Empty queues generate role-specific revenue work.',
   'internal_crm_source_of_truth'=>'Website leads and contacts remain in Hostinger MySQL.',
   'mission_control_production_studio'=>'The production feed exposes actual artifact versions only.'
  ],
  'next'=>'Run goliath-v120-architecture-reset.php once, then start START-GOLIATH-OMNI-V120.bat.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>