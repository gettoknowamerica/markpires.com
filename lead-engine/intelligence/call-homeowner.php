<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../dnc-check.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function redirect_back($status, $message) {
  header('Location: /dashboard/?jessica_status=' . urlencode($status) . '&jessica_msg=' . urlencode($message));
  exit;
}

function normalize_us_phone_hi($phone) {
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 10) return '+1' . $digits;
  if (strlen($digits) === 11 && substr($digits,0,1)==='1') return '+' . $digits;
  return $digits ? '+' . $digits : '';
}

function first_name_hi($name) {
  $name = trim((string)$name);
  if ($name === '') return 'there';
  $parts = preg_split('/\s+/', $name);
  return $parts[0] ?: 'there';
}

function supabase_hi_request($method, $endpoint, $payload = null) {
  if (!defined('SUPABASE_URL') || !SUPABASE_URL || !defined('SUPABASE_SERVICE_ROLE_KEY') || !SUPABASE_SERVICE_ROLE_KEY) {
    return ['ok'=>false, 'error'=>'Supabase not configured'];
  }

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
  $err = curl_error($ch);
  curl_close($ch);

  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300, 'http'=>$http, 'body'=>$body, 'error'=>$err, 'data'=>is_array($data)?$data:[]];
}

function update_homeowner_status($id, $fields) {
  $fields['updated_at'] = date('c');
  return supabase_hi_request('PATCH', 'homeowner_intelligence?id=eq.' . rawurlencode($id), $fields);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_back('error', 'POST only.');

$id = $_POST['homeowner_id'] ?? '';
if (!$id) redirect_back('error', 'Missing homeowner ID.');

$get = supabase_hi_request('GET', 'homeowner_intelligence?select=*&id=eq.' . rawurlencode($id) . '&limit=1');
if (!$get['ok'] || empty($get['data'][0])) redirect_back('error', 'Could not load homeowner record.');

$r = $get['data'][0];

$toNumber = normalize_us_phone_hi($r['phone'] ?? '');
if (!$toNumber || strlen(preg_replace('/\D+/', '', $toNumber)) < 11) {
  update_homeowner_status($id, ['status'=>'invalid_phone']);
  redirect_back('error', 'Invalid phone number.');
}

$dnc = function_exists('mp_is_dnc_number') ? mp_is_dnc_number($toNumber) : ['is_dnc'=>false,'reason'=>'not_checked'];
if (!empty($dnc['is_dnc'])) {
  update_homeowner_status($id, [
    'status' => 'dnc_suppressed',
    'dnc_status' => 'listed',
    'dnc_reason' => $dnc['reason'] ?? 'matched',
    'last_contacted_at' => date('c')
  ]);
  redirect_back('error', 'Blocked: number is listed on DNC. Jessica did not call.');
}

if (!defined('RETELL_API_KEY') || !RETELL_API_KEY) redirect_back('error', 'RETELL_API_KEY missing.');
if (!defined('RETELL_FROM_NUMBER') || !RETELL_FROM_NUMBER) redirect_back('error', 'RETELL_FROM_NUMBER missing.');

$agentId = defined('RETELL_AGENT_ID_COLD_HOMEOWNER') && RETELL_AGENT_ID_COLD_HOMEOWNER
  ? RETELL_AGENT_ID_COLD_HOMEOWNER
  : (defined('RETELL_AGENT_ID_MARK_PRIORITY') ? RETELL_AGENT_ID_MARK_PRIORITY : '');

if (!$agentId) redirect_back('error', 'No Retell cold homeowner or Mark priority agent ID configured.');

$fromNumber = normalize_us_phone_hi(RETELL_FROM_NUMBER);

$dynamic = [
  'first_name' => first_name_hi($r['owner_name'] ?? ''),
  'name' => $r['owner_name'] ?? '',
  'phone' => $toNumber,
  'email' => $r['email'] ?? '',
  'address' => $r['address'] ?? '',
  'town' => $r['town'] ?? '',
  'property_type' => $r['property_type'] ?? '',
  'purchase_date' => $r['purchase_date'] ?? '',
  'years_owned' => (string)($r['years_owned'] ?? ''),
  'estimated_value' => (string)($r['estimated_value'] ?? ''),
  'estimated_equity' => (string)($r['estimated_equity'] ?? ''),
  'lead_score' => (string)($r['lead_score'] ?? ''),
  'priority' => $r['priority'] ?? '',
  'motivation_signal' => $r['motivation_signal'] ?? '',
  'notes' => $r['notes'] ?? '',
  'source' => 'homeowner_intelligence_dashboard',
  'call_type' => 'reviewed_cold_homeowner'
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
$err = curl_error($ch);
curl_close($ch);

$decoded = json_decode($body, true);
$callId = $decoded['call_id'] ?? '';

if ($http >= 200 && $http < 300) {
  update_homeowner_status($id, [
    'status' => 'jessica_call_registered',
    'dnc_status' => 'clear',
    'dnc_reason' => $dnc['reason'] ?? '',
    'last_contacted_at' => date('c'),
    'notes' => trim(($r['notes'] ?? '') . "\n\nJessica call registered: " . $callId)
  ]);
  redirect_back('success', 'Jessica call registered for ' . ($r['owner_name'] ?: $toNumber));
}

update_homeowner_status($id, [
  'status' => 'retell_error',
  'last_contacted_at' => date('c'),
  'notes' => trim(($r['notes'] ?? '') . "\n\nRetell error: HTTP {$http} {$body} {$err}")
]);

redirect_back('error', 'Retell error: HTTP ' . $http);
