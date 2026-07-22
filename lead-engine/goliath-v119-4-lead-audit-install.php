<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
function k1194():string{if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);return 'timetomakethedonuts';}
$key=trim((string)($_GET['key']??''));if(!hash_equals(k1194(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 gdb()->exec("CREATE TABLE IF NOT EXISTS goliath_lead_capture_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audit_uid VARCHAR(96) NOT NULL UNIQUE,
  request_uid VARCHAR(96) NULL,
  lead_uid VARCHAR(96) NULL,
  crm_contact_id BIGINT UNSIGNED NULL,
  lead_id BIGINT UNSIGNED NULL,
  checkpoint VARCHAR(80) NOT NULL,
  status VARCHAR(40) NOT NULL,
  message LONGTEXT NULL,
  payload_json LONGTEXT NULL,
  source_url TEXT NULL,
  ip_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_request(request_uid,created_at),
  KEY idx_lead(lead_uid,created_at),
  KEY idx_status(status,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 echo json_encode(['ok'=>true,'version'=>'V119.4 Lead Capture Audit','next'=>'Upload lead-engine.js and capture.php, then submit one controlled test lead.'],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);}
?>
