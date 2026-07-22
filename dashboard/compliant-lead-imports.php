<?php
/**
 * V12.1.1 Compliant Lead Imports Dashboard — 500 Fix
 * Upload over: /public_html/dashboard/compliant-lead-imports.php
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

function sb1211($method, $endpoint, $payload = null){
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT => 25
  ]);

  if ($payload !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  }

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  $data = json_decode($body, true);
  return [
    'ok' => $http >= 200 && $http < 300,
    'http' => $http,
    'body' => $body,
    'error' => $err,
    'data' => is_array($data) ? $data : []
  ];
}

$msg = '';
$error = '';

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['approved','rejected'], true)) {
      $payload = [
        'approval_status' => $action,
        'updated_at' => date('c')
      ];

      if ($action === 'approved') {
        $payload['dnc_status'] = $_POST['dnc_status'] ?? 'clear';
        $payload['call_eligible'] = isset($_POST['call_eligible']);
        $payload['sms_eligible'] = isset($_POST['sms_eligible']);
        $payload['email_eligible'] = isset($_POST['email_eligible']);
        $payload['consent_status'] = $_POST['consent_status'] ?? 'unknown';
      }

      $r = sb1211('PATCH', 'compliant_lead_imports?id=eq.' . rawurlencode($id), $payload);
      $msg = $r['ok'] ? 'Updated.' : 'Update failed: ' . $r['body'];
    }
  }

  $res = sb1211('GET', 'compliant_lead_imports?select=*&order=lead_score.desc,created_at.desc&limit=300');
  $rows = $res['data'];

  if (!$res['ok']) {
    $error = 'Supabase error: HTTP ' . $res['http'] . "\n" . $res['body'];
  }

} catch (Throwable $e) {
  $rows = [];
  $error = 'PHP exception: ' . $e->getMessage() . ' on line ' . $e->getLine();
}

$cronKey = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'YOUR_KEY';
$stats = ['review'=>0,'approved'=>0,'imported'=>0,'rejected'=>0,'total'=>count($rows)];
foreach ($rows as $r) {
  $s = $r['approval_status'] ?? 'review';
  if (isset($stats[$s])) $stats[$s]++;
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Compliant Lead Imports V12.1.1</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}
.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}
.wrap{max-width:1500px;margin:auto;padding:26px}
.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}
.kpi{padding:18px}.n{font-size:30px;font-weight:900}
.panel{margin-top:18px;overflow:hidden}
.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}
.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}
.light{background:#f2efe8;color:#111}
table{width:100%;border-collapse:collapse}
td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}
th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
.muted{color:#777;font-size:13px}.ok{background:#e6f7ec;color:#14783c;padding:14px;border-radius:12px}.bad{background:#ffeaea;color:#9b1c1c;padding:14px;border-radius:12px;white-space:pre-wrap}
select{padding:7px;border:1px solid #ddd;border-radius:8px;margin:3px}
@media(max-width:1000px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header">
  <div class="brand">Compliant Lead Imports V12.1.1</div>
  <div>Approval gate before Jessica can call imported/researched contacts</div>
</div>
<main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?>
<?php if($error):?><div class="bad"><?=h($error)?></div><?php endif;?>

<p>
  <a class="btn" target="_blank" href="/lead-engine/build-compliant-import-queue.php?key=<?=h($cronKey)?>">Build Import Review Queue</a>
  <a class="btn light" target="_blank" href="/lead-engine/push-approved-imports.php?key=<?=h($cronKey)?>">Push Approved Imports</a>
  <a class="btn light" href="/dashboard/discovery-intelligence.php">Discovery</a>
</p>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div>
  <div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div>
  <div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div>
  <div class="kpi"><div class="n"><?=h($stats['imported'])?></div>Imported</div>
  <div class="kpi"><div class="n"><?=h($stats['rejected'])?></div>Rejected</div>
</section>

<section class="panel">
<h2>Import Review Queue</h2>
<table>
<tr><th>Score</th><th>Lead / Target</th><th>Compliance</th><th>Notes</th><th>Approve</th></tr>
<?php foreach($rows as $r):?>
<tr>
<td><strong><?=h($r['lead_score'] ?? '')?></strong><div class="muted"><?=h($r['lead_type'] ?? '')?><br><?=h($r['approval_status'] ?? '')?></div></td>
<td><strong><?=h(($r['name'] ?? '') ?: 'Research Target')?></strong><div class="muted"><?=h($r['phone'] ?? '')?><br><?=h($r['email'] ?? '')?><br><?=h($r['town'] ?? '')?> <?=h($r['market'] ?? '')?></div></td>
<td>DNC: <?=h($r['dnc_status'] ?? '')?><br>Consent: <?=h($r['consent_status'] ?? '')?><br>Call: <?=h(!empty($r['call_eligible'])?'yes':'no')?></td>
<td><?=h($r['notes'] ?? '')?></td>
<td>
<form method="post">
<input type="hidden" name="id" value="<?=h($r['id'] ?? '')?>">
<select name="dnc_status">
<option value="clear">clear</option>
<option value="blocked">blocked</option>
<option value="unknown">unknown</option>
</select>
<select name="consent_status">
<option value="unknown">unknown</option>
<option value="opt_in">opt_in</option>
<option value="business_contact">business_contact</option>
</select><br>
<label><input type="checkbox" name="call_eligible"> Call eligible</label><br>
<label><input type="checkbox" name="sms_eligible"> SMS eligible</label><br>
<label><input type="checkbox" name="email_eligible" checked> Email eligible</label><br>
<button class="btn" name="action" value="approved">Approve</button>
<button class="btn light" name="action" value="rejected">Reject</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
</main>
</body>
</html>