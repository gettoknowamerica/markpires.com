<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb($method, $endpoint, $payload=null) {
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
    CURLOPT_TIMEOUT => 20
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id=$_POST['id'] ?? '';
  $payload=[
    'step_order'=>(int)($_POST['step_order'] ?? 0),
    'channel'=>trim($_POST['channel'] ?? 'email'),
    'delay_days'=>(int)($_POST['delay_days'] ?? 1),
    'send_hour'=>(int)($_POST['send_hour'] ?? 9),
    'subject'=>trim($_POST['subject'] ?? ''),
    'message'=>trim($_POST['message'] ?? ''),
    'applies_to'=>trim($_POST['applies_to'] ?? 'all'),
    'is_active'=>isset($_POST['is_active']),
    'updated_at'=>date('c')
  ];
  if ($id) {
    $res=sb('PATCH','drip_sequence_templates?id=eq.'.rawurlencode($id),$payload);
    $msg=$res['ok']?'Sequence step updated.':'Update failed: '.$res['body'];
  }
}

$res=sb('GET','drip_sequence_templates?select=*&order=step_order.asc');
$steps=$res['data'];
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sequence Editor — Mark Pires</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:22px 28px;display:flex;justify-content:space-between;gap:16px}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1180px;margin:0 auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);padding:22px;margin-bottom:18px}
h1,h2{font-family:Georgia,serif;margin:0 0 12px}.grid{display:grid;grid-template-columns:90px 150px 110px 110px 1fr;gap:10px;margin-bottom:10px}
input,select,textarea{width:100%;padding:10px;border:1px solid #e7e1d8;border-radius:8px;font-size:14px}textarea{min-height:105px;margin-top:10px}
.btn{border:0;background:#10101a;color:#fff;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}.gold{background:#c8a96e;color:#111}
.msg{background:#e6f7ec;color:#14783c;padding:12px;border-radius:10px;margin-bottom:18px}.muted{color:#777;font-size:13px;line-height:1.6}.tokens code{background:#f7f2e7;border-radius:6px;padding:3px 6px}
@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}.header{display:block}}
</style>
</head>
<body>
<div class="header">
  <div><strong>Sequence Editor</strong><div style="color:rgba(255,255,255,.62);font-size:13px">Edit email · SMS · Jessica voice drip steps</div></div>
  <div><a href="/dashboard/">Main Dashboard</a> · <a href="/dashboard/drip-control.php">Drip Control</a></div>
</div>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<div class="panel">
  <h1>Follow-Up Sequence Templates</h1>
  <p class="muted tokens">Use tokens: <code>{{first_name}}</code> <code>{{name}}</code> <code>{{town}}</code> <code>{{type}}</code> <code>{{timeline}}</code> <code>{{address}}</code></p>
</div>

<?php foreach($steps as $s): ?>
<form method="post" class="panel">
  <input type="hidden" name="id" value="<?=h($s['id'])?>">
  <h2><?=h($s['step_key'])?></h2>
  <div class="grid">
    <input name="step_order" type="number" value="<?=h($s['step_order'])?>" placeholder="Order">
    <select name="channel">
      <?php foreach(['email','sms','voice'] as $ch): ?><option value="<?=h($ch)?>" <?=($s['channel']===$ch?'selected':'')?>><?=h(strtoupper($ch))?></option><?php endforeach; ?>
    </select>
    <input name="delay_days" type="number" value="<?=h($s['delay_days'])?>" placeholder="Delay days">
    <input name="send_hour" type="number" min="8" max="21" value="<?=h($s['send_hour'])?>" placeholder="Hour">
    <input name="applies_to" value="<?=h($s['applies_to'])?>" placeholder="all / valuation / buyer">
  </div>
  <input name="subject" value="<?=h($s['subject'])?>" placeholder="Subject line">
  <textarea name="message" placeholder="Message"><?=h($s['message'])?></textarea>
  <p><label><input type="checkbox" name="is_active" <?=!empty($s['is_active'])?'checked':''?> style="width:auto"> Active</label></p>
  <button class="btn gold" type="submit">Save Step</button>
</form>
<?php endforeach; ?>
</main>
</body>
</html>
