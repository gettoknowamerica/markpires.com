<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'invalid key']);
  exit;
}

function normalize_us_phone($phone) {
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 10) return '+1' . $digits;
  if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') return '+' . $digits;
  return $digits ? '+' . $digits : '';
}

function first_name($name) {
  $name = trim((string)$name);
  if ($name === '') return 'there';
  $parts = preg_split('/\s+/', $name);
  return $parts[0] ?: 'there';
}

function supabase_request($method, $endpoint, $payload = null) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY
    ],
    CURLOPT_TIMEOUT => 20
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body, true);
  return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'body' => $body, 'error' => $error, 'data' => is_array($data) ? $data : []];
}

function create_retell_call_from_queue($lead) {
  $fromNumber = normalize_us_phone(RETELL_FROM_NUMBER);
  $toNumber = normalize_us_phone($lead['phone'] ?? '');
  $agentId = $lead['retell_agent_id'] ?: (defined('RETELL_AGENT_ID_MARK_PRIORITY') ? RETELL_AGENT_ID_MARK_PRIORITY : '');

  if (!$toNumber || !$agentId) return ['ok' => false, 'error' => 'Missing phone or agent id'];

  $dynamic = [
    'first_name' => first_name($lead['name'] ?? ''),
    'name' => $lead['name'] ?? '',
    'email' => $lead['email'] ?? '',
    'phone' => $toNumber,
    'address' => $lead['address'] ?? '',
    'town' => $lead['town'] ?? '',
    'timeline' => $lead['timeline'] ?? '',
    'goal' => $lead['goal'] ?? '',
    'route' => $lead['route'] ?? '',
    'lead_score' => (string)($lead['lead_score'] ?? ''),
    'source' => 'after_hours_morning_callback',
    'original_source' => $lead['source'] ?? '',
    'page_url' => $lead['page_url'] ?? ''
  ];

  $payload = [
    'from_number' => $fromNumber,
    'to_number' => $toNumber,
    'agent_id' => $agentId,
    'contact_dynamic_variables' => $dynamic,
    'metadata' => $dynamic
  ];

  $ch = curl_init('https://api.retellai.com/v2/create-phone-call');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . RETELL_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 20
  ]);

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  $decoded = json_decode($body, true);

  return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'body' => $body, 'error' => $error, 'payload' => $payload, 'call_id' => $decoded['call_id'] ?? ''];
}

$query = 'after_hours_callbacks?select=*&status=eq.queued&scheduled_for=lte.' . rawurlencode(date('c')) . '&order=scheduled_for.asc&limit=10';
$res = supabase_request('GET', $query);

$processed = [];
foreach ($res['data'] as $row) {
  $call = create_retell_call_from_queue($row);
  $status = $call['ok'] ? 'called' : 'retell_error';
  supabase_request('PATCH', 'after_hours_callbacks?id=eq.' . rawurlencode($row['id']), [
    'status' => $status,
    'attempted_at' => date('c'),
    'retell_call_id' => $call['call_id'] ?? '',
    'retell_response' => $call,
    'updated_at' => date('c')
  ]);
  $processed[] = ['id' => $row['id'], 'name' => $row['name'], 'phone' => $row['phone'], 'status' => $status, 'retell_ok' => $call['ok'], 'call_id' => $call['call_id'] ?? ''];
}

echo json_encode(['success' => true, 'queued_found' => count($res['data']), 'processed' => $processed], JSON_PRETTY_PRINT);
