<?php
/**
 * MarkPires Action Queue V1
 * Upload to: /public_html/dashboard/action-queue.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_aq($method, $endpoint, $payload=null) {
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

function phone_link_aq($phone) {
  $d=preg_replace('/\D+/', '', (string)$phone);
  if(strlen($d)===10) return '+1'.$d;
  if(strlen($d)===11 && substr($d,0,1)==='1') return '+'.$d;
  return $phone;
}

$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id=$_POST['id'] ?? '';
  $action=$_POST['action'] ?? '';
  $note=trim($_POST['note'] ?? '');

  if ($id && $action==='done') {
    $res=sb_aq('PATCH','mark_action_queue?id=eq.'.rawurlencode($id),[
      'status'=>'done','completed_at'=>date('c'),'notes'=>$note,'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Action marked done.':'Update failed: '.$res['body'];
  }

  if ($id && $action==='dismiss') {
    $res=sb_aq('PATCH','mark_action_queue?id=eq.'.rawurlencode($id),[
      'status'=>'dismissed','notes'=>$note,'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Action dismissed.':'Update failed: '.$res['body'];
  }

  if ($id && $action==='snooze') {
    $res=sb_aq('PATCH','mark_action_queue?id=eq.'.rawurlencode($id),[
      'status'=>'snoozed','due_at'=>date('c',strtotime('+1 day 9:00')),'notes'=>$note,'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Action snoozed until tomorrow.':'Update failed: '.$res['body'];
  }

  if ($action==='add_manual') {
    $payload=[
      'related_type'=>'manual',
      'name'=>trim($_POST['name'] ?? ''),
      'phone'=>trim($_POST['phone'] ?? ''),
      'email'=>trim($_POST['email'] ?? ''),
      'town'=>trim($_POST['town'] ?? ''),
      'priority'=>trim($_POST['priority'] ?? 'normal'),
      'action_type'=>trim($_POST['action_type'] ?? 'call'),
      'recommended_action'=>trim($_POST['recommended_action'] ?? ''),
      'notes'=>trim($_POST['notes'] ?? ''),
      'status'=>'open',
      'due_at'=>date('c',strtotime($_POST['due_at'] ?? 'now')),
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    $res=sb_aq('POST','mark_action_queue',[$payload]);
    $msg=$res['ok']?'Manual action added.':'Add failed: '.$res['body'];
  }
}

$res=sb_aq('GET','mark_action_queue?select=*&status=in.(open,snoozed)&order=due_at.asc&limit=200');
$items=$res['data'];
$open=0; $hot=0; $snoozed=0;
foreach($items as $i){ if(($i['status']??'')==='open')$open++; if(($i['priority']??'')==='hot')$hot++; if(($i['status']??'')==='snoozed')$snoozed++; }
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mark Action Queue</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1300px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1300px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.l{font-size:11px;text-transform:uppercase;color:#777;letter-spacing:1px}
.panel{overflow:hidden;margin-bottom:18px}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
.card{padding:17px 20px;border-bottom:1px solid #eee}.top{display:flex;justify-content:space-between;gap:14px}.muted{color:#777;font-size:13px}.action{background:#fff8e8;border-left:4px solid var(--gold);padding:10px 12px;border-radius:8px;margin-top:10px;font-weight:700}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.normal{background:#e9f2ff;color:#174ea6}.low{background:#eee;color:#555}.snoozed{background:#fff4d7;color:#8a5a00}
.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:800;margin:4px;cursor:pointer}.gold{background:var(--gold);color:#111}.light{background:#f2efe8;color:#111}.danger{background:#ffeaea;color:#9b1c1c}
input,select,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;font-size:14px}textarea{min-height:70px}.formgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:18px 20px}.wide{grid-column:span 2}.full{grid-column:1/-1}.msg{background:#e6f7ec;color:#14783c;border-radius:10px;padding:12px;margin-bottom:16px}
@media(max-width:900px){.grid,.formgrid{grid-template-columns:1fr}.inner,.top{display:block}.wrap{padding:14px}.wide{grid-column:1/-1}}
</style>
</head>
<body>
<header class="header"><div class="inner"><div><div class="brand">Mark Action Queue</div><div class="sub">One place for calls, CMAs, follow-ups, and urgent opportunities.</div></div><div><a href="/dashboard/lead-velocity.php">Lead Velocity</a> · <a href="/dashboard/operations.php">Operations</a></div></div></header>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($open)?></div><div class="l">Open</div></div>
  <div class="kpi"><div class="n"><?=h($hot)?></div><div class="l">Hot Priority</div></div>
  <div class="kpi"><div class="n"><?=h($snoozed)?></div><div class="l">Snoozed</div></div>
</section>

<section class="panel">
  <h2>Add Manual Action</h2>
  <form method="post" class="formgrid">
    <input type="hidden" name="action" value="add_manual">
    <input name="name" placeholder="Name">
    <input name="phone" placeholder="Phone">
    <input name="email" placeholder="Email">
    <input name="town" placeholder="Town">
    <select name="priority"><option value="normal">Normal</option><option value="hot">Hot</option><option value="high">High</option><option value="low">Low</option></select>
    <select name="action_type"><option value="call">Call</option><option value="cma">Prepare CMA</option><option value="text">Text</option><option value="email">Email</option><option value="appointment">Appointment</option></select>
    <input name="due_at" placeholder="Due time e.g. tomorrow 9:00" value="now">
    <input name="recommended_action" class="wide" placeholder="Recommended action">
    <textarea name="notes" class="full" placeholder="Notes"></textarea>
    <button class="btn gold wide" type="submit">Add Action</button>
  </form>
</section>

<section class="panel">
  <h2>Open Actions</h2>
  <?php if(!$items): ?><div class="card muted">No open actions yet.</div><?php endif; ?>
  <?php foreach($items as $i): ?>
    <div class="card">
      <div class="top">
        <div>
          <strong><?=h($i['name'] ?: 'Unknown')?></strong>
          <div class="muted"><?=h($i['phone'])?> · <?=h($i['email'])?> · <?=h($i['town'])?></div>
          <div class="muted"><?=h($i['action_type'])?> · Due <?=h($i['due_at'])?> · Source <?=h($i['source'])?></div>
        </div>
        <div><span class="badge <?=h($i['priority'] ?: 'normal')?>"><?=h($i['priority'] ?: 'normal')?></span> <span class="badge <?=h($i['status'])?>"><?=h($i['status'])?></span></div>
      </div>
      <div class="action"><?=h($i['recommended_action'])?></div>
      <?php if($i['notes']): ?><p class="muted"><?=h($i['notes'])?></p><?php endif; ?>
      <?php if($i['phone']): ?><a class="btn gold" href="tel:<?=h(phone_link_aq($i['phone']))?>">Call</a><?php endif; ?>
      <?php if($i['email']): ?><a class="btn light" href="mailto:<?=h($i['email'])?>">Email</a><?php endif; ?>
      <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn" name="action" value="done">Done</button></form>
      <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn light" name="action" value="snooze">Snooze</button></form>
      <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn danger" name="action" value="dismiss">Dismiss</button></form>
    </div>
  <?php endforeach; ?>
</section>
</main>
</body>
</html>
