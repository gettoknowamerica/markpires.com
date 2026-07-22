<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function i1183_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(i1183_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $statements=[
 "CREATE TABLE IF NOT EXISTS goliath_v118_asset_versions (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   version_uid VARCHAR(96) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NOT NULL,
   stage_id BIGINT UNSIGNED NULL,
   stage_no INT NOT NULL,
   executive_key VARCHAR(64) NOT NULL,
   artifact_type VARCHAR(80) NOT NULL DEFAULT 'document',
   title VARCHAR(255) NOT NULL,
   content_html LONGTEXT NULL,
   content_text LONGTEXT NULL,
   artifact_url TEXT NULL,
   artifact_path TEXT NULL,
   change_note TEXT NULL,
   source_version_id BIGINT UNSIGNED NULL,
   is_tangible TINYINT(1) NOT NULL DEFAULT 1,
   status VARCHAR(40) NOT NULL DEFAULT 'stage_complete',
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
   reason TEXT NULL,
   is_current TINYINT(1) NOT NULL DEFAULT 1,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_mission_current(mission_id,is_current)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS goliath_v118_founder_requests (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   request_uid VARCHAR(120) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NULL,
   request_text LONGTEXT NOT NULL,
   originator_key VARCHAR(64) NOT NULL,
   priority INT NOT NULL DEFAULT 5000,
   status VARCHAR(40) NOT NULL DEFAULT 'created',
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_status_priority(status,priority,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_director_projects (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_uid VARCHAR(96) NOT NULL UNIQUE,
   mission_id BIGINT UNSIGNED NULL,
   asset_version_id BIGINT UNSIGNED NULL,
   title VARCHAR(255) NOT NULL,
   production_mode ENUM('automatic_director','human_director') NOT NULL DEFAULT 'automatic_director',
   production_type VARCHAR(80) NOT NULL DEFAULT 'episode',
   source_goal TEXT NULL,
   supplied_script LONGTEXT NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'ingest',
   progress INT NOT NULL DEFAULT 0,
   current_phase VARCHAR(80) NULL,
   output_url TEXT NULL,
   output_path TEXT NULL,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_status(status,updated_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_media_sources (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   source_uid VARCHAR(96) NOT NULL UNIQUE,
   source_name VARCHAR(255) NOT NULL,
   source_url TEXT NULL,
   source_path TEXT NULL,
   media_type VARCHAR(40) NOT NULL DEFAULT 'video',
   duration_seconds DECIMAL(12,3) NULL,
   checksum VARCHAR(128) NULL,
   proxy_path TEXT NULL,
   transcript_status VARCHAR(40) NOT NULL DEFAULT 'pending',
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_project(project_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_transcript_segments (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   source_id BIGINT UNSIGNED NOT NULL,
   segment_no INT NOT NULL,
   speaker_key VARCHAR(80) NULL,
   start_seconds DECIMAL(12,3) NOT NULL,
   end_seconds DECIMAL(12,3) NOT NULL,
   transcript_text LONGTEXT NOT NULL,
   confidence DECIMAL(6,5) NULL,
   filler_score DECIMAL(8,4) NULL,
   emotional_score DECIMAL(8,4) NULL,
   viral_score DECIMAL(8,4) NULL,
   keep_recommendation TINYINT(1) NOT NULL DEFAULT 0,
   metadata_json LONGTEXT NULL,
   KEY idx_project_time(project_id,start_seconds),
   KEY idx_source_segment(source_id,segment_no)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_scenes (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   scene_no INT NOT NULL,
   scene_uid VARCHAR(96) NOT NULL UNIQUE,
   title VARCHAR(255) NOT NULL,
   script_section LONGTEXT NULL,
   objective TEXT NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'draft',
   selected_take_id BIGINT UNSIGNED NULL,
   director_locked TINYINT(1) NOT NULL DEFAULT 0,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY uq_project_scene(project_id,scene_no)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_takes (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   scene_id BIGINT UNSIGNED NOT NULL,
   source_id BIGINT UNSIGNED NOT NULL,
   take_no INT NOT NULL,
   start_seconds DECIMAL(12,3) NOT NULL,
   end_seconds DECIMAL(12,3) NOT NULL,
   transcript_text LONGTEXT NULL,
   quality_score DECIMAL(8,4) NULL,
   emotion_score DECIMAL(8,4) NULL,
   clarity_score DECIMAL(8,4) NULL,
   continuity_score DECIMAL(8,4) NULL,
   recommended TINYINT(1) NOT NULL DEFAULT 0,
   director_selected TINYINT(1) NOT NULL DEFAULT 0,
   notes TEXT NULL,
   metadata_json LONGTEXT NULL,
   KEY idx_scene_score(scene_id,quality_score)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_director_notes (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   scene_id BIGINT UNSIGNED NULL,
   note_type VARCHAR(40) NOT NULL DEFAULT 'direction',
   note_text LONGTEXT NOT NULL,
   created_by VARCHAR(64) NOT NULL DEFAULT 'mark',
   applied_at DATETIME NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'open',
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_project_status(project_id,status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_edl_items (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   version_no INT NOT NULL DEFAULT 1,
   sequence_no INT NOT NULL,
   scene_id BIGINT UNSIGNED NULL,
   take_id BIGINT UNSIGNED NULL,
   source_id BIGINT UNSIGNED NULL,
   source_in DECIMAL(12,3) NOT NULL,
   source_out DECIMAL(12,3) NOT NULL,
   timeline_in DECIMAL(12,3) NOT NULL,
   timeline_out DECIMAL(12,3) NOT NULL,
   transition_in VARCHAR(80) NULL,
   transition_out VARCHAR(80) NULL,
   overlay_json LONGTEXT NULL,
   audio_json LONGTEXT NULL,
   locked TINYINT(1) NOT NULL DEFAULT 0,
   metadata_json LONGTEXT NULL,
   KEY idx_project_version(project_id,version_no,sequence_no)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_renders (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   project_id BIGINT UNSIGNED NOT NULL,
   render_uid VARCHAR(96) NOT NULL UNIQUE,
   version_no INT NOT NULL DEFAULT 1,
   render_type VARCHAR(40) NOT NULL DEFAULT 'review_cut',
   status VARCHAR(40) NOT NULL DEFAULT 'queued',
   progress INT NOT NULL DEFAULT 0,
   output_url TEXT NULL,
   output_path TEXT NULL,
   log_text LONGTEXT NULL,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_project_status(project_id,status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 foreach($statements as $sql)gdb()->exec($sql);

 echo json_encode([
  'ok'=>true,'version'=>'V118.3 Final Evolving Asset + Scorsese Director Foundation',
  'tables_created'=>count($statements),
  'next'=>'Upload all V118.3 files, replace local Constitution files, restart the production runtime, then create a new Founder Priority mission.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V118.3 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>