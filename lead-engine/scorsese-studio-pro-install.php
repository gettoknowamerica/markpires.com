<?php
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php'; header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
gdb_exec("CREATE TABLE IF NOT EXISTS scorsese_studio_projects (id INT AUTO_INCREMENT PRIMARY KEY, project_uid VARCHAR(80) UNIQUE, title VARCHAR(255), production_type VARCHAR(80), goal VARCHAR(160), platform VARCHAR(160), aspect_ratio VARCHAR(20), duration VARCHAR(60), style_pack VARCHAR(120), brief MEDIUMTEXT, cta TEXT, status VARCHAR(40) DEFAULT 'queued', progress INT DEFAULT 0, commission_id INT NULL, metadata JSON NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
gdb_exec("CREATE TABLE IF NOT EXISTS scorsese_storyboard_scenes (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT, scene_number INT, title VARCHAR(255), shot_type VARCHAR(255), camera_move VARCHAR(255), prompt MEDIUMTEXT, status VARCHAR(40) DEFAULT 'draft', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(project_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo json_encode(['ok'=>true,'version'=>'V90 Scorsese Studio Pro Install','tables'=>['scorsese_studio_projects','scorsese_storyboard_scenes'],'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>