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
"CREATE TABLE IF NOT EXISTS goliath_conversations_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 conversation_uid VARCHAR(100) NOT NULL,
 title VARCHAR(255) NULL,
 status VARCHAR(40) DEFAULT 'active',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_conv_uid (conversation_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_messages_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 conversation_uid VARCHAR(100) NOT NULL,
 speaker_key VARCHAR(80) NOT NULL,
 speaker_name VARCHAR(120) NULL,
 message_text MEDIUMTEXT NOT NULL,
 message_type VARCHAR(60) DEFAULT 'chat',
 task_id BIGINT NULL,
 metadata_json JSON NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_conv_id (conversation_uid,id),
 INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_live_events_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 event_uid VARCHAR(100) NULL,
 executive_key VARCHAR(80) NULL,
 event_type VARCHAR(80) NULL,
 title VARCHAR(255) NULL,
 details MEDIUMTEXT NULL,
 status VARCHAR(60) NULL,
 progress INT DEFAULT 0,
 url VARCHAR(255) NULL,
 metadata_json JSON NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_event_created (created_at),
 INDEX idx_event_exec (executive_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_local_service_status_v111 (
 id INT AUTO_INCREMENT PRIMARY KEY,
 service_key VARCHAR(80) NOT NULL,
 status VARCHAR(40) DEFAULT 'unknown',
 endpoint VARCHAR(255) NULL,
 details MEDIUMTEXT NULL,
 last_seen_at DATETIME NULL,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_service (service_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
try{
 foreach($sql as $s)gdb()->exec($s);
 echo json_encode(['ok'=>true,'version'=>'V111.0 Live Mission Control Migration','tables'=>['goliath_conversations_v111','goliath_messages_v111','goliath_live_events_v111','goliath_local_service_status_v111'],'next'=>'Run /lead-engine/goliath-live-feed-v111.php?key='.$key,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>