<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function i1192_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function i1192_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[]; foreach($rows as $r)$out[(string)$r['column_name']]=true; return $out;
}
function i1192_add(string $table,string $column,string $definition,array &$changes):void{
 $cols=i1192_cols($table); if(isset($cols[$column]))return;
 gdb()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
 $changes[]="$table.$column";
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(i1192_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $changes=[];
 gdb()->exec("CREATE TABLE IF NOT EXISTS goliath_v118_asset_versions (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   version_uid VARCHAR(96) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NOT NULL,
   stage_id BIGINT UNSIGNED NULL,
   stage_no INT NOT NULL,
   executive_key VARCHAR(80) NOT NULL,
   artifact_type VARCHAR(100) NOT NULL DEFAULT 'document',
   title VARCHAR(255) NOT NULL,
   content_html LONGTEXT NULL,
   content_text LONGTEXT NULL,
   artifact_url TEXT NULL,
   artifact_path TEXT NULL,
   change_note LONGTEXT NULL,
   source_version_id BIGINT UNSIGNED NULL,
   is_tangible TINYINT(1) NOT NULL DEFAULT 1,
   qa_passed TINYINT(1) NOT NULL DEFAULT 0,
   status VARCHAR(50) NOT NULL DEFAULT 'stage_complete',
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_mission_stage(mission_id,stage_no),
   KEY idx_exec_status(executive_key,status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 gdb()->exec("CREATE TABLE IF NOT EXISTS goliath_v119_artifact_gate_log (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   gate_uid VARCHAR(96) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NOT NULL,
   stage_no INT NOT NULL,
   task_id BIGINT UNSIGNED NULL,
   executive_key VARCHAR(80) NOT NULL,
   passed TINYINT(1) NOT NULL DEFAULT 0,
   reason VARCHAR(120) NOT NULL,
   source_length INT NOT NULL DEFAULT 0,
   output_length INT NOT NULL DEFAULT 0,
   source_hash VARCHAR(64) NULL,
   output_hash VARCHAR(64) NULL,
   details_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_mission_stage(mission_id,stage_no),
   KEY idx_passed_created(passed,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 i1192_add('goliath_v112_stages','input_artifact_id','BIGINT NULL',$changes);
 i1192_add('goliath_v112_stages','output_artifact_id','BIGINT NULL',$changes);
 i1192_add('goliath_v112_stages','last_error','LONGTEXT NULL',$changes);
 i1192_add('goliath_v112_stages','blocking_issue','LONGTEXT NULL',$changes);
 i1192_add('goliath_v112_stages','completed_at','DATETIME NULL',$changes);

 $taskCols=i1192_cols('local_ai_tasks');
 if(isset($taskCols['result'])){
  $info=gdb_one("SELECT data_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='local_ai_tasks' AND column_name='result'");
  if(!in_array(strtolower((string)($info['data_type']??'')),['longtext','mediumtext'],true)){
   gdb()->exec("ALTER TABLE local_ai_tasks MODIFY COLUMN result LONGTEXT NULL");
   $changes[]='local_ai_tasks.result_LONGTEXT';
  }
 }

 echo json_encode([
  'ok'=>true,'version'=>'V119.2 Work-Only Architecture Installer',
  'changes'=>$changes,
  'law'=>'A stage cannot complete unless it stores the complete edited artifact. Goliath final delivery clones the approved artifact and never rewrites it.',
  'next'=>'Upload the V119.2 engine/completion/viewer, then commission the live absentee-owner URL.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119.2 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>