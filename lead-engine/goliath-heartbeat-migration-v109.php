<?php
/**
 * Optional V109 support-table migration.
 */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals((string)$expected,$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
}

$changed=[];
try{
    gdb()->exec("
        CREATE TABLE IF NOT EXISTS goliath_runtime_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_uid VARCHAR(100) NULL,
            event_type VARCHAR(100) NULL,
            title VARCHAR(255) NULL,
            details MEDIUMTEXT NULL,
            status VARCHAR(60) NULL,
            metadata_json JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_runtime_event_type (event_type),
            INDEX idx_runtime_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $changed[]='goliath_runtime_events';
    echo json_encode([
        'ok'=>true,
        'version'=>'V109.0 Heartbeat Migration',
        'changed'=>$changed,
        'next'=>'Open /lead-engine/goliath-heartbeat-v109.php?key='.$key,
        'time'=>date('c')
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage(),
        'file'=>$e->getFile(),
        'line'=>$e->getLine()
    ],JSON_PRETTY_PRINT);
}
?>