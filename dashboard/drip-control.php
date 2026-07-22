<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_get($endpoint) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 15
  ]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function sb_patch($endpoint, $payload) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT => 15
  ]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $action = $_POST['action'] ?? '';

  if ($id && $action === 'skip') {
    $res = sb_patch('lead_followup_queue?id=eq.' . rawurlencode($id), ['status'=>'skipped','updated_at'=>date('c')]);
    $msg = $res['ok'] ? 'Drip step skipped.' : 'Could not skip item: ' . $res['body'];
  }

  if ($id && $action === 'requeue') {
    $res = sb_patch('lead_followup_queue?id=eq.' . rawurlencode($id), ['status'=>'queued','attempts'=>0,'updated_at'=>date('c')]);
    $msg = $res['ok'] ? 'Drip step requeued.' : 'Could not requeue item: ' . $res['body'];
  }

  if ($id && $action === 'send_now') {
    $res = sb_patch('lead_followup_queue?id=eq.' . rawurlencode($id), ['scheduled_for'=>date('c'),'status'=>'queued','updated_at'=>date('c')]);
    $msg = $res['ok'] ? 'Drip step queued to send now. Cron will process it.' : 'Could not queue item: ' . $res['body'];
  }
}

$res = sb_get('lead_followup_queue?select=*&order=scheduled_for.asc&limit=300');
$items = $res['data'];

$total=count($items); $queued=0; $sent=0; $errors=0; $sms=0; $email=0; $voice=0;
foreach($items as $i){
  $st=$i['status']??'';
  if($st==='queued') $queued++;
  if($st==='sent') $sent++;
  if($st==='error') $errors++;
  if(($i['channel']??'')==='sms') $sms++;
  if(($i['channel']??'')==='email') $email++;
  if(($i['channel']??'')==='voice') $voice++;
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Drip Control Center — Mark Pires</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:22px 28px;display:flex;justify-content:space-between;gap:16px}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1380px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:20px}
.kpi{background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.n{font-size:30px;font-weight:900}.l{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#777}
.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid #eee}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{background:#faf9f6;color:#777;text-transform:uppercase;font-size:11px;letter-spacing:1px}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.queued{background:#fff4d7;color:#8a5a00}.sent{background:#e6f7ec;color:#14783c}.error{background:#ffeaea;color:#9b1c1c}.skipped{background:#eee;color:#555}
.btn{border:0;background:#10101a;color:#fff;border-radius:8px;padding:7px 9px;font-size:12px;cursor:pointer;margin:2px}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.msg{margin:0 0 18px;background:#e6f7ec;color:#14783c;padding:12px;border-radius:10px}.muted{color:#777;font-size:12px}.tablewrap{overflow:auto}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}.wrap{padding:14px}.header{display:block}}
</style>
</head>
<body>
<div class="header">
  <div><strong>Drip Control Center</strong><div style="color:rgba(255,255,255,.62);font-size:13px">Email · SMS · Jessica voice follow-ups · V3 clean duplicate handling</div></div>
  <div><a href="/dashboard/">Main Dashboard</a> · <a href="/lead-engine/seed-followups.php?key=YOUR_KEY" target="_blank">Seed Followups V3</a> · <a href="/lead-engine/cron-master.php?key=YOUR_KEY" target="_blank">Run Cron</a></div>
</div>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($total)?></div><div class="l">Total Steps</div></div>
  <div class="kpi"><div class="n"><?=h($queued)?></div><div class="l">Queued</div></div>
  <div class="kpi"><div class="n"><?=h($sent)?></div><div class="l">Sent</div></div>
  <div class="kpi"><div class="n"><?=h($errors)?></div><div class="l">Errors</div></div>
  <div class="kpi"><div class="n"><?=h($email + $sms)?></div><div class="l">Email/SMS</div></div>
  <div class="kpi"><div class="n"><?=h($voice)?></div><div class="l">Voice Calls</div></div>
</section>

<section class="panel">
  <h2>Follow-Up Queue</h2>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Status</th><th>Lead</th><th>Step</th><th>Message</th><th>Scheduled</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($items as $i): $st=$i['status'] ?: 'queued'; ?>
        <tr>
          <td><span class="badge <?=h($st)?>"><?=h($st)?></span><div class="muted"><?=h($i['channel'])?></div></td>
          <td><strong><?=h($i['lead_name'] ?: 'Unknown')?></strong><div class="muted"><?=h($i['lead_email'])?><br><?=h($i['lead_phone'])?></div></td>
          <td><strong><?=h($i['step_key'])?></strong><div class="muted"><?=h($i['subject'])?></div></td>
          <td style="max-width:420px"><?=h($i['message'])?></td>
          <td><?=h($i['scheduled_for'])?><div class="muted">Attempts: <?=h($i['attempts'])?></div></td>
          <td>
            <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn gold" name="action" value="send_now">Send Now</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn light" name="action" value="skip">Skip</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn" name="action" value="requeue">Requeue</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
</main>
</body>
</html>
