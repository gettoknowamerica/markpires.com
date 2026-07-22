<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function ar1191_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function ar1191_table(string $table):bool{
 $row=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 return (int)($row['c']??0)>0;
}
function ar1191_columns(string $table):array{
 $rows=gdb_all(
  "SELECT column_name,column_type,is_nullable,column_default,extra,data_type,character_maximum_length
   FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name=?
   ORDER BY ordinal_position",[$table]
 )?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=$row;return $out;
}
function ar1191_add_column(string $table,string $column,string $definition,array &$changes):void{
 $cols=ar1191_columns($table);
 if(isset($cols[$column]))return;
 gdb()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
 $changes[]="$table.$column";
}
function ar1191_add_index(string $table,string $index,string $definition,array &$changes):void{
 $row=gdb_one(
  "SELECT COUNT(*) c FROM information_schema.statistics
   WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$table,$index]
 );
 if((int)($row['c']??0)>0)return;
 gdb()->exec("ALTER TABLE `$table` ADD $definition");
 $changes[]="$table.$index";
}
function ar1191_exec(string $sql,array &$changes,string $label):void{
 gdb()->exec($sql);$changes[]=$label;
}
function ar1191_required_without_default(string $table):array{
 $rows=gdb_all(
  "SELECT column_name,column_type
   FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name=?
     AND is_nullable='NO' AND column_default IS NULL
     AND extra NOT LIKE '%auto_increment%'",[$table]
 )?:[];
 return $rows;
}
function ar1191_task_meta(array $task):array{
 foreach(['metadata_json','metadata'] as $column){
  if(empty($task[$column]))continue;
  $decoded=json_decode((string)$task[$column],true);
  if(is_array($decoded))return $decoded;
 }
 return [];
}

$key=trim((string)($_GET['key']??''));
if(!hash_equals(ar1191_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$repair=(string)($_GET['repair']??'1')!=='0';
$missionId=max(0,(int)($_GET['mission_id']??0));

try{
 if(!gdb())throw new RuntimeException('Hostinger MySQL connection is unavailable.');

 $changes=[];$warnings=[];$tables=[];

 $createStatements=[
 "CREATE TABLE IF NOT EXISTS goliath_v118_asset_versions (
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
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_v118_asset_selections (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   selection_uid VARCHAR(96) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NOT NULL,
   version_id BIGINT UNSIGNED NOT NULL,
   selected_by VARCHAR(64) NOT NULL DEFAULT 'mark',
   reason LONGTEXT NULL,
   is_current TINYINT(1) NOT NULL DEFAULT 1,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_mission_current(mission_id,is_current)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_v119_runtime_errors (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   error_uid VARCHAR(96) NOT NULL UNIQUE,
   task_id BIGINT UNSIGNED NULL,
   mission_id BIGINT UNSIGNED NULL,
   stage_no INT NULL,
   endpoint VARCHAR(160) NOT NULL,
   error_message LONGTEXT NOT NULL,
   context_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_task(task_id),
   KEY idx_mission_stage(mission_id,stage_no),
   KEY idx_created(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 if($repair){
  foreach($createStatements as $sql)gdb()->exec($sql);
 }

 foreach([
  'local_ai_tasks','goliath_v112_missions','goliath_v112_stages',
  'goliath_v112_artifacts','goliath_v112_events',
  'goliath_v118_asset_versions','goliath_v118_asset_selections',
  'goliath_v119_proof_tests','goliath_v119_stage_quality','goliath_v119_runtime_errors'
 ] as $table)$tables[$table]=ar1191_table($table);

 foreach(['local_ai_tasks','goliath_v112_missions','goliath_v112_stages','goliath_v112_artifacts','goliath_v112_events'] as $required){
  if(!$tables[$required])$warnings[]="Missing required table: $required";
 }

 if($repair&&$tables['goliath_v112_stages']){
  ar1191_add_column('goliath_v112_stages','input_artifact_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_stages','output_artifact_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_stages','local_task_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_stages','last_error','LONGTEXT NULL',$changes);
  ar1191_add_column('goliath_v112_stages','blocking_issue','LONGTEXT NULL',$changes);
  ar1191_add_column('goliath_v112_stages','completed_at','DATETIME NULL',$changes);
 }
 if($repair&&$tables['goliath_v112_artifacts']){
  ar1191_add_column('goliath_v112_artifacts','stage_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','content_html','LONGTEXT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','content_text','LONGTEXT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','artifact_url','TEXT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','artifact_path','TEXT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','metadata_json','LONGTEXT NULL',$changes);
  ar1191_add_column('goliath_v112_artifacts','is_tangible','TINYINT(1) NOT NULL DEFAULT 0',$changes);
  ar1191_add_column('goliath_v112_artifacts','delivered_by_goliath','TINYINT(1) NOT NULL DEFAULT 0',$changes);
 }
 if($repair&&$tables['goliath_v112_events']){
  ar1191_add_column('goliath_v112_events','stage_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_events','artifact_id','BIGINT NULL',$changes);
  ar1191_add_column('goliath_v112_events','url','TEXT NULL',$changes);
 }
 if($repair&&$tables['local_ai_tasks']){
  ar1191_add_column('local_ai_tasks','metadata_json','LONGTEXT NULL',$changes);
  ar1191_add_column('local_ai_tasks','result','LONGTEXT NULL',$changes);
  ar1191_add_column('local_ai_tasks','error','LONGTEXT NULL',$changes);
  ar1191_add_column('local_ai_tasks','progress','INT NOT NULL DEFAULT 0',$changes);
  ar1191_add_column('local_ai_tasks','updated_at','DATETIME NULL',$changes);

  $cols=ar1191_columns('local_ai_tasks');
  if(isset($cols['result'])&&!in_array(strtolower((string)$cols['result']['data_type']),['longtext','mediumtext'],true)){
   gdb()->exec("ALTER TABLE local_ai_tasks MODIFY COLUMN result LONGTEXT NULL");
   $changes[]='local_ai_tasks.result_to_LONGTEXT';
  }
  if(isset($cols['error'])&&!in_array(strtolower((string)$cols['error']['data_type']),['longtext','mediumtext','text'],true)){
   gdb()->exec("ALTER TABLE local_ai_tasks MODIFY COLUMN error LONGTEXT NULL");
   $changes[]='local_ai_tasks.error_to_LONGTEXT';
  }
 }

 $requiredColumns=[];
 foreach(['local_ai_tasks','goliath_v112_artifacts','goliath_v112_events','goliath_v118_asset_versions'] as $table){
  if(ar1191_table($table))$requiredColumns[$table]=ar1191_required_without_default($table);
 }

 $missionSnapshot=null;
 if($missionId>0&&$tables['goliath_v112_missions']){
  $mission=gdb_one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]);
  $stages=gdb_all("SELECT * FROM goliath_v112_stages WHERE mission_id=? ORDER BY stage_no",[$missionId])?:[];
  $tasks=[];
  if($tables['local_ai_tasks']){
   $candidates=gdb_all("SELECT * FROM local_ai_tasks WHERE status IN ('queued','working','claimed','failed') ORDER BY id DESC LIMIT 500")?:[];
   foreach($candidates as $task){
    $meta=ar1191_task_meta($task);
    if((int)($meta['mission_id']??0)===$missionId)$tasks[]=[
     'id'=>(int)$task['id'],'status'=>$task['status']??null,
     'task_type'=>$task['task_type']??$task['type']??null,
     'stage_no'=>(int)($meta['stage_no']??0),
     'error'=>$task['error']??null
    ];
   }
  }
  $versions=$tables['goliath_v118_asset_versions']?
   (gdb_all("SELECT id,stage_no,executive_key,title,status,created_at FROM goliath_v118_asset_versions WHERE mission_id=? ORDER BY stage_no,id",[$missionId])?:[]):[];
  $missionSnapshot=['mission'=>$mission,'stages'=>$stages,'tasks'=>$tasks,'versions'=>$versions];

  if($repair&&$mission){
   $currentStage=(int)($mission['current_stage_no']??1);
   $current=gdb_one("SELECT * FROM goliath_v112_stages WHERE mission_id=? AND stage_no=? LIMIT 1",[$missionId,$currentStage]);
   if($current){
    $activeTasks=[];
    foreach($tasks as $task)if((int)$task['stage_no']===$currentStage&&in_array($task['status'],['queued','working','claimed'],true))$activeTasks[]=$task;
    if(count($activeTasks)>1){
     usort($activeTasks,fn($a,$b)=>$b['id']<=>$a['id']);
     $keep=(int)$activeTasks[0]['id'];
     foreach(array_slice($activeTasks,1) as $duplicate){
      gdb_update('local_ai_tasks',[
       'status'=>'archived',
       'error'=>'V119.1 archived duplicate active task for the same mission stage.',
       'updated_at'=>gdb_now()
      ],'id=:id',['id'=>(int)$duplicate['id']]);
     }
     gdb_update('goliath_v112_stages',[
      'local_task_id'=>$keep,
      'status'=>'queued_local',
      'updated_at'=>gdb_now()
     ],'id=:id',['id'=>(int)$current['id']]);
     $changes[]="mission_$missionId duplicate_stage_tasks_archived";
    }elseif(count($activeTasks)===0&&!in_array((string)$current['status'],['complete'],true)){
     gdb_update('goliath_v112_stages',[
      'status'=>'ready','local_task_id'=>null,'blocking_issue'=>null,'last_error'=>null,'updated_at'=>gdb_now()
     ],'id=:id',['id'=>(int)$current['id']]);
     gdb_update('goliath_v112_missions',['status'=>'working','updated_at'=>gdb_now()],'id=:id',['id'=>$missionId]);
     $changes[]="mission_$missionId current_stage_reset_ready";
    }
   }
  }
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V119.1 Full System Audit and Repair',
  'repair_applied'=>$repair,
  'tables'=>$tables,
  'changes'=>$changes,
  'warnings'=>$warnings,
  'required_not_null_without_default'=>$requiredColumns,
  'mission_snapshot'=>$missionSnapshot,
  'diagnosis'=>[
   'voice_timeout'=>'The voice failure occurs before Kokoro generation, during the LLM answer. It is independent of the production completion 500.',
   'production_500'=>'The prior completion endpoint could fail on missing evolving-asset tables/columns, result column size, a live-table schema drift, or a required live column not represented in older migration files.',
   'launcher'=>'One BAT should start dependencies first and then start both specialized production and voice workers. They remain separate workers internally to prevent voice from being blocked by long production prompts.'
  ],
  'next'=>'Upload the V119.1 completion endpoint, run this audit with repair=1&mission_id=184, then start START-GOLIATH-OMNI-V119-1.bat.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V119.1 Full System Audit and Repair',
  'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>