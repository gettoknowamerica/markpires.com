<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_ah($method, $endpoint, $payload=null) {
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

function normalize_phone_ah($phone) {
  $d=preg_replace('/\D+/', '', (string)$phone);
  if(strlen($d)===10) return '+1'.$d;
  if(strlen($d)===11 && substr($d,0,1)==='1') return '+'.$d;
  return $phone;
}

function first_ah($name) {
  $name=trim((string)$name);
  if($name==='') return 'there';
  $p=preg_split('/\s+/', $name);
  return $p[0] ?: 'there';
}

function retell_call_ah($lead) {
  if (!defined('RETELL_API_KEY') || !RETELL_API_KEY) return ['ok'=>false,'error'=>'RETELL_API_KEY missing'];
  if (!defined('RETELL_FROM_NUMBER') || !RETELL_FROM_NUMBER) return ['ok'=>false,'error'=>'RETELL_FROM_NUMBER missing'];

  $from = normalize_phone_ah(RETELL_FROM_NUMBER);
  $to = normalize_phone_ah($lead['phone'] ?? '');
  $agent = $lead['retell_agent_id'] ?: (defined('RETELL_AGENT_ID_MARK_PRIORITY') ? RETELL_AGENT_ID_MARK_PRIORITY : '');

  if (!$to || !$agent) return ['ok'=>false,'error'=>'Missing phone or agent id'];

  $dynamic=[
    'first_name'=>first_ah($lead['name'] ?? ''),
    'name'=>$lead['name'] ?? '',
    'email'=>$lead['email'] ?? '',
    'phone'=>$to,
    'address'=>$lead['address'] ?? '',
    'town'=>$lead['town'] ?? '',
    'timeline'=>$lead['timeline'] ?? '',
    'goal'=>$lead['goal'] ?? '',
    'route'=>$lead['route'] ?? '',
    'lead_score'=>(string)($lead['lead_score'] ?? ''),
    'source'=>'after_hours_manager_manual_fire',
    'original_source'=>$lead['source'] ?? '',
    'page_url'=>$lead['page_url'] ?? ''
  ];

  $payload=[
    'from_number'=>$from,
    'to_number'=>$to,
    'agent_id'=>$agent,
    'contact_dynamic_variables'=>$dynamic,
    'metadata'=>$dynamic
  ];

  $ch=curl_init('https://api.retellai.com/v2/create-phone-call');
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'Authorization: Bearer '.RETELL_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT=>20
  ]);

  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $decoded=json_decode($body,true);

  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'call_id'=>$decoded['call_id'] ?? '', 'payload'=>$payload];
}

$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id=$_POST['id'] ?? '';
  $action=$_POST['action'] ?? '';

  if($id && $action==='call_now') {
    $get=sb_ah('GET','after_hours_callbacks?select=*&id=eq.'.rawurlencode($id).'&limit=1');
    if($get['ok'] && !empty($get['data'][0])) {
      $lead=$get['data'][0];
      $call=retell_call_ah($lead);
      sb_ah('PATCH','after_hours_callbacks?id=eq.'.rawurlencode($id),[
        'status'=>$call['ok']?'called':'retell_error',
        'attempted_at'=>date('c'),
        'retell_call_id'=>$call['call_id'] ?? '',
        'retell_response'=>$call,
        'updated_at'=>date('c')
      ]);
      $msg=$call['ok']?'Jessica call fired now.':'Retell error: '.($call['body'] ?: $call['error']);
    } else {
      $msg='Could not load queued callback.';
    }
  }

  if($id && $action==='skip') {
    $res=sb_ah('PATCH','after_hours_callbacks?id=eq.'.rawurlencode($id),[
      'status'=>'skipped',
      'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Callback skipped.':'Skip failed: '.$res['body'];
  }

  if($id && $action==='reschedule') {
    $when=trim($_POST['scheduled_for'] ?? '');
    $ts=$when ? strtotime($when) : strtotime('tomorrow 8:00');
    $res=sb_ah('PATCH','after_hours_callbacks?id=eq.'.rawurlencode($id),[
      'status'=>'queued',
      'scheduled_for'=>date('c',$ts),
      'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Callback rescheduled.':'Reschedule failed: '.$res['body'];
  }

  if($id && $action==='mark_done') {
    $res=sb_ah('PATCH','after_hours_callbacks?id=eq.'.rawurlencode($id),[
      'status'=>'done',
      'updated_at'=>date('c')
    ]);
    $msg=$res['ok']?'Marked done.':'Update failed: '.$res['body'];
  }
}

$status=$_GET['status'] ?? 'active';
if($status==='all') {
  $endpoint='after_hours_callbacks?select=*&order=created_at.desc&limit=200';
} else {
  $endpoint='after_hours_callbacks?select=*&status=in.(queued,retell_error)&order=scheduled_for.asc&limit=200';
}
$res=sb_ah('GET',$endpoint);
$rows=$res['data'];

$queued=0;$errors=0;
foreach($rows as $r){
  if(($r['status']??'')==='queued')$queued++;
  if(($r['status']??'')==='retell_error')$errors++;
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>After-Hours Manager — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1300px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1300px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.l{font-size:11px;text-transform:uppercase;color:#777;letter-spacing:1px}
.panel{overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
.card{padding:17px 20px;border-bottom:1px solid #eee}.top{display:flex;justify-content:space-between;gap:14px}.muted{color:#777;font-size:13px}.detail{background:#faf9f6;border-radius:10px;padding:10px;margin-top:10px}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.queued{background:#fff4d7;color:#8a5a00}.called{background:#e6f7ec;color:#14783c}.retell_error{background:#ffeaea;color:#9b1c1c}.skipped,.done{background:#eee;color:#555}
.btn{border:0;background:#10101a;color:#fff;text-decoration:none;border-radius:8px;padding:8px 10px;font-size:12px;font-weight:800;margin:4px;cursor:pointer}.gold{background:var(--gold);color:#111}.light{background:#f2efe8;color:#111}.danger{background:#ffeaea;color:#9b1c1c}
input{padding:8px;border:1px solid var(--line);border-radius:8px}.msg{background:#e6f7ec;color:#14783c;border-radius:10px;padding:12px;margin-bottom:16px}
@media(max-width:900px){.grid{grid-template-columns:1fr}.inner,.top{display:block}.wrap{padding:14px}}
</style>
</head>
<body>
<header class="header"><div class="inner"><div><div class="brand">After-Hours Manager</div><div class="sub">Fire, skip, or reschedule Jessica’s morning callbacks.</div></div><div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/lead-velocity.php">Lead Velocity</a></div></div></header>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($queued)?></div><div class="l">Queued</div></div>
  <div class="kpi"><div class="n"><?=h($errors)?></div><div class="l">Errors</div></div>
  <div class="kpi"><div class="n"><?=h(count($rows))?></div><div class="l">Loaded</div></div>
</section>

<section class="panel">
  <h2>Callback Queue</h2>
  <?php if(!$rows): ?><div class="card muted">No after-hours callbacks in this view.</div><?php endif; ?>
  <?php foreach($rows as $r): $st=$r['status'] ?: 'queued'; ?>
    <div class="card">
      <div class="top">
        <div>
          <strong><?=h($r['name'] ?: 'Unknown')?></strong>
          <div class="muted"><?=h($r['phone'])?> · <?=h($r['email'])?> · <?=h($r['town'])?></div>
          <div class="muted"><?=h($r['type'])?> · Score <?=h($r['lead_score'])?> · Scheduled <?=h($r['scheduled_for'])?></div>
        </div>
        <div><span class="badge <?=h($st)?>"><?=h($st)?></span></div>
      </div>
      <div class="detail">
        <strong>Need:</strong> <?=h($r['goal'])?> <?=h($r['timeline'])?><br>
        <strong>Address:</strong> <?=h($r['address'])?><br>
        <strong>Message:</strong> <?=h($r['message'])?>
      </div>

      <?php if($r['phone']): ?><a class="btn gold" href="tel:<?=h(normalize_phone_ah($r['phone']))?>">Call Lead</a><?php endif; ?>

      <form method="post" style="display:inline" onsubmit="return confirm('Fire Jessica call now?');">
        <input type="hidden" name="id" value="<?=h($r['id'])?>">
        <button class="btn gold" name="action" value="call_now">Fire Jessica Now</button>
      </form>

      <form method="post" style="display:inline">
        <input type="hidden" name="id" value="<?=h($r['id'])?>">
        <input name="scheduled_for" placeholder="tomorrow 8:00" style="width:150px">
        <button class="btn light" name="action" value="reschedule">Reschedule</button>
      </form>

      <form method="post" style="display:inline">
        <input type="hidden" name="id" value="<?=h($r['id'])?>">
        <button class="btn" name="action" value="mark_done">Done</button>
      </form>

      <form method="post" style="display:inline">
        <input type="hidden" name="id" value="<?=h($r['id'])?>">
        <button class="btn danger" name="action" value="skip">Skip</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>
</main>
</body>
</html>
