<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function i1182_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(i1182_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $sql=[];
 $sql[]="CREATE TABLE IF NOT EXISTS goliath_v118_asset_versions (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   version_uid VARCHAR(80) NOT NULL,
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
   PRIMARY KEY(id),
   UNIQUE KEY uq_version_uid(version_uid),
   KEY idx_mission_stage(mission_id,stage_no),
   KEY idx_mission_created(mission_id,created_at),
   KEY idx_exec_status(executive_key,status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

 $sql[]="CREATE TABLE IF NOT EXISTS goliath_v118_founder_requests (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   request_uid VARCHAR(96) NOT NULL,
   mission_id BIGINT UNSIGNED NULL,
   request_text LONGTEXT NOT NULL,
   originator_key VARCHAR(64) NOT NULL,
   priority INT NOT NULL DEFAULT 5000,
   status VARCHAR(40) NOT NULL DEFAULT 'created',
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY(id),
   UNIQUE KEY uq_request_uid(request_uid),
   KEY idx_status_priority(status,priority,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

 foreach($sql as $statement)gdb()->exec($statement);

 echo json_encode([
  'ok'=>true,
  'version'=>'V118.2 Evolving Asset Schema',
  'tables'=>['goliath_v118_asset_versions','goliath_v118_founder_requests'],
  'next'=>'Upload the remaining V118.2 files, run the sequential engine once, and restart the production runtime.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V118.2 Evolving Asset Schema',
  'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>