<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function e991($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 e991("CREATE TABLE IF NOT EXISTS executive_council_sessions (id INT AUTO_INCREMENT PRIMARY KEY, session_uid VARCHAR(80) UNIQUE, session_date DATE, title VARCHAR(255), status VARCHAR(80) DEFAULT 'draft', meeting_summary MEDIUMTEXT, top_actions_json JSON NULL, audio_url TEXT NULL, replay_json JSON NULL, started_at DATETIME NULL, completed_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(session_date), INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 e991("CREATE TABLE IF NOT EXISTS os_open_tabs (id INT AUTO_INCREMENT PRIMARY KEY, tab_uid VARCHAR(80) UNIQUE, title VARCHAR(255), content_type VARCHAR(120), source_table VARCHAR(120), source_id INT NULL, payload_json JSON NULL, active TINYINT(1) DEFAULT 0, pinned TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(active), INDEX(source_table,source_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 try{e991("ALTER TABLE daily_briefs ADD COLUMN screen_payload_json JSON NULL");}catch(Throwable $ignore){}
 echo json_encode(['ok'=>true,'version'=>'V99.1 Cinematic OS Front Door Installer','created'=>['executive_council_sessions','os_open_tabs'],'next'=>'Run /lead-engine/executive-council-nightly-v99-1.php?key=timetomakethedonuts then open /dashboard/goliath-os.php','time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V99.1 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>