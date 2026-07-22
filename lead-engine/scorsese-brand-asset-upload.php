<?php
/**
 * V80.3 — Scorsese Logo / Brand Asset Upload
 * Uploads logo/graphic assets to /media-assets/scorsese/brand-assets/
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_POST['key'] ?? $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'success'=>false,'error'=>'bad_key']);exit;}

if(empty($_FILES['asset']) || !is_uploaded_file($_FILES['asset']['tmp_name'])){
  http_response_code(400); echo json_encode(['ok'=>false,'success'=>false,'error'=>'missing_asset']); exit;
}

$brand = strtolower(preg_replace('/[^a-z0-9_\-]+/','-', $_POST['brand'] ?? 'general'));
$brand = trim($brand,'-') ?: 'general';
$title = trim($_POST['title'] ?? $_FILES['asset']['name']);

$ext = strtolower(pathinfo($_FILES['asset']['name'], PATHINFO_EXTENSION));
$allowed = ['png','jpg','jpeg','webp','svg','gif','mp4','mov','pdf'];
if(!in_array($ext,$allowed,true)){
  http_response_code(400); echo json_encode(['ok'=>false,'success'=>false,'error'=>'file_type_not_allowed','allowed'=>$allowed]); exit;
}

$baseDir = realpath(__DIR__.'/..') . '/media-assets/scorsese/brand-assets/'.$brand;
if(!is_dir($baseDir)) mkdir($baseDir, 0755, true);

$safeTitle = preg_replace('/[^a-zA-Z0-9_\-]+/','-', pathinfo($_FILES['asset']['name'], PATHINFO_FILENAME));
$safeTitle = trim($safeTitle,'-') ?: 'asset';
$fileName = date('Ymd-His').'-'.$safeTitle.'.'.$ext;
$dest = $baseDir.'/'.$fileName;

if(!move_uploaded_file($_FILES['asset']['tmp_name'],$dest)){
  http_response_code(500); echo json_encode(['ok'=>false,'success'=>false,'error'=>'upload_failed']); exit;
}

$url = '/media-assets/scorsese/brand-assets/'.$brand.'/'.$fileName;

try{
  if(function_exists('gdb_enabled') && gdb_enabled()){
    gdb_exec("CREATE TABLE IF NOT EXISTS scorsese_brand_assets (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      brand_key VARCHAR(90) NOT NULL,
      title VARCHAR(255) NULL,
      file_url VARCHAR(500) NOT NULL,
      file_path VARCHAR(500) NOT NULL,
      file_type VARCHAR(40) NOT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'active',
      metadata LONGTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_brand (brand_key),
      KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    gdb_insert('scorsese_brand_assets',[
      'brand_key'=>$brand,
      'title'=>$title,
      'file_url'=>$url,
      'file_path'=>$dest,
      'file_type'=>$ext,
      'status'=>'active',
      'metadata'=>json_encode(['original_name'=>$_FILES['asset']['name'],'size'=>$_FILES['asset']['size']],JSON_UNESCAPED_SLASHES)
    ]);
  }
}catch(Throwable $e){}

echo json_encode(['ok'=>true,'success'=>true,'brand'=>$brand,'title'=>$title,'file_url'=>$url,'message'=>'Brand asset uploaded. Scorsese can reference this in future video prompts.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>