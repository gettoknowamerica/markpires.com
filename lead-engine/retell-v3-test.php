<?php
/**
 * V20.1.3 Retell V3 List Calls Test
 * Upload: /public_html/lead-engine/retell-v3-test.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/retell/retell-api-helper.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $key = $_GET['key'] ?? '';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)){
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  $r = retell_list_calls_v3(10);
  $items = retell_extract_items_v2013($r);
  echo json_encode([
    'success'=>$r['success'],
    'http'=>$r['http'] ?? null,
    'items_count'=>count($items),
    'has_more'=>$r['data']['has_more'] ?? null,
    'pagination_key'=>$r['data']['pagination_key'] ?? null,
    'sample_item'=>$items[0] ?? null,
    'raw'=>$r
  ], JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()], JSON_PRETTY_PRINT);
}
?>