<?php
/**
 * Goliath Omni V51 — Local AI Poller / Dispatcher
 * Server endpoint for the local desktop worker.
 * It claims queued local_ai_tasks and returns them to the local worker.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if (!hash_equals($expected, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'bad_key']);
  exit;
}

$limit = max(1, min(10, (int)($_GET['limit'] ?? 3)));

function glp_req($method, $endpoint, $payload=null){
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $raw = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($raw, true);
  return ['ok'=>($http>=200 && $http<300), 'http'=>$http, 'data'=>is_array($data)?$data:$raw, 'raw'=>$raw, 'error'=>$err];
}

function glp_log($agent, $title, $detail, $metadata=[]){
  return glp_req('POST', 'goliath_events', [
    'department' => $agent ?: 'Goliath',
    'event_type' => 'local_ai_dispatch',
    'title' => $title,
    'detail' => mb_substr((string)$detail,0,700),
    'roi_estimate' => 0,
    'confidence' => 90,
    'status' => 'active',
    'link_url' => '/dashboard/goliath-worker-output.php',
    'metadata' => $metadata
  ]);
}

// Load queued tasks. Supports older tables even if priority/model columns are missing.
$res = glp_req('GET', 'local_ai_tasks?select=*&status=eq.queued&order=created_at.asc&limit=' . $limit);
if (!$res['ok']) {
  echo json_encode(['success'=>false,'stage'=>'load_tasks','error'=>$res], JSON_PRETTY_PRINT);
  exit;
}
$tasks = is_array($res['data']) ? $res['data'] : [];
$claimed = [];

foreach ($tasks as $task) {
  $id = $task['id'] ?? null;
  if (!$id) continue;
  $metadata = $task['metadata'] ?? [];
  if (is_string($metadata)) $metadata = json_decode($metadata, true) ?: [];
  $agent = $metadata['agent'] ?? ($task['agent'] ?? 'Goliath');

  $patch = glp_req('PATCH', 'local_ai_tasks?id=eq.' . rawurlencode($id) . '&status=eq.queued', [
    'status' => 'working',
    'updated_at' => gmdate('c')
  ]);
  if (!$patch['ok']) continue;

  glp_log($agent, $agent . ' local AI task claimed', 'Local worker claimed task ' . $id, ['task_id'=>$id,'agent'=>$agent]);
  $task['status'] = 'working';
  $claimed[] = $task;
}

echo json_encode([
  'success' => true,
  'dispatcher' => 'Goliath Local AI Poller v1.0',
  'claimed_count' => count($claimed),
  'tasks' => $claimed,
  'next' => count($claimed) ? 'Run each task through Ollama/OpenClaw/Hermes and POST the result to local-ai-task-update.php.' : 'No queued local_ai_tasks available right now.'
], JSON_PRETTY_PRINT);
