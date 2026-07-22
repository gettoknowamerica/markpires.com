<?php
/**
 * V14.4A-PRO Retell Webhook Super Merge
 * Upload over: /public_html/lead-engine/retell/webhook.php
 *
 * Preserves original webhook features:
 * - POST-only Retell webhook handling
 * - call_intelligence upsert
 * - lead scoring / hot lead detection
 * - appointment detection
 * - motivation detection
 * - Resend hot-lead email alerts
 * - webhook logging
 *
 * Adds V14.4A Executive Concierge:
 * - business_call_log
 * - business_call_actions
 * - voice_intelligence_events mirror
 * - forwarded Mark-line business concierge mode
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function ci_log($service, $status, $details = []) {
  $path = defined('LOG_PATH') ? LOG_PATH : (__DIR__ . '/../logs');
  if (!is_dir($path)) @mkdir($path, 0755, true);
  file_put_contents(
    $path . '/retell-webhook-' . date('Y-m') . '.log',
    json_encode(['time'=>date('c'),'service'=>$service,'status'=>$status,'details'=>$details]) . PHP_EOL,
    FILE_APPEND
  );
}

function clean_text_ci($v) {
  if (is_array($v) || is_object($v)) return '';
  return trim((string)$v);
}

function digits_ci($phone) {
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 11 && substr($digits,0,1)==='1') return '+'.$digits;
  if (strlen($digits) === 10) return '+1'.$digits;
  return $digits ? '+'.$digits : '';
}

function digits10_ci($phone) {
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 11 && substr($digits,0,1)==='1') return substr($digits,1);
  return $digits;
}

function get_path_ci($arr, $paths, $default = '') {
  foreach ($paths as $path) {
    $cur = $arr;
    $ok = true;
    foreach (explode('.', $path) as $part) {
      if (is_array($cur) && array_key_exists($part, $cur)) $cur = $cur[$part];
      else { $ok = false; break; }
    }
    if ($ok && $cur !== null && $cur !== '') return $cur;
  }
  return $default;
}

function duration_ci($payload) {
  foreach (['duration_ms','total_duration_ms'] as $k) {
    if (isset($payload[$k]) && is_numeric($payload[$k])) return (int)round(((int)$payload[$k])/1000);
  }
  foreach (['duration_sec','duration_seconds','total_duration_seconds','call_cost.total_duration_seconds'] as $k) {
    $v = get_path_ci($payload, [$k], null);
    if ($v !== null && is_numeric($v)) return (int)$v;
  }
  return 0;
}

function analyze_call_ci($text, $metadata = []) {
  $blob = strtolower($text . ' ' . json_encode($metadata));

  $score = 25;
  $hot = false;
  $appt = false;
  $motivation = [];

  $hotTerms = [
    'sell now','selling now','list now','list my house','need to sell','need a realtor',
    'can mark come','come today','come tomorrow','appointment','meet mark',
    'interviewing agents','listing appointment','as soon as possible','asap',
    'pre-approved','preapproved','cash buyer','offer','urgent','valuation','home value'
  ];

  foreach ($hotTerms as $t) {
    if (strpos($blob, $t) !== false) { $score += 18; $hot = true; }
  }

  $apptTerms = ['appointment','meet','come today','come tomorrow','schedule','listing appointment','can mark come','call me now','speak with mark'];
  foreach ($apptTerms as $t) {
    if (strpos($blob, $t) !== false) { $appt = true; $score += 15; break; }
  }

  $signals = [
    'relocation' => ['relocation','moving from','job transfer','moving to connecticut','moving to ct'],
    'downsizing' => ['downsizing','downsize','empty nest','retiring','retirement'],
    'inherited' => ['inherited','inheritance','estate','probate'],
    'divorce' => ['divorce','separation'],
    'investor' => ['investment','investor','multifamily','multi-family','1031','rental','portfolio'],
    'luxury' => ['luxury','waterfront','greenwich','westport','darien','new canaan','estate','million']
  ];

  foreach ($signals as $label=>$terms) {
    foreach ($terms as $t) {
      if (strpos($blob, $t) !== false) { $motivation[] = $label; $score += 10; break; }
    }
  }

  $score = min(100, $score);
  $priority = $score >= 90 ? 'hot' : ($score >= 75 ? 'high' : ($score >= 55 ? 'watch' : 'nurture'));

  return [
    'lead_score' => $score,
    'priority' => $priority,
    'hot_lead' => $hot || $score >= 90,
    'appointment_requested' => $appt,
    'motivation' => implode(', ', array_unique($motivation))
  ];
}

function classify_business_call_ci($text) {
  $t = strtolower($text);
  $cat = 'unknown';
  $score = 20;
  $priority = 'normal';
  $lead = false;
  $callback = false;
  $appt = false;

  if (strpos($t,'sell') !== false || strpos($t,'seller') !== false || strpos($t,'home value') !== false || strpos($t,'valuation') !== false || strpos($t,'listing') !== false) {
    $cat = 'seller'; $score += 45; $lead = true;
  } elseif (strpos($t,'buy') !== false || strpos($t,'buyer') !== false || strpos($t,'showing') !== false || strpos($t,'house') !== false) {
    $cat = 'buyer'; $score += 35; $lead = true;
  } elseif (strpos($t,'attorney') !== false || strpos($t,'lawyer') !== false || strpos($t,'closing') !== false) {
    $cat = 'attorney'; $score += 20;
  } elseif (strpos($t,'mortgage') !== false || strpos($t,'lender') !== false || strpos($t,'loan') !== false) {
    $cat = 'mortgage'; $score += 18;
  } elseif (strpos($t,'agent') !== false || strpos($t,'realtor') !== false || strpos($t,'broker') !== false || strpos($t,'mls') !== false) {
    $cat = 'agent'; $score += 18;
  } elseif (strpos($t,'vendor') !== false || strpos($t,'photographer') !== false || strpos($t,'contractor') !== false || strpos($t,'marketing') !== false || strpos($t,'seo') !== false) {
    $cat = 'vendor'; $score += 8;
  } elseif (strpos($t,'friend') !== false || strpos($t,'personal') !== false || strpos($t,'family') !== false) {
    $cat = 'personal'; $score += 5;
  }

  if (strpos($t,'call back') !== false || strpos($t,'callback') !== false || strpos($t,'return my call') !== false || strpos($t,'please call') !== false) {
    $callback = true; $score += 15;
  }

  if (strpos($t,'appointment') !== false || strpos($t,'schedule') !== false || strpos($t,'meet') !== false || strpos($t,'consultation') !== false) {
    $appt = true; $callback = true; $score += 25;
  }

  if (strpos($t,'urgent') !== false || strpos($t,'asap') !== false || strpos($t,'today') !== false || strpos($t,'right away') !== false) {
    $priority = 'urgent'; $score += 20;
  } elseif ($score >= 70) {
    $priority = 'high';
  } elseif ($score < 30) {
    $priority = 'low';
  }

  $score = max(0, min(100, $score));

  $action = 'Review call details.';
  if ($cat === 'seller') $action = 'Mark should review and call back as a seller/listing opportunity.';
  elseif ($cat === 'buyer') $action = 'Mark should review buyer needs and follow up.';
  elseif ($cat === 'agent') $action = 'Review agent/realtor request and respond if needed.';
  elseif ($cat === 'vendor') $action = 'Review vendor request only if relevant.';
  elseif ($callback) $action = 'Call back today.';

  return [
    'cat'=>$cat,
    'score'=>$score,
    'priority'=>$priority,
    'lead'=>$lead,
    'callback'=>$callback,
    'appt'=>$appt,
    'action'=>$action
  ];
}

function supabase_ci_request($method, $endpoint, $payload = null, $prefer = 'return=representation') {
  if (!defined('SUPABASE_URL') || !SUPABASE_URL || !defined('SUPABASE_SERVICE_ROLE_KEY') || !SUPABASE_SERVICE_ROLE_KEY) {
    return ['ok'=>false,'error'=>'Supabase not configured'];
  }

  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  $headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Prefer: ' . $prefer
  ];

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 20
  ]);

  if ($payload !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  }

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function supabase_ci_upsert_call_intelligence($record) {
  return supabase_ci_request(
    'POST',
    'call_intelligence?on_conflict=call_id',
    [$record],
    'resolution=merge-duplicates,return=representation'
  );
}

function send_hot_alert_ci($record) {
  if (empty($record['hot_lead']) && empty($record['appointment_requested'])) return ['ok'=>false,'skipped'=>true,'reason'=>'not_hot'];

  if (!defined('RESEND_API_KEY') || !RESEND_API_KEY || !defined('MARK_EMAIL')) {
    return ['ok'=>false,'error'=>'Resend not configured'];
  }

  $fromEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'no-reply@markpires.com';
  $subject = '🔥 HOT Jessica Lead — ' . ($record['name'] ?: $record['phone'] ?: 'Unknown');

  $html = '<h2>🔥 Hot Jessica Lead</h2>'
    . '<p><strong>Name:</strong> ' . htmlspecialchars($record['name'] ?? '') . '</p>'
    . '<p><strong>Phone:</strong> ' . htmlspecialchars($record['phone'] ?? '') . '</p>'
    . '<p><strong>Town:</strong> ' . htmlspecialchars($record['town'] ?? '') . '</p>'
    . '<p><strong>Address:</strong> ' . htmlspecialchars($record['address'] ?? '') . '</p>'
    . '<p><strong>Score:</strong> ' . htmlspecialchars((string)($record['lead_score'] ?? '')) . '</p>'
    . '<p><strong>Priority:</strong> ' . htmlspecialchars($record['priority'] ?? '') . '</p>'
    . '<p><strong>Motivation:</strong> ' . htmlspecialchars($record['motivation'] ?? '') . '</p>'
    . '<p><strong>Appointment Requested:</strong> ' . (!empty($record['appointment_requested']) ? 'YES' : 'No') . '</p>'
    . '<p><strong>Summary:</strong><br>' . nl2br(htmlspecialchars($record['summary'] ?? '')) . '</p>'
    . '<p><strong>Recording:</strong> ' . (!empty($record['recording_url']) ? '<a href="'.htmlspecialchars($record['recording_url']).'">Listen</a>' : 'None') . '</p>';

  $payload = [
    'from' => (defined('MARK_NAME') ? MARK_NAME : 'Mark Pires') . ' <' . $fromEmail . '>',
    'to' => [MARK_EMAIL],
    'subject' => $subject,
    'html' => $html
  ];

  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . RESEND_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 15
  ]);

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

function save_business_concierge_ci($record, $payload, $call) {
  $combined = ($record['summary'] ?? '') . "\n" . ($record['transcript'] ?? '') . "\n" . json_encode($payload);
  $class = classify_business_call_ci($combined);

  $from10 = digits10_ci($record['from_number'] ?? '');
  $isMarkForwarded = ($from10 === '2032472655');

  $business = [[
    'call_date' => date('c'),
    'retell_call_id' => $record['call_id'] ?? '',
    'call_sid' => clean_text_ci(get_path_ci($call, ['twilio_call_sid','call_sid'], '')),
    'from_number' => $from10,
    'to_number' => digits10_ci($record['to_number'] ?? ''),
    'caller_display' => $isMarkForwarded ? 'Forwarded via Mark Pires line' : ($record['name'] ?: $record['phone'] ?: $from10),
    'call_direction' => $record['direction'] ?? '',
    'call_source' => 'retell',
    'call_category' => $class['cat'],
    'priority' => $class['priority'],
    'priority_score' => $class['score'],
    'lead_related' => $class['lead'],
    'needs_callback' => $class['callback'],
    'appointment_opportunity' => $class['appt'],
    'transcript' => $record['transcript'] ?? '',
    'summary' => $record['summary'] ?? '',
    'recording_url' => $record['recording_url'] ?? '',
    'duration_seconds' => (int)($record['duration_seconds'] ?? 0),
    'recommended_action' => $class['action'],
    'suggested_response' => 'Review transcript and follow up based on Jessica summary.',
    'status' => ($class['callback'] || $class['score'] >= 60) ? 'callback_needed' : 'new',
    'raw_payload' => ['call_intelligence'=>$record,'retell'=>$payload],
    'created_at' => date('c'),
    'updated_at' => date('c')
  ]];

  $save = supabase_ci_request('POST', 'business_call_log', $business);
  ci_log('Business Concierge Log', $save['ok'] ? 'success' : 'error', $save);

  $businessId = $save['ok'] && !empty($save['data'][0]['id']) ? $save['data'][0]['id'] : null;

  if ($businessId && ($class['callback'] || $class['score'] >= 60)) {
    $action = [[
      'call_id' => $businessId,
      'action_date' => date('Y-m-d'),
      'action_type' => 'callback',
      'title' => 'Callback: ' . ($business[0]['caller_display'] ?: $business[0]['from_number']),
      'details' => $class['action'] . ' Summary: ' . ($record['summary'] ?? ''),
      'priority' => $class['priority'],
      'due_at' => date('c', strtotime($class['priority'] === 'urgent' ? '+1 hour' : '+1 day')),
      'status' => 'open',
      'created_at' => date('c'),
      'updated_at' => date('c')
    ]];
    $as = supabase_ci_request('POST', 'business_call_actions', $action);
    ci_log('Business Concierge Action', $as['ok'] ? 'success' : 'error', $as);
  }

  $voice = [[
    'event_date' => date('c'),
    'source' => 'retell_super_webhook',
    'retell_call_id' => $record['call_id'] ?? '',
    'call_type' => 'forwarded_call',
    'direction' => $record['direction'] ?? '',
    'caller_name' => $record['name'] ?? '',
    'caller_phone' => $from10 ?: digits10_ci($record['phone'] ?? ''),
    'caller_email' => $record['email'] ?? '',
    'town' => $record['town'] ?? '',
    'address' => $record['address'] ?? '',
    'transcript' => $record['transcript'] ?? '',
    'summary' => $record['summary'] ?? '',
    'recording_url' => $record['recording_url'] ?? '',
    'urgency' => $class['priority'],
    'lead_related' => $class['lead'],
    'appointment_requested' => $class['appt'],
    'callback_needed' => $class['callback'],
    'hot_lead' => ($record['hot_lead'] ?? false) || $class['score'] >= 75,
    'lead_score' => max((int)($record['lead_score'] ?? 0), (int)$class['score']),
    'recommended_action' => $class['action'],
    'raw_payload' => $business[0],
    'status' => 'new',
    'created_at' => date('c'),
    'updated_at' => date('c')
  ]];

  $vs = supabase_ci_request('POST', 'voice_intelligence_events', $voice);
  ci_log('Voice Intelligence Mirror', $vs['ok'] ? 'success' : 'skipped_or_error', $vs);

  return ['business_saved'=>$save['ok'],'business_id'=>$businessId,'classification'=>$class];
}

/**
 * Allow GET demo tests, but require POST for actual Retell unless demo fields are present.
 */
$isDemoGet = ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['summary']) || isset($_GET['transcript']) || isset($_GET['from_number'])));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isDemoGet) {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'POST only']);
  exit;
}

/**
 * Accept either the original RETELL_WEBHOOK_KEY or the later AFTER_HOURS_CRON_KEY.
 */
$key = $_GET['key'] ?? '';
$keyOk = false;
if (defined('RETELL_WEBHOOK_KEY') && RETELL_WEBHOOK_KEY && hash_equals(RETELL_WEBHOOK_KEY, $key)) $keyOk = true;
if (defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && hash_equals(AFTER_HOURS_CRON_KEY, $key)) $keyOk = true;

if (!$keyOk && ((defined('RETELL_WEBHOOK_KEY') && RETELL_WEBHOOK_KEY) || (defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY))) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'invalid webhook key']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || !is_array($payload)) $payload = $_POST;
if (!$payload || !is_array($payload)) $payload = $_GET;

$event = $payload['event'] ?? $payload['type'] ?? '';
$call = $payload['call'] ?? $payload['data'] ?? $payload;

$callId = get_path_ci($call, ['call_id','id'], get_path_ci($payload, ['call_id','id'], ''));
if (!$callId) $callId = 'unknown_' . sha1($raw . json_encode($payload) . microtime(true));

$metadata = get_path_ci($call, ['metadata'], []);
if (!is_array($metadata)) $metadata = [];

$summary = clean_text_ci(get_path_ci($call, ['summary','call_analysis.call_summary','analysis.call_summary'], clean_text_ci($_GET['summary'] ?? '')));
$transcript = clean_text_ci(get_path_ci($call, ['transcript','scrubbed_transcript'], clean_text_ci($_GET['transcript'] ?? '')));
$combinedText = $summary . "\n" . $transcript;

$analysis = analyze_call_ci($combinedText, $metadata);

$fromNumber = digits_ci(get_path_ci($call, ['from_number'], $_GET['from_number'] ?? ''));
$toNumber = digits_ci(get_path_ci($call, ['to_number'], $_GET['to_number'] ?? ''));

$record = [
  'call_id' => $callId,
  'agent_id' => clean_text_ci(get_path_ci($call, ['agent_id'], '')),
  'agent_name' => clean_text_ci(get_path_ci($call, ['agent_name'], '')),
  'from_number' => $fromNumber,
  'to_number' => $toNumber,
  'direction' => clean_text_ci(get_path_ci($call, ['direction'], '')),
  'call_status' => clean_text_ci(get_path_ci($call, ['call_status','session_status'], $event)),
  'end_reason' => clean_text_ci(get_path_ci($call, ['end_reason','disconnection_reason'], '')),
  'duration_seconds' => duration_ci($call),
  'recording_url' => clean_text_ci(get_path_ci($call, ['recording_url','recording_multi_channel_url','scrubbed_recording_url'], $_GET['recording_url'] ?? '')),
  'transcript' => $transcript,
  'summary' => $summary,
  'sentiment' => clean_text_ci(get_path_ci($call, ['user_sentiment','call_analysis.user_sentiment'], '')),
  'lead_score' => $analysis['lead_score'],
  'priority' => $analysis['priority'],
  'hot_lead' => $analysis['hot_lead'],
  'appointment_requested' => $analysis['appointment_requested'],
  'motivation' => $analysis['motivation'],
  'town' => clean_text_ci($metadata['town'] ?? ($_GET['town'] ?? '')),
  'address' => clean_text_ci($metadata['address'] ?? ($_GET['address'] ?? '')),
  'name' => clean_text_ci($metadata['name'] ?? ($_GET['name'] ?? '')),
  'email' => clean_text_ci($metadata['email'] ?? ($_GET['email'] ?? '')),
  'phone' => digits_ci($metadata['phone'] ?? get_path_ci($call, ['to_number','from_number'], $_GET['phone'] ?? '')),
  'source' => clean_text_ci($metadata['source'] ?? ($_GET['source'] ?? 'retell')),
  'metadata' => $metadata,
  'raw_payload' => $payload,
  'updated_at' => date('c')
];

$save = supabase_ci_upsert_call_intelligence($record);
ci_log('Supabase Call Intelligence', $save['ok'] ? 'success' : 'error', $save);

$alert = send_hot_alert_ci($record);
ci_log('Hot Alert', !empty($alert['ok']) ? 'sent' : 'skipped_or_error', $alert);

$biz = save_business_concierge_ci($record, $payload, $call);

echo json_encode([
  'success' => true,
  'call_id' => $callId,
  'saved_call_intelligence' => $save['ok'],
  'saved_business_concierge' => $biz['business_saved'] ?? false,
  'business_call_id' => $biz['business_id'] ?? null,
  'business_classification' => $biz['classification'] ?? null,
  'hot_lead' => $record['hot_lead'],
  'appointment_requested' => $record['appointment_requested'],
  'priority' => $record['priority'],
  'alert_sent' => !empty($alert['ok'])
]);
?>