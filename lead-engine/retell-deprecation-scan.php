<?php
/**
 * V20.1.3 Retell Deprecated Endpoint Scanner
 * Upload: /public_html/lead-engine/retell-deprecation-scan.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $key = $_GET['key'] ?? '';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)){
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  $root = realpath(__DIR__ . '/..'); // public_html
  $needles = [
    '/v2/list-calls',
    'v2/list-calls',
    '/list-chat',
    '/list-phone-numbers',
    '/list-retell-llms',
    'analysis_summary_prompt',
    'analysis_successful_prompt',
    'analysis_user_sentiment_prompt'
  ];

  $hits = [];
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
  foreach($rii as $file){
    if(!$file->isFile()) continue;
    $path = $file->getPathname();
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if(!in_array($ext, ['php','js','json','txt','md'], true)) continue;
    if(strpos($path, '/uploads/') !== false) continue;

    $lines = @file($path);
    if(!$lines) continue;

    foreach($lines as $i=>$line){
      foreach($needles as $needle){
        if(stripos($line, $needle) !== false){
          $hits[] = [
            'file' => str_replace($root, '', $path),
            'line' => $i + 1,
            'match' => $needle,
            'text' => trim($line)
          ];
        }
      }
    }
  }

  echo json_encode([
    'success'=>true,
    'root'=>$root,
    'hit_count'=>count($hits),
    'hits'=>$hits,
    'fix'=>'Replace POST /v2/list-calls with POST /v3/list-calls and read response.data.items instead of expecting a top-level array.'
  ], JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()], JSON_PRETTY_PRINT);
}
?>