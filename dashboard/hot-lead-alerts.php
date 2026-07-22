<?php
/**
 * MarkPires Hot Lead Alerts Dashboard V1
 * Upload to: /public_html/dashboard/hot-lead-alerts.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_hotdash($method,$endpoint,$payload=null){
  $url = rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>20
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function phone_hotdash($phone){
  $d=preg_replace('/\D+/', '', (string)$phone);
  if(strlen($d)===10) return '+1'.$d;
  if(strlen($d)===11 && substr($d,0,1)==='1') return '+'.$d;
  return $phone;
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id'] ?? '';
  $action=$_POST['action'] ?? '';
  if($id && in_array($action,['acknowledged','done','dismissed'],true)){
    $res=sb_hotdash('PATCH','hot_lead_alerts?id=eq.'.rawurlencode($id),[
      'status'=>$action,
      'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Alert updated.':'Update failed: '.$res['body'];
  }
}

$status=$_GET['status'] ?? 'new';
$endpoint = $status === 'all'
  ? 'hot_lead_alerts?select=*&order=created_at.desc&limit=200'
  : 'hot_lead_alerts?select=*&status=eq.'.rawurlencode($status).'&order=created_at.desc&limit=200';
$res=sb_hotdash('GET',$endpoint);
$rows=$res['data'];

$new=0;$done=0;$sms=0;$email=0;
foreach($rows as $r){ if(($r['status']??'')==='new')$new++; if(($r['status']??'')==='done')$done++; if(!empty($r['sms_sent']))$sms++; if(!empty($r['email_sent']))$email++; }
$cronKey = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'YOUR_KEY';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hot Lead Alerts — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1300px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1300px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.l{font-size:11px;text-transform:uppercase;color:#777;letter-spacing:1px}
.panel{overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
.controls{padding:16px 20px;border-bottom:1px solid #eee;display:flex;gap:10px;flex-wrap:wrap}.card{padding:17px 20px;border-bottom:1px solid #eee}.top{display:flex;justify-content:space-between;gap:14px}.muted{color:#777;font-size:13px}.action{background:#fff8e8;border-left:4px solid var(--gold);padding:10px 12px;border-radius:8px;margin-top:10px;font-weight:700}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.new{background:#ffeaea;color:#9b1c1c}.acknowledged{background:#fff4d7;color:#8a5a00}.done{background:#e6f7ec;color:#14783c}.dismissed{background:#eee;color:#555}
.btn{border:0;background:#10101a;color:#fff;text-decoration:none;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:800;margin:4px;cursor:pointer}.gold{background:var(--gold);color:#111}.light{background:#f2efe8;color:#111}.danger{background:#ffeaea;color:#9b1c1c}
.msg{background:#e6f7ec;color:#14783c;border-radius:10px;padding:12px;margin-bottom:16px}
@media(max-width:900px){.grid{grid-template-columns:1fr}.inner,.top{display:block}.wrap{padding:14px}}
</style>
</head>
<body>
<header class="header"><div class="inner"><div><div class="brand">Hot Lead Alerts</div><div class="sub">The highest urgency leads from forms and Jessica calls.</div></div><div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/action-queue.php">Action Queue</a></div></div></header>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($new)?></div><div class="l">New Alerts Loaded</div></div>
  <div class="kpi"><div class="n"><?=h(count($rows))?></div><div class="l">Loaded</div></div>
  <div class="kpi"><div class="n"><?=h($email)?></div><div class="l">Email Sent</div></div>
  <div class="kpi"><div class="n"><?=h($sms)?></div><div class="l">SMS Sent</div></div>
</section>

<section class="panel">
<h2>Alert Feed</h2>
<div class="controls">
  <a class="btn light" href="?status=new">New</a>
  <a class="btn light" href="?status=acknowledged">Acknowledged</a>
  <a class="btn light" href="?status=all">All</a>
  <a class="btn gold" href="/lead-engine/build-hot-alerts.php?key=<?=h($cronKey)?>" target="_blank">Build Alerts Now</a>
</div>

<?php if(!$rows): ?><div class="card muted">No hot lead alerts in this view.</div><?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="card">
  <div class="top">
    <div>
      <strong><?=h($r['name'] ?: 'Unknown')?></strong>
      <div class="muted"><?=h($r['phone'])?> · <?=h($r['email'])?> · <?=h($r['town'])?></div>
      <div class="muted"><?=h($r['source'])?> · <?=h($r['address'])?> · <?=h($r['created_at'])?></div>
    </div>
    <div>
      <span class="badge <?=h($r['priority'] ?: 'hot')?>"><?=h($r['priority'] ?: 'hot')?> <?=h($r['lead_score'])?></span>
      <span class="badge <?=h($r['status'])?>"><?=h($r['status'])?></span>
    </div>
  </div>

  <div class="action"><?=h($r['recommended_action'])?></div>
  <p><strong>Reason:</strong> <?=h($r['reason'])?></p>

  <?php if($r['phone']): ?><a class="btn gold" href="tel:<?=h(phone_hotdash($r['phone']))?>">Call Now</a><?php endif; ?>
  <?php if($r['email']): ?><a class="btn light" href="mailto:<?=h($r['email'])?>">Email</a><?php endif; ?>

  <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="action" value="acknowledged">Acknowledge</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="action" value="done">Done</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn danger" name="action" value="dismissed">Dismiss</button></form>
</div>
<?php endforeach; ?>
</section>
</main>
</body>
</html>
