<?php
/**
 * V13.3.1 Jessica Deliverables 500 Fix
 * Upload over: /public_html/dashboard/jessica-deliverables.php
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location:/dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb20d($m, $ep, $p = null){
  $ch = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($ep, '/'));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $m,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT => 25
  ]);

  // IMPORTANT FIX: comma, not equals.
  if ($p !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($p));
  }

  $b = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  $d = json_decode($b, true);
  if (!is_array($d)) return [];
  if ($http < 200 || $http >= 300) return [];
  return $d;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $status = $_POST['status'] ?? '';
  if ($id && in_array($status, ['open','reviewed','approved','completed','archived'], true)) {
    sb20d('PATCH', 'jessica_daily_deliverables?id=eq.' . rawurlencode($id), [
      'status' => $status,
      'updated_at' => date('c')
    ]);
    $msg = 'Deliverable marked ' . $status . '.';
  }
}

$rows = sb20d('GET', 'jessica_daily_deliverables?select=*&order=priority_score.desc,created_at.desc&limit=400');
$logs = sb20d('GET', 'jessica_morning_email_logs?select=*&order=created_at.desc&limit=10');

$stats = [
  'total' => count($rows),
  'ad' => 0,
  'content' => 0,
  'queue' => 0,
  'appointment' => 0,
  'executive_call' => 0
];

foreach ($rows as $r) {
  $t = $r['deliverable_type'] ?? '';
  if (isset($stats[$t])) $stats[$t]++;
}

$cronKey = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'YOUR_KEY';
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jessica Deliverables Gallery</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}
.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}
.wrap{max-width:1600px;margin:auto;padding:26px}
.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}
.kpi{padding:18px}.n{font-size:30px;font-weight:900}
.panel{margin-top:18px;overflow:hidden}
.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}
.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}
.light{background:#f2efe8;color:#111}
.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}
table{width:100%;border-collapse:collapse}
td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}
th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
.muted{color:#777;font-size:13px}
pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}
.ad{color:#174ea6;font-weight:900}
.content{color:#5a2ca0;font-weight:900}
.queue{color:#9a6400;font-weight:900}
.appointment,.executive_call{color:#14783c;font-weight:900}
@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header">
  <div class="brand">Jessica Deliverables Gallery</div>
  <div>Ads, content, queue, appointments, forwarded calls, morning email, and approved creative in one place</div>
</div>

<main class="wrap">
<?php if($msg): ?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif; ?>

<p>
  <a class="btn" target="_blank" href="/lead-engine/build-morning-executive-brief.php?key=<?=h($cronKey)?>">Build Brief</a>
  <a class="btn light" target="_blank" href="/lead-engine/build-morning-executive-brief.php?key=<?=h($cronKey)?>&send=1">Build + Email Mark</a>
  <a class="btn light" href="/dashboard/creative-review-center.php">Creative Review</a>
  <a class="btn light" href="/dashboard/source-hunter-center.php">Source Hunter</a>
  <a class="btn light" href="/dashboard/daily-command-center.php">Command</a>
  <a class="btn light" href="/dashboard/queue-intelligence.php">Queue</a>
</p>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div>
  <div class="kpi"><div class="n"><?=h($stats['ad'])?></div>Ads</div>
  <div class="kpi"><div class="n"><?=h($stats['content'])?></div>Content</div>
  <div class="kpi"><div class="n"><?=h($stats['queue'])?></div>Queue</div>
  <div class="kpi"><div class="n"><?=h($stats['appointment'])?></div>Appts</div>
  <div class="kpi"><div class="n"><?=h($stats['executive_call'])?></div>Calls</div>
</section>

<div class="layout">
<section class="panel">
<h2>Daily Deliverables</h2>
<table>
<tr><th>Type</th><th>Title</th><th>Summary</th><th>Action</th><th>Status</th></tr>
<?php if(empty($rows)): ?>
<tr><td colspan="5">No deliverables yet. Click Build Brief.</td></tr>
<?php endif; ?>
<?php foreach($rows as $r): ?>
<tr>
<td class="<?=h($r['deliverable_type'] ?? '')?>">
  <?=h($r['deliverable_type'] ?? '')?>
  <div class="muted">Score <?=h($r['priority_score'] ?? 0)?><br><?=h($r['town'] ?? '')?></div>
</td>
<td>
  <strong><?=h($r['title'] ?? '')?></strong>
  <?php if(!empty($r['action_url'])): ?><br><a target="_blank" href="<?=h($r['action_url'])?>">Open</a><?php endif; ?>
</td>
<td><?=h($r['summary'] ?? '')?></td>
<td><?=h($r['recommended_action'] ?? '')?></td>
<td>
<form method="post">
  <input type="hidden" name="id" value="<?=h($r['id'] ?? '')?>">
  <button class="btn" name="status" value="completed">Done</button>
  <button class="btn light" name="status" value="approved">Approve</button>
  <button class="btn light" name="status" value="reviewed">Reviewed</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Morning Email Logs</h2>
<table><tr><th>Date</th><th>Status</th><th>Subject</th></tr>
<?php if(empty($logs)): ?>
<tr><td colspan="3">No email logs yet.</td></tr>
<?php endif; ?>
<?php foreach($logs as $l): ?>
<tr>
<td><?=h($l['created_at'] ?? '')?></td>
<td><?=h($l['send_status'] ?? '')?></td>
<td><?=h($l['subject'] ?? '')?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if(!empty($logs[0]['email_body'])): ?>
<h2>Latest Email Preview</h2>
<div style="padding:16px"><pre><?=h($logs[0]['email_body'])?></pre></div>
<?php endif; ?>
</section>
</div>
</main>
</body>
</html>