<?php
/**
 * Seed Followups V3 — clean duplicate handling
 * Upload to: /public_html/lead-engine/seed-followups-v3.php
 *
 * URL:
 * https://markpires.com/lead-engine/seed-followups-v3.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sbv3($method, $endpoint, $payload=null) {
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY
  ];

  if ($method === 'POST') {
    $headers[] = 'Prefer: resolution=ignore-duplicates,return=representation';
  } else {
    $headers[] = 'Prefer: return=representation';
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 25
  ]);

  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  $data = json_decode($body, true);
  return [
    'ok'=>$http>=200 && $http<300,
    'http'=>$http,
    'body'=>$body,
    'error'=>$err,
    'data'=>is_array($data)?$data:[]
  ];
}

function firstv3($name) {
  $name = trim((string)$name);
  if ($name === '') return 'there';
  $p = preg_split('/\s+/', $name);
  return $p[0] ?: 'there';
}

function render_tpl_v3($text, $lead) {
  $tokens = [
    '{{first_name}}' => firstv3($lead['name'] ?? ''),
    '{{name}}' => $lead['name'] ?? '',
    '{{town}}' => ($lead['town'] ?? '') ?: 'Fairfield County',
    '{{type}}' => $lead['type'] ?? '',
    '{{timeline}}' => $lead['timeline'] ?? '',
    '{{address}}' => $lead['address'] ?? ''
  ];
  return strtr((string)$text, $tokens);
}

function existing_keys_for_lead($email, $phone) {
  $clauses = [];
  if ($email) $clauses[] = 'lead_email=eq.' . rawurlencode($email);
  if ($phone) $clauses[] = 'lead_phone=eq.' . rawurlencode($phone);

  if (!$clauses) return [];

  // Query both email and phone separately for reliability.
  $keys = [];

  if ($email) {
    $res = sbv3('GET', 'lead_followup_queue?select=step_key&lead_email=eq.' . rawurlencode($email));
    foreach ($res['data'] as $r) if (!empty($r['step_key'])) $keys[$r['step_key']] = true;
  }

  if ($phone) {
    $res = sbv3('GET', 'lead_followup_queue?select=step_key&lead_phone=eq.' . rawurlencode($phone));
    foreach ($res['data'] as $r) if (!empty($r['step_key'])) $keys[$r['step_key']] = true;
  }

  return $keys;
}

$limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
$onlyEmail = $_GET['email'] ?? '';
$onlyPhone = $_GET['phone'] ?? '';

$leadEndpoint = 'leads?select=*&order=created_at.desc&limit=' . $limit;
if ($onlyEmail) $leadEndpoint = 'leads?select=*&email=eq.' . rawurlencode($onlyEmail) . '&limit=1';
if ($onlyPhone) $leadEndpoint = 'leads?select=*&phone=eq.' . rawurlencode($onlyPhone) . '&limit=1';

$leadsRes = sbv3('GET', $leadEndpoint);
$templatesRes = sbv3('GET', 'drip_sequence_templates?select=*&is_active=eq.true&order=step_order.asc');

$leads = $leadsRes['data'];
$templates = $templatesRes['data'];

$createdTotal = 0;
$duplicatesSkipped = 0;
$notApplicableSkipped = 0;
$errors = [];
$results = [];

foreach ($leads as $lead) {
  $email = $lead['email'] ?? '';
  $phone = $lead['phone'] ?? '';

  if (!$email && !$phone) {
    $results[] = ['lead'=>$lead['name'] ?? 'unknown', 'created'=>0, 'duplicates'=>0, 'skipped'=>'no email or phone'];
    continue;
  }

  $existing = existing_keys_for_lead($email, $phone);
  $payload = [];
  $leadDuplicates = 0;
  $leadNotApplicable = 0;

  foreach ($templates as $t) {
    $stepKey = $t['step_key'] ?? '';
    $channel = $t['channel'] ?? 'email';

    if (!$stepKey) continue;

    if (isset($existing[$stepKey])) {
      $duplicatesSkipped++;
      $leadDuplicates++;
      continue;
    }

    if ($channel === 'email' && !$email) {
      $notApplicableSkipped++;
      $leadNotApplicable++;
      continue;
    }

    if (($channel === 'sms' || $channel === 'voice') && !$phone) {
      $notApplicableSkipped++;
      $leadNotApplicable++;
      continue;
    }

    $applies = strtolower($t['applies_to'] ?? 'all');
    $leadType = strtolower($lead['type'] ?? '');

    if ($applies !== 'all' && $applies !== '' && strpos($leadType, $applies) === false) {
      $notApplicableSkipped++;
      $leadNotApplicable++;
      continue;
    }

    $delay = (int)($t['delay_days'] ?? 1);
    $hour = (int)($t['send_hour'] ?? 9);

    $payload[] = [
      'lead_email'=>$email,
      'lead_phone'=>$phone,
      'lead_name'=>$lead['name'] ?? '',
      'lead_type'=>$lead['type'] ?? '',
      'lead_source'=>$lead['tag'] ?? '',
      'page_url'=>$lead['page_url'] ?? '',
      'step_key'=>$stepKey,
      'channel'=>$channel,
      'subject'=>render_tpl_v3($t['subject'] ?? '', $lead),
      'message'=>render_tpl_v3($t['message'] ?? '', $lead),
      'scheduled_for'=>date('c', strtotime('+' . $delay . ' days ' . $hour . ':00')),
      'status'=>'queued',
      'raw_lead'=>$lead
    ];
  }

  $created = 0;

  if ($payload) {
    $res = sbv3('POST', 'lead_followup_queue', $payload);
    if ($res['ok']) {
      $created = is_array($res['data']) ? count($res['data']) : 0;
      // With ignore-duplicates, returned rows equal actually created rows.
      $createdTotal += $created;
    } else {
      $errors[] = [
        'lead'=>$lead['name'] ?? $email ?: $phone,
        'http'=>$res['http'],
        'body'=>$res['body'],
        'error'=>$res['error']
      ];
    }
  }

  $results[] = [
    'lead'=>$lead['name'] ?? $email ?: $phone,
    'created'=>$created,
    'duplicates_skipped'=>$leadDuplicates,
    'not_applicable_skipped'=>$leadNotApplicable
  ];
}

echo json_encode([
  'success'=>true,
  'summary'=>[
    'leads_checked'=>count($leads),
    'templates_active'=>count($templates),
    'created'=>$createdTotal,
    'duplicates_skipped'=>$duplicatesSkipped,
    'not_applicable_skipped'=>$notApplicableSkipped,
    'errors'=>count($errors)
  ],
  'results'=>$results,
  'errors'=>$errors
], JSON_PRETTY_PRINT);
