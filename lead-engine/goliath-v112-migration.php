<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??$_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$sql=[
"CREATE TABLE IF NOT EXISTS goliath_v112_missions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 mission_uid VARCHAR(100) NOT NULL,
 mission_type VARCHAR(80) NOT NULL DEFAULT 'content',
 title VARCHAR(255) NOT NULL,
 originator_key VARCHAR(80) NOT NULL,
 status VARCHAR(50) NOT NULL DEFAULT 'queued',
 priority INT NOT NULL DEFAULT 80,
 current_stage_no INT NOT NULL DEFAULT 1,
 source_url VARCHAR(500) NULL,
 source_payload_json JSON NULL,
 final_artifact_id BIGINT NULL,
 delivered_url VARCHAR(500) NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 delivered_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_v112_mission_uid(mission_uid),
 KEY idx_v112_mission_status(status,priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_v112_stages (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 mission_id BIGINT NOT NULL,
 stage_no INT NOT NULL,
 executive_key VARCHAR(80) NOT NULL,
 stage_key VARCHAR(100) NOT NULL,
 title VARCHAR(255) NOT NULL,
 instructions MEDIUMTEXT NOT NULL,
 status VARCHAR(50) NOT NULL DEFAULT 'waiting',
 local_task_id BIGINT NULL,
 input_artifact_id BIGINT NULL,
 output_artifact_id BIGINT NULL,
 attempt_count INT NOT NULL DEFAULT 0,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 last_error MEDIUMTEXT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_v112_stage(mission_id,stage_no),
 KEY idx_v112_stage_status(status,stage_no),
 KEY idx_v112_stage_task(local_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_v112_artifacts (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 mission_id BIGINT NOT NULL,
 stage_id BIGINT NULL,
 executive_key VARCHAR(80) NOT NULL,
 artifact_type VARCHAR(100) NOT NULL,
 title VARCHAR(255) NOT NULL,
 content_html LONGTEXT NULL,
 content_text LONGTEXT NULL,
 artifact_url VARCHAR(500) NULL,
 artifact_path VARCHAR(500) NULL,
 evidence_json JSON NULL,
 metadata_json JSON NULL,
 status VARCHAR(50) NOT NULL DEFAULT 'working',
 is_tangible TINYINT NOT NULL DEFAULT 0,
 delivered_by_goliath TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 delivered_at DATETIME NULL,
 KEY idx_v112_artifact_status(status,delivered_by_goliath),
 KEY idx_v112_artifact_mission(mission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_v112_events (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 mission_id BIGINT NULL,
 stage_id BIGINT NULL,
 executive_key VARCHAR(80) NULL,
 event_type VARCHAR(80) NOT NULL,
 title VARCHAR(255) NOT NULL,
 details MEDIUMTEXT NULL,
 artifact_id BIGINT NULL,
 url VARCHAR(500) NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 KEY idx_v112_event_created(created_at),
 KEY idx_v112_event_mission(mission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

try{
 foreach($sql as $s) gdb()->exec($s);
 echo json_encode([
  'ok'=>true,
  'version'=>'V112.0 Software Release Migration',
  'tables'=>['goliath_v112_missions','goliath_v112_stages','goliath_v112_artifacts','goliath_v112_events'],
  'rule'=>'A number becomes completed only after Goliath delivers one tangible asset.',
  'next'=>'Run /lead-engine/goliath-v112-seed-rehabilitation.php?key='.$key,
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>