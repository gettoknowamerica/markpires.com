<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function v119_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function v119_table(string $table):bool{
 $row=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 return (int)($row['c']??0)>0;
}
function v119_column(string $table,string $column):bool{
 $row=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$table,$column]);
 return (int)($row['c']??0)>0;
}

$key=trim((string)($_GET['key']??''));
if(!hash_equals(v119_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $created=[];$altered=[];

 $tables=[
  "CREATE TABLE IF NOT EXISTS goliath_v119_proof_tests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proof_uid VARCHAR(96) NOT NULL UNIQUE,
    mission_id BIGINT UNSIGNED NULL,
    proof_type VARCHAR(64) NOT NULL DEFAULT 'full_blog_loop',
    status VARCHAR(40) NOT NULL DEFAULT 'created',
    expected_stages INT NOT NULL DEFAULT 13,
    last_checked_at DATETIME NULL,
    result_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status(status,updated_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS goliath_v119_stage_quality (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mission_id BIGINT UNSIGNED NOT NULL,
    stage_no INT NOT NULL,
    artifact_version_id BIGINT UNSIGNED NULL,
    executive_key VARCHAR(64) NOT NULL,
    tangible_pass TINYINT(1) NOT NULL DEFAULT 0,
    content_length INT NOT NULL DEFAULT 0,
    has_title TINYINT(1) NOT NULL DEFAULT 0,
    has_cta TINYINT(1) NOT NULL DEFAULT 0,
    has_structure TINYINT(1) NOT NULL DEFAULT 0,
    has_source_or_evidence TINYINT(1) NOT NULL DEFAULT 0,
    has_visual_or_media_reference TINYINT(1) NOT NULL DEFAULT 0,
    qa_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mission_stage(mission_id,stage_no),
    KEY idx_mission_pass(mission_id,tangible_pass)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 foreach($tables as $sql){gdb()->exec($sql);$created[]='table';}

 if(v119_table('goliath_v112_missions')&&!v119_column('goliath_v112_missions','proof_test_uid')){
  gdb()->exec("ALTER TABLE goliath_v112_missions ADD COLUMN proof_test_uid VARCHAR(96) NULL, ADD KEY idx_proof_test_uid(proof_test_uid)");
  $altered[]='goliath_v112_missions.proof_test_uid';
 }
 if(v119_table('goliath_v118_asset_versions')&&!v119_column('goliath_v118_asset_versions','qa_passed')){
  gdb()->exec("ALTER TABLE goliath_v118_asset_versions ADD COLUMN qa_passed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_tangible");
  $altered[]='goliath_v118_asset_versions.qa_passed';
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V119 Blog Proof Stabilization Installer',
  'tables_ready'=>['goliath_v119_proof_tests','goliath_v119_stage_quality'],
  'columns_added'=>$altered,
  'next'=>'Create the proof mission with goliath-v119-create-blog-proof.php, then run production and watch goliath-v119-proof-status.php.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>