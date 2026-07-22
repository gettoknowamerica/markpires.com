<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 gdb()->exec("CREATE TABLE IF NOT EXISTS goliath_media_intake_v113 (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  media_uid VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  brand_key VARCHAR(100) NULL,
  instructions MEDIUMTEXT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  public_url VARCHAR(500) NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT DEFAULT 0,
  duration_seconds DECIMAL(10,2) NULL,
  status VARCHAR(50) DEFAULT 'queued',
  scorsese_job_id BIGINT NULL,
  mission_id BIGINT NULL,
  requested_outputs_json JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_uid(media_uid),
  KEY idx_media_status(status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 echo json_encode(['ok'=>true,'version'=>'V113.0 Media Intake Install','table'=>'goliath_media_intake_v113'],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);}
?>