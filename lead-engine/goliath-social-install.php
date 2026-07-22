<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
gdb_exec("CREATE TABLE IF NOT EXISTS goliath_social_accounts (
 id INT AUTO_INCREMENT PRIMARY KEY,
 platform_key VARCHAR(80) UNIQUE,
 platform_name VARCHAR(120),
 username VARCHAR(255),
 credential_note TEXT,
 status VARCHAR(40) DEFAULT 'disconnected',
 last_checked_at DATETIME NULL,
 metadata JSON NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
gdb_exec("CREATE TABLE IF NOT EXISTS goliath_social_queue (
 id INT AUTO_INCREMENT PRIMARY KEY,
 queue_uid VARCHAR(80) UNIQUE,
 platform VARCHAR(80),
 title VARCHAR(255),
 caption MEDIUMTEXT,
 media_url TEXT,
 source_type VARCHAR(80),
 source_id INT NULL,
 status VARCHAR(40) DEFAULT 'draft',
 scheduled_at DATETIME NULL,
 posted_at DATETIME NULL,
 metadata JSON NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo json_encode(['ok'=>true,'version'=>'V92.5 Social Command Install','time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>