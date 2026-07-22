<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??$_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$tables=[
"CREATE TABLE IF NOT EXISTS goliath_executive_activity_v110 (
 id INT AUTO_INCREMENT PRIMARY KEY,
 executive_key VARCHAR(80) NOT NULL,
 display_name VARCHAR(120) NOT NULL,
 department VARCHAR(160) NULL,
 current_mode VARCHAR(80) DEFAULT 'observing',
 current_mission_uid VARCHAR(100) NULL,
 current_action MEDIUMTEXT NULL,
 status VARCHAR(40) DEFAULT 'active',
 last_heartbeat_at DATETIME NULL,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_v110_exec (executive_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS goliath_required_handoffs_v110 (
 id INT AUTO_INCREMENT PRIMARY KEY,
 handoff_uid VARCHAR(100) NULL,
 mission_uid VARCHAR(100) NULL,
 from_executive VARCHAR(80) NULL,
 to_executive VARCHAR(80) NULL,
 requirement_key VARCHAR(120) NULL,
 title VARCHAR(255) NULL,
 instructions MEDIUMTEXT NULL,
 status VARCHAR(40) DEFAULT 'required',
 priority INT DEFAULT 75,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_v110_mission (mission_uid),
 INDEX idx_v110_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS jessica_outreach_templates_v110 (
 id INT AUTO_INCREMENT PRIMARY KEY,
 template_key VARCHAR(100) NOT NULL,
 lead_type VARCHAR(80) NOT NULL,
 variation_no INT DEFAULT 1,
 subject_line VARCHAR(255) NULL,
 body_text MEDIUMTEXT NOT NULL,
 sender_name VARCHAR(120) DEFAULT 'Mark Pires',
 sender_email VARCHAR(190) DEFAULT 'mark@markpires.com',
 outward_identity VARCHAR(80) DEFAULT 'mark',
 internal_owner VARCHAR(80) DEFAULT 'jessica',
 is_active TINYINT DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_v110_template (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

$changed=[];
try{
 foreach($tables as $sql){gdb()->exec($sql);}
 $changed=['goliath_executive_activity_v110','goliath_required_handoffs_v110','jessica_outreach_templates_v110'];
 echo json_encode(['ok'=>true,'version'=>'V110.0 Cohesive Executive OS Migration','changed'=>$changed,'next'=>'Run /lead-engine/goliath-cohesion-engine-v110.php?key='.$key,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>