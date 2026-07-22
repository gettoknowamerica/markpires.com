<?php
/**
 * MarkPires Auto Action Builder V1
 * Upload to: /public_html/lead-engine/build-action-queue.php
 *
 * URL:
 * https://markpires.com/lead-engine/build-action-queue.php?key=YOUR_AFTER_HOURS_CRON_KEY
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb_auto($method, $endpoint, $payload=null) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  if ($method === 'POST') $headers[] = 'Prefer: resolution=ignore-duplicates,return=representation';
  else $headers[] = 'Prefer: return=representation';

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
  $data=json_decode($body,true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function action_exists($relatedType, $relatedId, $actionType) {
  if (!$relatedId) return false;
  $endpoint = 'mark_action_queue?select=id'
    . '&related_type=eq.' . rawurlencode($relatedType)
    . '&related_id=eq.' . rawurlencode($relatedId)
    . '&action_type=eq.' . rawurlencode($actionType)
    . '&limit=1';
  $res = sb_auto('GET', $endpoint);
  return !empty($res['data']);
}

function source_label_auto($lead) {
  $url = strtolower((string)($lead['page_url'] ?? ''));
  $tag = (string)($lead['tag'] ?? '');
  $type = (string)($lead['type'] ?? '');

  if (str_contains($url, '/towns/')) {
    if (preg_match('#/towns/([^/?]+)#', $url, $m)) return 'Town: ' . ucwords(str_replace('-', ' ', preg_replace('/\.html$/','',$m[1])));
    return 'Town Pages';
  }
  if (str_contains($url, '/blog/')) return 'Blog';
  if (str_contains($url, 'home-valuation') || $type === 'valuation') return 'Home Valuation';
  if ($tag) return $tag;
  if ($type) return $type;
  return 'Website';
}

function recommend_action_for_lead($lead) {
  $score = (int)($lead['lead_score'] ?? 0);
  $blob = strtolower(json_encode($lead));

  if (str_contains($blob, 'asap') || str_contains($blob, 'immediate') || str_contains($blob, 'today') || str_contains($blob, 'now')) {
    return ['priority'=>'hot','action_type'=>'call','due'=>date('c'), 'text'=>'Call immediately — urgent timing language in lead.'];
  }

  if ($score >= 90) {
    return ['priority'=>'hot','action_type'=>'call','due'=>date('c'), 'text'=>'Call immediately — 90+ lead score.'];
  }

  if ($score >= 75) {
    return ['priority'=>'high','action_type'=>'call','due'=>date('c', strtotime('+1 hour')), 'text'=>'Call today — high-quality lead.'];
  }

  if (!empty($lead['address']) && (str_contains($blob, 'valuation') || (($lead['type'] ?? '') === 'valuation'))) {
    return ['priority'=>'high','action_type'=>'cma','due'=>date('c', strtotime('+2 hours')), 'text'=>'Prepare quick CMA context, then call.'];
  }

  return null;
}

function recommend_action_for_call($call) {
  $blob = strtolower(json_encode($call));
  $score = (int)($call['lead_score'] ?? 0);

  if (!empty($call['appointment_requested'])) {
    return ['priority'=>'hot','action_type'=>'appointment','due'=>date('c'), 'text'=>'Call immediately — Jessica detected appointment/showing/listing language.'];
  }

  if (!empty($call['hot_lead']) || $score >= 90) {
    return ['priority'=>'hot','action_type'=>'call','due'=>date('c'), 'text'=>'Call immediately — hot Jessica call.'];
  }

  if (str_contains($blob, 'need mark') || str_contains($blob, 'speak with mark') || str_contains($blob, 'call me')) {
    return ['priority'=>'high','action_type'=>'call','due'=>date('c', strtotime('+30 minutes')), 'text'=>'Call back soon — caller asked for Mark or callback.'];
  }

  return null;
}

$limit = max(10, min(500, (int)($_GET['limit'] ?? 250)));

$leads = sb_auto('GET', 'leads?select=*&order=created_at.desc&limit=' . $limit)['data'];
$calls = sb_auto('GET', 'call_intelligence?select=*&order=created_at.desc&limit=' . $limit)['data'];
$after = sb_auto('GET', 'after_hours_callbacks?select=*&status=eq.queued&order=scheduled_for.asc&limit=100')['data'];

$created = [];
$skipped = [];

foreach ($leads as $lead) {
  $id = $lead['id'] ?? '';
  $rec = recommend_action_for_lead($lead);
  if (!$rec) {
    $skipped[] = ['type'=>'lead','name'=>$lead['name'] ?? '', 'reason'=>'not actionable'];
    continue;
  }

  if (action_exists('lead', $id, $rec['action_type'])) {
    $skipped[] = ['type'=>'lead','name'=>$lead['name'] ?? '', 'reason'=>'already exists'];
    continue;
  }

  $payload = [[
    'related_type'=>'lead',
    'related_id'=>$id,
    'name'=>$lead['name'] ?? '',
    'phone'=>$lead['phone'] ?? '',
    'email'=>$lead['email'] ?? '',
    'town'=>$lead['town'] ?? '',
    'address'=>$lead['address'] ?? '',
    'source'=>source_label_auto($lead),
    'priority'=>$rec['priority'],
    'action_type'=>$rec['action_type'],
    'recommended_action'=>$rec['text'],
    'notes'=>'Auto-created from lead score ' . ($lead['lead_score'] ?? '') . '. Type: ' . ($lead['type'] ?? '') . '. Timeline: ' . ($lead['timeline'] ?? ''),
    'status'=>'open',
    'due_at'=>$rec['due'],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res = sb_auto('POST','mark_action_queue',$payload);
  $created[] = ['type'=>'lead','name'=>$lead['name'] ?? '', 'ok'=>$res['ok'], 'http'=>$res['http']];
}

foreach ($calls as $call) {
  $id = $call['id'] ?? ($call['call_id'] ?? '');
  $rec = recommend_action_for_call($call);
  if (!$rec) {
    $skipped[] = ['type'=>'call','name'=>$call['name'] ?? '', 'reason'=>'not actionable'];
    continue;
  }

  if (action_exists('call', $id, $rec['action_type'])) {
    $skipped[] = ['type'=>'call','name'=>$call['name'] ?? '', 'reason'=>'already exists'];
    continue;
  }

  $payload = [[
    'related_type'=>'call',
    'related_id'=>$id,
    'name'=>$call['name'] ?? '',
    'phone'=>$call['phone'] ?? '',
    'email'=>$call['email'] ?? '',
    'town'=>$call['town'] ?? '',
    'address'=>$call['address'] ?? '',
    'source'=>$call['source'] ?? 'Jessica Call',
    'priority'=>$rec['priority'],
    'action_type'=>$rec['action_type'],
    'recommended_action'=>$rec['text'],
    'notes'=>'Auto-created from Jessica call. Summary: ' . substr((string)($call['summary'] ?? ''), 0, 500),
    'status'=>'open',
    'due_at'=>$rec['due'],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res = sb_auto('POST','mark_action_queue',$payload);
  $created[] = ['type'=>'call','name'=>$call['name'] ?? '', 'ok'=>$res['ok'], 'http'=>$res['http']];
}

foreach ($after as $row) {
  $id = $row['id'] ?? '';
  if (action_exists('after_hours', $id, 'review')) {
    $skipped[] = ['type'=>'after_hours','name'=>$row['name'] ?? '', 'reason'=>'already exists'];
    continue;
  }

  $payload = [[
    'related_type'=>'after_hours',
    'related_id'=>$id,
    'name'=>$row['name'] ?? '',
    'phone'=>$row['phone'] ?? '',
    'email'=>$row['email'] ?? '',
    'town'=>$row['town'] ?? '',
    'address'=>$row['address'] ?? '',
    'source'=>'After Hours Queue',
    'priority'=>((int)($row['lead_score'] ?? 0) >= 75 ? 'high' : 'normal'),
    'action_type'=>'review',
    'recommended_action'=>'Review after-hours lead before/after Jessica morning callback.',
    'notes'=>'Scheduled callback: ' . ($row['scheduled_for'] ?? ''),
    'status'=>'open',
    'due_at'=>$row['scheduled_for'] ?? date('c'),
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res = sb_auto('POST','mark_action_queue',$payload);
  $created[] = ['type'=>'after_hours','name'=>$row['name'] ?? '', 'ok'=>$res['ok'], 'http'=>$res['http']];
}

echo json_encode([
  'success'=>true,
  'summary'=>[
    'created'=>count(array_filter($created, fn($x)=>!empty($x['ok']))),
    'attempted'=>count($created),
    'skipped'=>count($skipped)
  ],
  'created'=>$created,
  'skipped'=>array_slice($skipped,0,50)
], JSON_PRETTY_PRINT);
