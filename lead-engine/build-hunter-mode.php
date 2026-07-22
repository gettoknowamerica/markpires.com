<?php
/**
 * V12.11 Jessica Hunter Mode
 * Upload: /public_html/lead-engine/build-hunter-mode.php
 *
 * Run:
 * /lead-engine/build-hunter-mode.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb1211($method, $endpoint, $payload = null) {
  $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/'));
  $headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[] = $method === 'POST'
    ? 'Prefer: resolution=ignore-duplicates,return=representation'
    : 'Prefer: return=representation';

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

  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function score_band1211($score) {
  if ($score >= 85) return 'A';
  if ($score >= 70) return 'B';
  if ($score >= 50) return 'C';
  return 'nurture';
}
function call_rec1211($score, $eligible) {
  if ($eligible && $score >= 90) return 'call_first';
  if ($eligible && $score >= 78) return 'call_today';
  if ($eligible && $score >= 65) return 'call_this_week';
  if ($score >= 55) return 'watch';
  return 'nurture';
}
function hunter_type1211($type, $market, $audience) {
  $t = strtolower((string)$type . ' ' . (string)$market . ' ' . (string)$audience);
  if (str_contains($t, 'seller')) return 'seller';
  if (str_contains($t, 'relocation') || str_contains($t, 'nyc') || str_contains($t, 'westchester')) return 'relocation';
  if (str_contains($t, 'builder')) return 'builder';
  if (str_contains($t, 'developer')) return 'developer';
  if (str_contains($t, 'buyer')) return 'buyer';
  return 'campaign';
}

$today = date('Y-m-d');
$rankings = [];
$errors = [];

/* Approved/Review compliant imports */
$imports = sb1211('GET', 'compliant_lead_imports?select=*&order=lead_score.desc,created_at.desc&limit=1000')['data'];
foreach ($imports as $r) {
  if (!is_array($r)) continue;
  $score = (int)($r['lead_score'] ?? 0);
  $eligible = !empty($r['call_eligible']) && ($r['dnc_status'] ?? '') === 'clear' && in_array(($r['approval_status'] ?? ''), ['approved','imported'], true);
  if (($r['approval_status'] ?? '') === 'review') $score -= 8;
  if (($r['dnc_status'] ?? '') === 'clear') $score += 6;
  if (($r['consent_status'] ?? '') === 'opt_in') $score += 10;
  if (($r['consent_status'] ?? '') === 'business_contact') $score += 5;
  if (!empty($r['phone'])) $score += 5;
  if (!empty($r['address'])) $score += 5;
  $score = max(0, min(100, $score));

  $type = hunter_type1211($r['lead_type'] ?? '', $r['market'] ?? '', $r['notes'] ?? '');
  $rec = call_rec1211($score, $eligible);
  $reason = $eligible
    ? 'DNC-clear / approved import with usable contact data.'
    : 'Good research target, but needs approval/DNC/consent review before calling.';

  $rankings[] = [
    'ranking_date'=>$today,
    'source_table'=>'compliant_lead_imports',
    'source_id'=>(string)($r['id'] ?? ''),
    'hunter_type'=>$type,
    'name'=>$r['name'] ?? '',
    'phone'=>$r['phone'] ?? '',
    'email'=>$r['email'] ?? '',
    'address'=>$r['address'] ?? '',
    'town'=>$r['town'] ?? '',
    'market'=>$r['market'] ?? '',
    'audience'=>$r['lead_type'] ?? '',
    'priority_band'=>score_band1211($score),
    'hunter_score'=>$score,
    'call_priority'=>$eligible ? $score : max(0, $score - 20),
    'call_recommendation'=>$rec,
    'reason'=>$reason,
    'next_action'=>$eligible ? 'Jessica/Mark can call during approved daytime window.' : 'Review compliance, approve if eligible, then push to hunter.',
    'compliance_status'=>($r['approval_status'] ?? 'review') . ' / ' . ($r['dnc_status'] ?? 'unchecked'),
    'call_eligible'=>$eligible,
    'expected_value'=>0,
    'raw_payload'=>$r,
    'status'=>'active',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

/* Discovery opportunities */
$opps = sb1211('GET', 'discovery_opportunity_queue?select=*&order=priority_score.desc,created_at.desc&limit=1000')['data'];
foreach ($opps as $o) {
  if (!is_array($o)) continue;
  $score = (int)($o['priority_score'] ?? 50);
  $type = hunter_type1211($o['opportunity_type'] ?? '', $o['market'] ?? '', $o['audience'] ?? '');
  if ($type === 'seller') $score += 8;
  if ($type === 'relocation') $score += 6;
  if ($type === 'builder' || $type === 'developer') $score += 5;
  $score = max(0, min(100, $score));

  $rankings[] = [
    'ranking_date'=>$today,
    'source_table'=>'discovery_opportunity_queue',
    'source_id'=>(string)($o['id'] ?? ''),
    'hunter_type'=>$type,
    'name'=>$o['offer'] ?? '',
    'town'=>$o['town'] ?? '',
    'market'=>$o['market'] ?? '',
    'audience'=>$o['audience'] ?? '',
    'priority_band'=>score_band1211($score),
    'hunter_score'=>$score,
    'call_priority'=>0,
    'call_recommendation'=>'watch',
    'reason'=>'High-value discovery opportunity. Use for ads, content, imports, and targeting.',
    'next_action'=>'Use this as campaign/landing-page direction or import source review.',
    'compliance_status'=>'research_only',
    'call_eligible'=>false,
    'expected_value'=>0,
    'raw_payload'=>$o,
    'status'=>'active',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

/* Builder forecasts */
$builders = sb1211('GET', 'builder_forecasts?select=*&order=forecast_score.desc&limit=1000')['data'];
foreach ($builders as $b) {
  if (!is_array($b)) continue;
  $score = (int)($b['forecast_score'] ?? 0);
  $value = (float)($b['expected_referral_value'] ?? 0);
  if ($value > 10000) $score += 8;
  $score = max(0, min(100, $score));

  $rankings[] = [
    'ranking_date'=>$today,
    'source_table'=>'builder_forecasts',
    'source_id'=>(string)($b['id'] ?? ''),
    'hunter_type'=>'builder',
    'name'=>$b['builder_name'] ?? '',
    'address'=>$b['opportunity_address'] ?? '',
    'town'=>$b['opportunity_town'] ?? '',
    'market'=>'Builder Pipeline',
    'audience'=>$b['opportunity_type'] ?? '',
    'priority_band'=>score_band1211($score),
    'hunter_score'=>$score,
    'call_priority'=>$score,
    'call_recommendation'=>$score >= 80 ? 'call_today' : 'watch',
    'reason'=>$b['expected_outcome'] ?? 'Builder opportunity forecast.',
    'next_action'=>$b['recommended_action'] ?? 'Review builder opportunity.',
    'compliance_status'=>'business/opportunity follow-up',
    'call_eligible'=>true,
    'expected_value'=>$value,
    'raw_payload'=>$b,
    'status'=>'active',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

/* Campaigns */
$campaigns = sb1211('GET', 'first_campaign_plan?select=*&order=priority_score.desc,created_at.desc&limit=300')['data'];
foreach ($campaigns as $c) {
  if (!is_array($c)) continue;
  $score = (int)($c['priority_score'] ?? 50);
  if (!empty($c['facebook_primary_text'])) $score += 5;
  if (!empty($c['creative_prompt'])) $score += 5;
  if (!empty($c['approved_for_launch'])) $score += 10;
  $score = max(0, min(100, $score));
  $type = hunter_type1211($c['campaign_name'] ?? '', $c['market'] ?? '', $c['audience'] ?? '');

  $rankings[] = [
    'ranking_date'=>$today,
    'source_table'=>'first_campaign_plan',
    'source_id'=>(string)($c['id'] ?? ''),
    'hunter_type'=>$type,
    'name'=>$c['campaign_name'] ?? '',
    'town'=>$c['town'] ?? '',
    'market'=>$c['market'] ?? '',
    'audience'=>$c['audience'] ?? '',
    'priority_band'=>score_band1211($score),
    'hunter_score'=>$score,
    'call_priority'=>0,
    'call_recommendation'=>'watch',
    'reason'=>'Campaign opportunity ready for launch/review.',
    'next_action'=>'Review ad copy, launch URL, and budget. Pick strongest campaign to launch first.',
    'compliance_status'=>'ad_campaign',
    'call_eligible'=>false,
    'expected_value'=>0,
    'raw_payload'=>$c,
    'status'=>'active',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

usort($rankings, function($a, $b){
  return ($b['hunter_score'] <=> $a['hunter_score']);
});

$inserted = [];
foreach (array_chunk(array_slice($rankings, 0, 500), 100) as $chunk) {
  $r = sb1211('POST', 'hunter_priority_rankings', $chunk);
  if ($r['ok']) $inserted[] = ['count'=>count($chunk), 'http'=>$r['http']];
  else $errors[] = ['http'=>$r['http'], 'body'=>$r['body']];
}

$topCall = array_values(array_filter($rankings, function($r){
  return in_array($r['call_recommendation'], ['call_first','call_today'], true);
}));
$topCall = array_slice($topCall, 0, 15);

$seller=0; $buyer=0; $builder=0; $callFirst=0; $callToday=0; $nurture=0; $expected=0;
foreach ($rankings as $r) {
  if ($r['hunter_type'] === 'seller') $seller++;
  if (in_array($r['hunter_type'], ['buyer','relocation'], true)) $buyer++;
  if (in_array($r['hunter_type'], ['builder','developer'], true)) $builder++;
  if ($r['call_recommendation'] === 'call_first') $callFirst++;
  if ($r['call_recommendation'] === 'call_today') $callToday++;
  if ($r['call_recommendation'] === 'nurture') $nurture++;
  $expected += (float)($r['expected_value'] ?? 0);
}

$brief = "Jessica Hunter Briefing — {$today}\n\n";
$brief .= "Total ranked: ".count($rankings)."\n";
$brief .= "Call first: {$callFirst}\n";
$brief .= "Call today: {$callToday}\n";
$brief .= "Sellers: {$seller}\n";
$brief .= "Buyers/relocation: {$buyer}\n";
$brief .= "Builders/developers: {$builder}\n";
$brief .= "Expected value: $".number_format($expected,0)."\n\n";
$brief .= "Top calls:\n";
foreach ($topCall as $i=>$r) {
  $brief .= ($i+1).". ".($r['name'] ?: $r['town'].' '.$r['hunter_type'])." — ".$r['call_recommendation']." — Score ".$r['hunter_score']." — ".$r['reason']."\n";
}

$daily = [[
  'briefing_date'=>$today,
  'total_ranked'=>count($rankings),
  'call_first'=>$callFirst,
  'call_today'=>$callToday,
  'nurture'=>$nurture,
  'seller_count'=>$seller,
  'buyer_count'=>$buyer,
  'builder_count'=>$builder,
  'expected_value'=>$expected,
  'top_call_list'=>$topCall,
  'briefing_text'=>$brief,
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$dr = sb1211('POST', 'hunter_daily_briefings', $daily);
if (!$dr['ok'] && str_contains($dr['body'], 'duplicate key')) {
  sb1211('PATCH', 'hunter_daily_briefings?briefing_date=eq.' . rawurlencode($today), $daily[0]);
}

echo json_encode([
  'success'=>empty($errors),
  'ranked'=>count($rankings),
  'inserted'=>$inserted,
  'call_first'=>$callFirst,
  'call_today'=>$callToday,
  'top_call_list'=>$topCall,
  'briefing'=>$brief,
  'errors'=>$errors
], JSON_PRETTY_PRINT);
?>