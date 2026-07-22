<?php
/**
 * V20.1.2 Large Chunk Upload Endpoint
 * Upload: /public_html/lead-engine/chunk-upload.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

try{
  $key = $_POST['key'] ?? $_GET['key'] ?? '';
  $session_ok = !empty($_SESSION['mp_dashboard_auth']);
  $key_ok = defined('AFTER_HOURS_CRON_KEY') && hash_equals(AFTER_HOURS_CRON_KEY, $key);
  if(!$session_ok && !$key_ok){
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
  }

  $uploadId = preg_replace('/[^a-zA-Z0-9_-]/','', $_POST['upload_id'] ?? '');
  $original = basename($_POST['filename'] ?? 'upload.bin');
  $chunkIndex = intval($_POST['chunk_index'] ?? -1);
  $totalChunks = intval($_POST['total_chunks'] ?? 0);

  if(!$uploadId || $chunkIndex < 0 || $totalChunks < 1 || empty($_FILES['chunk']['tmp_name'])){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing chunk data']);
    exit;
  }

  $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
  $allowed = ['mp4','mov','m4v','avi','webm','mkv','mp3','wav','m4a','aac','aiff','txt','srt','vtt','pdf','doc','docx'];
  if(!in_array($ext,$allowed,true)){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Unsupported file type']);
    exit;
  }

  $tmpBase = __DIR__ . '/../uploads/media/tmp';
  $rawBase = __DIR__ . '/../uploads/media/raw';
  if(!is_dir($tmpBase)) mkdir($tmpBase,0755,true);
  if(!is_dir($rawBase)) mkdir($rawBase,0755,true);

  $uploadDir = $tmpBase . '/' . $uploadId;
  if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);

  $chunkPath = $uploadDir . '/chunk_' . str_pad((string)$chunkIndex, 6, '0', STR_PAD_LEFT);
  if(!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Could not save chunk']);
    exit;
  }

  $received = count(glob($uploadDir.'/chunk_*'));
  if($received < $totalChunks){
    echo json_encode(['success'=>true,'complete'=>false,'received'=>$received,'total'=>$totalChunks]);
    exit;
  }

  $safeName = preg_replace('/[^a-zA-Z0-9._-]/','_', pathinfo($original, PATHINFO_FILENAME));
  $finalName = date('Ymd_His') . '_' . $safeName . '.' . $ext;
  $finalPath = $rawBase . '/' . $finalName;

  $out = fopen($finalPath,'wb');
  if(!$out){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Could not create final file']);
    exit;
  }

  for($i=0;$i<$totalChunks;$i++){
    $p = $uploadDir . '/chunk_' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
    if(!file_exists($p)){
      fclose($out);
      http_response_code(500);
      echo json_encode(['success'=>false,'error'=>'Missing chunk '.$i]);
      exit;
    }
    $in = fopen($p,'rb');
    stream_copy_to_stream($in,$out);
    fclose($in);
  }
  fclose($out);
  @chmod($finalPath,0644);

  foreach(glob($uploadDir.'/chunk_*') as $p) @unlink($p);
  @rmdir($uploadDir);

  echo json_encode([
    'success'=>true,
    'complete'=>true,
    'filename'=>$finalName,
    'url'=>'/uploads/media/raw/'.$finalName,
    'size'=>filesize($finalPath)
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>