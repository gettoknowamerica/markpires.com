<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function i1184_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(i1184_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $statements=[
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
   current_phase VARCHAR(120) NULL,
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
 "CREATE TABLE IF NOT EXISTS scorsese_clone_profiles (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   profile_uid VARCHAR(96) NOT NULL UNIQUE,
   profile_name VARCHAR(255) NOT NULL,
   subject_name VARCHAR(255) NOT NULL DEFAULT 'Mark Pires',
   consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
   status VARCHAR(40) NOT NULL DEFAULT 'planning',
   voice_model_path TEXT NULL,
   likeness_model_path TEXT NULL,
   approved_uses_json LONGTEXT NULL,
   metadata_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_clone_capture_sessions (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   session_uid VARCHAR(96) NOT NULL UNIQUE,
   profile_id BIGINT UNSIGNED NOT NULL,
   session_type VARCHAR(60) NOT NULL DEFAULT 'guided_full_body_voice',
   status VARCHAR(40) NOT NULL DEFAULT 'planned',
   progress INT NOT NULL DEFAULT 0,
   current_instruction TEXT NULL,
   capture_manifest_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   KEY idx_profile_status(profile_id,status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS scorsese_clone_capture_clips (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   session_id BIGINT UNSIGNED NOT NULL,
   clip_no INT NOT NULL,
   instruction_key VARCHAR(100) NOT NULL,
   instruction_text TEXT NOT NULL,
   media_url TEXT NULL,
   media_path TEXT NULL,
   status VARCHAR(40) NOT NULL DEFAULT 'pending',
   quality_score DECIMAL(8,4) NULL,
   notes TEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_session_clip(session_id,clip_no)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ];
 foreach($statements as $sql)gdb()->exec($sql);

 echo json_encode([
  'ok'=>true,
  'version'=>'V118.4 Scorsese Studio Pro Integration',
  'tables_checked'=>count($statements),
  'next'=>'Upload Studio Pro and chunk handler, then open /dashboard/scorsese-studio-pro.php.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>