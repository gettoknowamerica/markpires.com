<?php
/**
 * V8.0.2 Jessica Priority Queue Builder — string-offset safe
 * Upload to: /public_html/lead-engine/build-jessica-priority-queue.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle) {
    return $needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
  }
}

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function safe_rows_v802($res) {
  if (!is_array($res)) return [];
  if (isset($res['data']) && is_array($res['data'])) {
    return array_values(array_filter($res['data'], 'is_array'));
  }
  return [];
}

function safe_get_v802($arr, $key, $default='') {
  return is_array($arr) && array_key_exists($key, $arr) ? $arr[$key] : $default;
}

function sb8_v802($method, $endpoint, $payload=null) {
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[] = $method === 'POST'
    ? 'Prefer: resolution=ignore-duplicates,return=representation'
    : 'Prefer: return=representation';

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 45
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
    'data'=>is_array($data) ? $data : []
  ];
}

function src8_v802($lead) {
  if (!is_array($lead)) return 'Website';
  $url = strtolower((string)safe_get_v802($lead, 'page_url', ''));
  $type = strtolower((string)safe_get_v802($lead, 'type', ''));
  if (str_contains($url, '/towns/')) return 'Town Pages';
  if (str_contains($url, '/blog/')) return 'Blog';
  if (str_contains($url, 'valuation') || $type === 'valuation') return 'Home Valuation';
  return safe_get_v802($lead, 'tag', '') ?: (safe_get_v802($lead, 'type', '') ?: 'Website');
}

function priority8_v802($base, $adaptive, $bonus=0) {
  return max(0, min(150, (int)max((int)$base, (int)$adaptive) + (int)$bonus));
}

function exists8_v802($type, $id, $mission) {
  if (!$id) return false;
  $endpoint = 'jessica_priority_queue?select=id'
    . '&related_type=eq.' . rawurlencode($type)
    . '&related_id=eq.' . rawurlencode($id)
    . '&mission_type=eq.' . rawurlencode($mission)
    . '&status=in.(pending,queued)'
    . '&limit=1';
  $res = sb8_v802('GET', $endpoint);
  return !empty(safe_rows_v802($res));
}

function add_item8_v802(&$items, &$skipped, $payload) {
  $type = (string)safe_get_v802($payload, 'related_type', '');
  $id = (string)safe_get_v802($payload, 'related_id', '');
  $mission = (string)safe_get_v802($payload, 'mission_type', '');
  if (!$id) {
    $skipped[] = ['type'=>$type, 'reason'=>'missing related id'];
    return false;
  }
  if (exists8_v802($type, $id, $mission)) {
    $skipped[] = ['type'=>$type, 'reason'=>'already exists'];
    return false;
  }
  $payload['created_at'] = date('c');
  $payload['updated_at'] = date('c');
  $items[] = $payload;
  return true;
}

try {
  $items = [];
  $skipped = [];

  $leadsRes = sb8_v802('GET','leads?select=*&order=created_at.desc&limit=300');
  $futureRes = sb8_v802('GET','future_seller_pipeline?select=*&status=in.(active,queued)&order=next_followup_at.asc&limit=300');
  $homeownersRes = sb8_v802('GET','homeowner_intelligence?select=*&order=lead_score.desc&limit=300');
  $actionsRes = sb8_v802('GET','mark_action_queue?select=*&status=eq.open&order=due_at.asc&limit=200');

  $leads = safe_rows_v802($leadsRes);
  $future = safe_rows_v802($futureRes);
  $homeowners = safe_rows_v802($homeownersRes);
  $actions = safe_rows_v802($actionsRes);

  $source_status = [
    'leads'=>['ok'=>$leadsRes['ok'], 'http'=>$leadsRes['http'], 'rows'=>count($leads), 'body'=>$leadsRes['ok'] ? '' : $leadsRes['body']],
    'future_seller_pipeline'=>['ok'=>$futureRes['ok'], 'http'=>$futureRes['http'], 'rows'=>count($future), 'body'=>$futureRes['ok'] ? '' : $futureRes['body']],
    'homeowner_intelligence'=>['ok'=>$homeownersRes['ok'], 'http'=>$homeownersRes['http'], 'rows'=>count($homeowners), 'body'=>$homeownersRes['ok'] ? '' : $homeownersRes['body']],
    'mark_action_queue'=>['ok'=>$actionsRes['ok'], 'http'=>$actionsRes['http'], 'rows'=>count($actions), 'body'=>$actionsRes['ok'] ? '' : $actionsRes['body']]
  ];

  foreach ($leads as $lead) {
    $base = (int)safe_get_v802($lead, 'lead_score', 0);
    $adaptive = (int)safe_get_v802($lead, 'adaptive_score', $base);
    $type = strtolower((string)safe_get_v802($lead, 'type', ''));
    $blob = strtolower(json_encode($lead));

    $bonus = 0;
    $mission = 'HOT_LEAD';
    $action = 'Call today.';

    if ($base >= 90 || $adaptive >= 90) {
      $bonus += 15;
      $mission = 'CALL_NOW';
      $action = 'Call immediately — highest-priority inbound lead.';
    }

    if ($type === 'valuation' || str_contains(strtolower((string)safe_get_v802($lead, 'page_url', '')), 'valuation')) {
      $bonus += 20;
      $mission = 'VALUATION_FOLLOWUP';
      $action = 'Call and prepare quick valuation context.';
    }

    if (str_contains($blob, 'appointment') || str_contains($blob, 'consult')) {
      $bonus += 20;
      $mission = 'BOOK_APPOINTMENT';
      $action = 'Push toward appointment booking.';
    }

    $priority = priority8_v802($base, $adaptive, $bonus);
    if ($priority < 75) {
      $skipped[] = ['type'=>'lead','reason'=>'below threshold'];
      continue;
    }

    add_item8_v802($items, $skipped, [
      'related_type'=>'lead',
      'related_id'=>(string)safe_get_v802($lead, 'id', ''),
      'lead_id'=>(string)safe_get_v802($lead, 'id', ''),
      'name'=>safe_get_v802($lead, 'name', ''),
      'phone'=>safe_get_v802($lead, 'phone', ''),
      'email'=>safe_get_v802($lead, 'email', ''),
      'address'=>safe_get_v802($lead, 'address', ''),
      'town'=>safe_get_v802($lead, 'town', ''),
      'source'=>src8_v802($lead),
      'lead_type'=>safe_get_v802($lead, 'type', ''),
      'base_score'=>$base,
      'adaptive_score'=>$adaptive,
      'adaptive_adjustment'=>(int)safe_get_v802($lead, 'adaptive_adjustment', 0),
      'priority_score'=>$priority,
      'mission_type'=>$mission,
      'suggested_action'=>$action,
      'confidence'=>$priority >= 100 ? 'high' : 'medium',
      'reason'=>'Inbound lead ranked by adaptive score, source, urgency, and CTA type.',
      'call_by'=>$priority >= 100 ? date('c') : date('c', strtotime('+2 hours')),
      'raw_payload'=>$lead
    ]);
  }

  foreach ($future as $f) {
    $base = (int)safe_get_v802($f, 'lead_score', 0);
    $adaptive = max($base, (int)safe_get_v802($f, 'adaptive_score', 0));
    $nf = safe_get_v802($f, 'next_followup_at', '');
    $days = $nf ? floor((strtotime($nf) - time()) / 86400) : 90;

    $bonus = 25;
    if ($days <= 7) $bonus += 25;
    elseif ($days <= 30) $bonus += 15;
    if (safe_get_v802($f, 'priority', '') === 'hot') $bonus += 15;

    $priority = priority8_v802($base, $adaptive, $bonus);

    add_item8_v802($items, $skipped, [
      'related_type'=>'future_seller',
      'related_id'=>(string)safe_get_v802($f, 'id', ''),
      'future_seller_id'=>(string)safe_get_v802($f, 'id', ''),
      'homeowner_id'=>(string)safe_get_v802($f, 'homeowner_id', ''),
      'name'=>safe_get_v802($f, 'name', ''),
      'phone'=>safe_get_v802($f, 'phone', ''),
      'email'=>safe_get_v802($f, 'email', ''),
      'address'=>safe_get_v802($f, 'address', ''),
      'town'=>safe_get_v802($f, 'town', ''),
      'source'=>'Future Seller Pipeline',
      'lead_type'=>'future_seller',
      'base_score'=>$base,
      'adaptive_score'=>$adaptive,
      'priority_score'=>$priority,
      'mission_type'=>$days <= 14 ? 'CALL_NOW' : 'FUTURE_SELLER',
      'suggested_action'=>$days <= 14 ? 'Call or text personally before Jessica follow-up.' : 'Queue Jessica future-seller follow-up.',
      'confidence'=>$priority >= 100 ? 'high' : 'medium',
      'reason'=>'Future seller prioritized by timing and score.',
      'call_by'=>$nf ?: date('c', strtotime('+7 days')),
      'raw_payload'=>$f
    ]);
  }

  foreach ($homeowners as $h) {
    if (safe_get_v802($h, 'dnc_status', '') === 'listed') continue;

    $base = (int)safe_get_v802($h, 'lead_score', 0);
    $adaptive = (int)safe_get_v802($h, 'adaptive_score', $base);
    $years = (float)safe_get_v802($h, 'years_owned', 0);
    $equity = (float)safe_get_v802($h, 'estimated_equity', 0);

    $bonus = 0;
    if ($years >= 10) $bonus += 10;
    if ($years >= 20) $bonus += 10;
    if ($equity >= 500000) $bonus += 10;
    if ($equity >= 1000000) $bonus += 10;

    $priority = priority8_v802($base, $adaptive, $bonus);
    if ($priority < 85) {
      $skipped[] = ['type'=>'homeowner','reason'=>'below threshold'];
      continue;
    }

    add_item8_v802($items, $skipped, [
      'related_type'=>'homeowner',
      'related_id'=>(string)safe_get_v802($h, 'id', ''),
      'homeowner_id'=>(string)safe_get_v802($h, 'id', ''),
      'name'=>safe_get_v802($h, 'owner_name', ''),
      'phone'=>safe_get_v802($h, 'phone', ''),
      'email'=>safe_get_v802($h, 'email', ''),
      'address'=>safe_get_v802($h, 'address', ''),
      'town'=>safe_get_v802($h, 'town', ''),
      'source'=>'Homeowner Intelligence',
      'lead_type'=>'cold_homeowner',
      'base_score'=>$base,
      'adaptive_score'=>$adaptive,
      'adaptive_adjustment'=>(int)safe_get_v802($h, 'adaptive_adjustment', 0),
      'priority_score'=>$priority,
      'mission_type'=>'HOMEOWNER_PROSPECT',
      'suggested_action'=>'Add to reviewed Jessica outreach list after DNC check.',
      'confidence'=>$priority >= 105 ? 'high' : 'medium',
      'reason'=>'Homeowner ranked by adaptive score, years owned, equity, and town learning.',
      'call_by'=>date('c', strtotime('+1 day 10:00')),
      'raw_payload'=>$h
    ]);
  }

  foreach ($actions as $a) {
    $pri = strtolower((string)safe_get_v802($a, 'priority', ''));
    $priority = 80 + ($pri === 'hot' ? 30 : ($pri === 'high' ? 18 : 8));

    add_item8_v802($items, $skipped, [
      'related_type'=>'action_queue',
      'related_id'=>(string)safe_get_v802($a, 'id', ''),
      'action_queue_id'=>(string)safe_get_v802($a, 'id', ''),
      'name'=>safe_get_v802($a, 'name', ''),
      'phone'=>safe_get_v802($a, 'phone', ''),
      'email'=>safe_get_v802($a, 'email', ''),
      'address'=>safe_get_v802($a, 'address', ''),
      'town'=>safe_get_v802($a, 'town', ''),
      'source'=>safe_get_v802($a, 'source', 'Action Queue'),
      'lead_type'=>safe_get_v802($a, 'action_type', 'task'),
      'base_score'=>$priority,
      'adaptive_score'=>$priority,
      'priority_score'=>$priority,
      'mission_type'=>strtolower((string)safe_get_v802($a, 'action_type', '')) === 'appointment' ? 'BOOK_APPOINTMENT' : 'CALL_NOW',
      'suggested_action'=>safe_get_v802($a, 'recommended_action', 'Review and call.'),
      'confidence'=>$priority >= 100 ? 'high' : 'medium',
      'reason'=>'Open Mark action queue task promoted into Jessica mission control.',
      'call_by'=>safe_get_v802($a, 'due_at', date('c')),
      'raw_payload'=>$a
    ]);
  }

  $inserted = [];
  $errors = [];

  foreach (array_chunk($items, 100) as $chunk) {
    $res = sb8_v802('POST','jessica_priority_queue',$chunk);
    if ($res['ok']) $inserted[] = ['count'=>count($chunk),'http'=>$res['http']];
    else $errors[] = ['http'=>$res['http'],'body'=>$res['body'],'error'=>$res['error']];
  }

  echo json_encode([
    'success'=>empty($errors),
    'attempted'=>count($items),
    'inserted_batches'=>$inserted,
    'source_status'=>$source_status,
    'errors'=>$errors,
    'skipped'=>array_slice($skipped,0,80)
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'error'=>'PHP exception',
    'message'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ], JSON_PRETTY_PRINT);
}
