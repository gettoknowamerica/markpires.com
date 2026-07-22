<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
gdb_exec("CREATE TABLE IF NOT EXISTS goliath_lead_timeline (id INT AUTO_INCREMENT PRIMARY KEY,event_uid VARCHAR(80) UNIQUE,lead_uid VARCHAR(80),crm_contact_id INT NULL,actor VARCHAR(80),event_type VARCHAR(120),title VARCHAR(255),details MEDIUMTEXT,status VARCHAR(60) DEFAULT 'complete',metadata JSON NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,INDEX(lead_uid),INDEX(crm_contact_id),INDEX(event_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
gdb_exec("CREATE TABLE IF NOT EXISTS goliath_callback_tasks (id INT AUTO_INCREMENT PRIMARY KEY,task_uid VARCHAR(80) UNIQUE,lead_uid VARCHAR(80),crm_contact_id INT NULL,title VARCHAR(255),contact_name VARCHAR(255),phone VARCHAR(80),email VARCHAR(255),town VARCHAR(120),lead_type VARCHAR(120),priority INT DEFAULT 5,status VARCHAR(60) DEFAULT 'queued',scheduled_for DATETIME NULL,calendar_status VARCHAR(60) DEFAULT 'pending',notes MEDIUMTEXT,metadata JSON NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(lead_uid),INDEX(status),INDEX(scheduled_for)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
gdb_exec("CREATE TABLE IF NOT EXISTS goliath_revenue_engine_failures (id INT AUTO_INCREMENT PRIMARY KEY,failure_uid VARCHAR(80) UNIQUE,lead_uid VARCHAR(80),service VARCHAR(120),severity VARCHAR(40) DEFAULT 'warning',message TEXT,payload JSON NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,INDEX(lead_uid),INDEX(service)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo json_encode(['ok'=>true,'version'=>'V93.1 Revenue Engine Install','tables'=>['goliath_lead_timeline','goliath_callback_tasks','goliath_revenue_engine_failures'],'next'=>'Upload capture.php, crm-write.php, mission-trigger.php, jessica-dispatch.php. Then test a form.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>
