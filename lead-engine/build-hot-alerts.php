<?php
/**
 * MarkPires Hot Lead Alert Builder V1
 * Upload to: /public_html/lead-engine/build-hot-alerts.php
 *
 * URL:
 * https://markpires.com/lead-engine/build-hot-alerts.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb_hot($method, $endpoint, $payload=null) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
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

function normalize_hot_phone($phone) {
  $d = preg_replace('/\D+/', '', (string)$phone);
  if(strlen($d) === 10) return '+1'.$d;
  if(strlen($d) === 11 && substr($d,0,1)==='1') return '+'.$d;
  return $phone;
}

function alert_exists($relatedType, $relatedId) {
  if (!$relatedId) return false;
  $res = sb_hot('GET', 'hot_lead_alerts?select=id&related_type=eq.'.rawurlencode($relatedType).'&related_id=eq.'.rawurlencode($relatedId).'&limit=1');
  return !empty($res['data']);
}

function send_hot_email($alert) {
  if (!defined('RESEND_API_KEY') || !RESEND_API_KEY || !defined('RESEND_FROM_EMAIL') || !defined('MARK_EMAIL')) {
    return ['ok'=>false,'error'=>'Resend not configured'];
  }

  $subject = '🔥 HOT LEAD: ' . ($alert['name'] ?: $alert['phone'] ?: 'New opportunity');

  $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#141421;max-width:680px;margin:auto">'
    . '<h1 style="font-family:Georgia,serif;color:#141421">🔥 Hot Lead Alert</h1>'
    . '<p><strong>Name:</strong> '.htmlspecialchars($alert['name']).'</p>'
    . '<p><strong>Phone:</strong> '.htmlspecialchars($alert['phone']).'</p>'
    . '<p><strong>Email:</strong> '.htmlspecialchars($alert['email']).'</p>'
    . '<p><strong>Town:</strong> '.htmlspecialchars($alert['town']).'</p>'
    . '<p><strong>Address:</strong> '.htmlspecialchars($alert['address']).'</p>'
    . '<p><strong>Source:</strong> '.htmlspecialchars($alert['source']).'</p>'
    . '<p><strong>Score:</strong> '.htmlspecialchars($alert['lead_score']).'</p>'
    . '<p><strong>Reason:</strong> '.htmlspecialchars($alert['reason']).'</p>'
    . '<p style="background:#fff4d7;border-left:4px solid #c8a96e;padding:12px"><strong>Recommended Action:</strong><br>'.htmlspecialchars($alert['recommended_action']).'</p>'
    . '<p><a href="https://markpires.com/dashboard/hot-lead-alerts.php" style="background:#141421;color:#fff;padding:12px 16px;border-radius:8px;text-decoration:none">Open Hot Lead Dashboard</a></p>'
    . '</div>';

  $payload = [
    'from' => (defined('MARK_NAME') ? MARK_NAME : 'Mark Pires') . ' <' . RESEND_FROM_EMAIL . '>',
    'to' => [MARK_EMAIL],
    'subject' => $subject,
    'html' => $html
  ];

  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'Authorization: Bearer '.RESEND_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT=>15
  ]);
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

function send_mark_sms($alert) {
  if (!defined('MARK_PHONE') || !MARK_PHONE) return ['ok'=>false,'error'=>'MARK_PHONE not configured'];
  if (!defined('TWILIO_ACCOUNT_SID') || !TWILIO_ACCOUNT_SID || str_contains(TWILIO_ACCOUNT_SID, 'PUT_')) return ['ok'=>false,'error'=>'Twilio SID not configured'];
  if (!defined('TWILIO_AUTH_TOKEN') || !TWILIO_AUTH_TOKEN || str_contains(TWILIO_AUTH_TOKEN, 'PUT_')) return ['ok'=>false,'error'=>'Twilio token not configured'];
  if (!defined('TWILIO_SMS_FROM') || !TWILIO_SMS_FROM) return ['ok'=>false,'error'=>'TWILIO_SMS_FROM missing'];

  $body = "🔥 HOT LEAD: ".($alert['name'] ?: 'Unknown')
    . "\\nPhone: ".($alert['phone'] ?: '')
    . "\\nTown: ".($alert['town'] ?: '')
    . "\\nScore: ".($alert['lead_score'] ?: 0)
    . "\\nAction: ".($alert['recommended_action'] ?: 'Call now')
    . "\\nDashboard: https://markpires.com/dashboard/hot-lead-alerts.php";

  $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
  $post = http_build_query([
    'From' => normalize_hot_phone(TWILIO_SMS_FROM),
    'To' => normalize_hot_phone(MARK_PHONE),
    'Body' => $body
  ]);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$post,
    CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT=>15
  ]);
  $resp=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);

  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}

function source_label_hot($lead) {
  $url = strtolower((string)($lead['page_url'] ?? ''));
  $tag = (string)($lead['tag'] ?? '');
  $type = (string)($lead['type'] ?? '');
  if (str_contains($url, '/towns/')) return 'Town Page';
  if (str_contains($url, '/blog/')) return 'Blog';
  if (str_contains($url, 'home-valuation') || $type === 'valuation') return 'Home Valuation';
  return $tag ?: ($type ?: 'Website');
}

$created = [];
$skipped = [];

$leads = sb_hot('GET','leads?select=*&lead_score=gte.75&order=created_at.desc&limit=100')['data'];
$calls = sb_hot('GET','call_intelligence?select=*&or=(hot_lead.eq.true,appointment_requested.eq.true)&order=created_at.desc&limit=100')['data'];

foreach ($leads as $lead) {
  $id = (string)($lead['id'] ?? '');
  if (alert_exists('lead', $id)) {
    $skipped[] = ['type'=>'lead','name'=>$lead['name'] ?? '', 'reason'=>'exists'];
    continue;
  }

  $score = (int)($lead['lead_score'] ?? 0);
  $reason = $score >= 90 ? 'Lead score 90+ from website/funnel.' : 'High-quality lead score 75+.';

  $alert = [
    'alert_type'=>'lead',
    'related_type'=>'lead',
    'related_id'=>$id,
    'name'=>$lead['name'] ?? '',
    'phone'=>normalize_hot_phone($lead['phone'] ?? ''),
    'email'=>$lead['email'] ?? '',
    'town'=>$lead['town'] ?? '',
    'address'=>$lead['address'] ?? '',
    'source'=>source_label_hot($lead),
    'lead_score'=>$score,
    'priority'=>$score >= 90 ? 'hot' : 'high',
    'reason'=>$reason,
    'recommended_action'=>$score >= 90 ? 'Call immediately.' : 'Call today.',
    'status'=>'new',
    'raw_payload'=>$lead,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];

  $email = send_hot_email($alert);
  $sms = send_mark_sms($alert);

  $alert['email_sent'] = $email['ok'];
  $alert['sms_sent'] = $sms['ok'];
  $alert['provider_response'] = ['email'=>$email,'sms'=>$sms];

  $res = sb_hot('POST','hot_lead_alerts',[$alert]);
  $created[] = ['type'=>'lead','name'=>$alert['name'],'ok'=>$res['ok'],'email'=>$email['ok'],'sms'=>$sms['ok']];
}

foreach ($calls as $call) {
  $id = (string)($call['id'] ?? ($call['call_id'] ?? ''));
  if (alert_exists('call', $id)) {
    $skipped[] = ['type'=>'call','name'=>$call['name'] ?? '', 'reason'=>'exists'];
    continue;
  }

  $reason = !empty($call['appointment_requested']) ? 'Jessica detected appointment language.' : 'Jessica detected hot lead language.';
  $alert = [
    'alert_type'=>'call',
    'related_type'=>'call',
    'related_id'=>$id,
    'name'=>$call['name'] ?? '',
    'phone'=>normalize_hot_phone($call['phone'] ?? $call['from_number'] ?? $call['to_number'] ?? ''),
    'email'=>$call['email'] ?? '',
    'town'=>$call['town'] ?? '',
    'address'=>$call['address'] ?? '',
    'source'=>$call['source'] ?: 'Jessica Call',
    'lead_score'=>(int)($call['lead_score'] ?? 0),
    'priority'=>'hot',
    'reason'=>$reason,
    'recommended_action'=>'Call immediately and reference Jessica conversation.',
    'status'=>'new',
    'raw_payload'=>$call,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];

  $email = send_hot_email($alert);
  $sms = send_mark_sms($alert);

  $alert['email_sent'] = $email['ok'];
  $alert['sms_sent'] = $sms['ok'];
  $alert['provider_response'] = ['email'=>$email,'sms'=>$sms];

  $res = sb_hot('POST','hot_lead_alerts',[$alert]);
  $created[] = ['type'=>'call','name'=>$alert['name'],'ok'=>$res['ok'],'email'=>$email['ok'],'sms'=>$sms['ok']];
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
