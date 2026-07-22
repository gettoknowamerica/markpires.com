<?php
/**
 * MarkPires DNC Index Builder V2
 * Upload to: /public_html/lead-engine/dnc-build-index.php
 *
 * Purpose:
 * Converts a huge raw National DNC file into fast lookup buckets.
 *
 * Input:
 * /lead-engine/dnc/dnc-national.txt
 *
 * Supports:
 * 203,9999999
 * 2039999999
 * +12039999999
 *
 * Output:
 * /lead-engine/dnc/index/203/99.txt
 * /lead-engine/dnc/index/475/12.txt
 * etc.
 *
 * Run manually in browser:
 * https://markpires.com/lead-engine/dnc-build-index.php?key=YOUR_DNC_BUILD_KEY
 *
 * Add to config.php:
 * define('DNC_BUILD_KEY', 'make-a-long-random-password-here');
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/dnc-check.php';

header('Content-Type: text/plain; charset=utf-8');

if (!defined('DNC_BUILD_KEY') || !DNC_BUILD_KEY) {
  http_response_code(403);
  echo "DNC_BUILD_KEY missing in config.php\n";
  exit;
}

$key = $_GET['key'] ?? '';
if (!hash_equals(DNC_BUILD_KEY, $key)) {
  http_response_code(403);
  echo "Invalid key\n";
  exit;
}

$rawFile = __DIR__ . '/dnc/dnc-national.txt';
$indexRoot = __DIR__ . '/dnc/index';

if (!file_exists($rawFile)) {
  http_response_code(404);
  echo "Missing raw file: {$rawFile}\n";
  exit;
}

if (!is_dir($indexRoot)) {
  mkdir($indexRoot, 0755, true);
}

$handle = fopen($rawFile, 'r');
if (!$handle) {
  http_response_code(500);
  echo "Could not open raw DNC file\n";
  exit;
}

$buffers = [];
$count = 0;
$valid = 0;
$skipped = 0;
$flushEvery = 50000;

function mp_dnc_flush_buffers(&$buffers) {
  foreach ($buffers as $path => $lines) {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, implode('', $lines), FILE_APPEND);
  }
  $buffers = [];
}

while (($line = fgets($handle)) !== false) {
  $count++;
  $digits = mp_dnc_normalize_phone_digits($line);

  if (strlen($digits) !== 10) {
    $skipped++;
    continue;
  }

  $area = substr($digits, 0, 3);
  $suffix = substr($digits, 3);
  $bucket = substr($suffix, 0, 2);
  $path = $indexRoot . '/' . $area . '/' . $bucket . '.txt';

  if (!isset($buffers[$path])) $buffers[$path] = [];
  $buffers[$path][] = $digits . PHP_EOL;
  $valid++;

  if ($count % $flushEvery === 0) {
    mp_dnc_flush_buffers($buffers);
    echo "Processed {$count} lines...\n";
    @ob_flush();
    @flush();
  }
}

fclose($handle);
mp_dnc_flush_buffers($buffers);

$meta = [
  'built_at' => date('c'),
  'raw_file' => $rawFile,
  'processed_lines' => $count,
  'valid_numbers' => $valid,
  'skipped_lines' => $skipped
];

file_put_contents($indexRoot . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

echo "DONE\n";
echo "Processed lines: {$count}\n";
echo "Valid numbers: {$valid}\n";
echo "Skipped lines: {$skipped}\n";
echo "Index root: {$indexRoot}\n";
