<?php
declare(strict_types=1);ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$sql=[
"CREATE TABLE IF NOT EXISTS jessica_campaigns_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,campaign_uid VARCHAR(100) NOT NULL,lead_type VARCHAR(80) NOT NULL,title VARCHAR(255) NOT NULL,
 subject_line VARCHAR(255) NOT NULL,body_text MEDIUMTEXT NOT NULL,sender_name VARCHAR(120) DEFAULT 'Mark Pires',
 sender_email VARCHAR(190) DEFAULT 'mark@markpires.com',approval_status VARCHAR(40) DEFAULT 'pending_approval',
 status VARCHAR(40) DEFAULT 'draft',batch_size INT DEFAULT 25,drip_plan_json JSON NULL,approved_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_campaign_uid(campaign_uid),KEY idx_campaign_type(lead_type,approval_status,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS jessica_campaign_recipients_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,campaign_id BIGINT NOT NULL,contact_id BIGINT NOT NULL,email VARCHAR(255) NOT NULL,
 first_name VARCHAR(120) NULL,property_address VARCHAR(255) NULL,status VARCHAR(40) DEFAULT 'queued',
 attempt_count INT DEFAULT 0,last_error MEDIUMTEXT NULL,sent_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_campaign_contact(campaign_id,contact_id),KEY idx_recipient_status(campaign_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS jessica_suppression_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(255) NOT NULL,reason VARCHAR(120) NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_suppression_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_mission_originator_flow_v111 (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,mission_uid VARCHAR(100) NOT NULL,originator_key VARCHAR(80) NOT NULL,
 current_stage VARCHAR(80) DEFAULT 'organization_review',originator_review_status VARCHAR(40) DEFAULT 'pending',
 goliath_execution_status VARCHAR(40) DEFAULT 'blocked',sequence_json JSON NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_originator_mission(mission_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
try{foreach($sql as $s)gdb()->exec($s);echo json_encode(['ok'=>true,'version'=>'V111.2 TV + Campaign + Originator Loop Migration','time'=>date('c')],JSON_PRETTY_PRINT);}
catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>