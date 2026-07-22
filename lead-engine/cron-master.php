<?php
/**
 * V10.6 Master Cron — Full Intelligence Loop + Guarded Hunter
 * Upload to: /public_html/lead-engine/cron-master.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function cron_call_job($label, $file, $extra = '') {
  $host = $_SERVER['HTTP_HOST'] ?? (defined('SITE_DOMAIN') ? SITE_DOMAIN : 'markpires.com');
  $url = 'https://' . $host . '/lead-engine/' . $file . '?key=' . rawurlencode(AFTER_HOURS_CRON_KEY);
  if ($extra) $url .= '&' . ltrim($extra, '&');

  $started = microtime(true);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_TIMEOUT => 75
  ]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $decoded = json_decode($body, true);

  return [
    'label' => $label,
    'file' => $file,
    'ok' => $http >= 200 && $http < 300,
    'http' => $http,
    'seconds' => round(microtime(true) - $started, 3),
    'error' => $err,
    'data' => is_array($decoded) ? $decoded : null,
    'raw' => is_array($decoded) ? null : substr((string)$body, 0, 500)
  ];
}

$jobs=[];
$jobs['call_learning'] = cron_call_job('Cold Call Learning', 'process-call-outcomes.php', 'limit=200');
$jobs['future_sellers'] = cron_call_job('Future Seller Pipeline', 'build-future-sellers.php');
$jobs['hot_alerts'] = cron_call_job('Hot Lead Alerts', 'build-hot-alerts.php');
$jobs['action_queue'] = cron_call_job('Action Queue Builder', 'build-action-queue.php', 'limit=250');
$jobs['adaptive_intelligence'] = cron_call_job('Adaptive Intelligence', 'build-adaptive-intelligence.php');
$jobs['hunter_campaigns'] = cron_call_job('Hunter Campaign Builder', 'build-hunter-campaigns.php');
$jobs['hunter_queue'] = cron_call_job('Hunter Queue Builder', 'build-hunter-queue.php', 'daily=25');
$jobs['apply_scripts'] = cron_call_job('Apply Jessica Scripts', 'apply-jessica-scripts.php');
$jobs['jessica_priority'] = cron_call_job('Jessica Priority Queue', 'build-jessica-priority-queue.php');
$jobs['hunter_learning'] = cron_call_job('Hunter Learning', 'process-hunter-outcomes.php', 'limit=250');
$jobs['guarded_hunter_calls'] = cron_call_job('Guarded Hunter Calls', 'run-hunter-cron.php');
$jobs['after_hours'] = cron_call_job('After-Hours Callbacks', 'process-after-hours-callbacks.php');
$jobs['seed_followups'] = cron_call_job('Seed Followups', 'seed-followups.php', 'limit=25');

$allOk=true;
foreach($jobs as $job){ if(empty($job['ok'])) $allOk=false; }

echo json_encode([
  'success'=>$allOk,
  'ran_at'=>date('c'),
  'message'=>$allOk?'Master cron completed.':'Master cron completed with one or more job errors.',
  'jobs'=>$jobs
], JSON_PRETTY_PRINT);
?>