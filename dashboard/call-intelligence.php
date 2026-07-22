<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function supabase_get_ci($endpoint) {
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
  return ['ok'=>$http>=200 && $http<300,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function dur($s){ $s=(int)$s; return floor($s/60).':'.str_pad((string)($s%60),2,'0',STR_PAD_LEFT); }

$res = supabase_get_ci('call_intelligence?select=*&order=created_at.desc&limit=250');
$calls = $res['data'];

$total=count($calls); $hot=0; $appt=0; $answered=0; $dur=0;
foreach($calls as $c){
  if(!empty($c['hot_lead'])) $hot++;
  if(!empty($c['appointment_requested'])) $appt++;
  if((int)($c['duration_seconds']??0)>0) $answered++;
  $dur += (int)($c['duration_seconds']??0);
}
$avg = $total ? round($dur/max(1,$total)) : 0;
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jessica Call Intelligence</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:22px 28px;display:flex;justify-content:space-between}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1350px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px}
.kpi{background:#fff;border-radius:14px;padding:18px}.n{font-size:30px;font-weight:900}.l{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#777}
.panel{background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.06);overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid #eee}
.call{padding:18px 20px;border-bottom:1px solid #eee}.top{display:flex;justify-content:space-between;gap:14px}.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase;background:#eee}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.nurture{background:#eee;color:#555}.yes{background:#e6f7ec;color:#14783c}.muted{color:#777;font-size:13px}.btn{display:inline-block;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;margin-top:8px}
details{margin-top:10px;background:#faf9f6;border-radius:10px;padding:10px}pre{white-space:pre-wrap;font-family:inherit;font-size:13px;line-height:1.55}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}.top{display:block}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header"><strong>Jessica Call Intelligence</strong><div><a href="/dashboard/">Main Dashboard</a></div></div>
<main class="wrap">
<?php if(!$res['ok']): ?><div class="panel"><h2>Error</h2><div class="call"><?=h($res['body'] ?: $res['error'])?></div></div><?php endif; ?>
<section class="grid">
  <div class="kpi"><div class="n"><?=h($total)?></div><div class="l">Calls Captured</div></div>
  <div class="kpi"><div class="n"><?=h($answered)?></div><div class="l">Answered</div></div>
  <div class="kpi"><div class="n"><?=h($hot)?></div><div class="l">Hot Leads</div></div>
  <div class="kpi"><div class="n"><?=h($appt)?></div><div class="l">Appointments</div></div>
  <div class="kpi"><div class="n"><?=h(dur($avg))?></div><div class="l">Avg Call</div></div>
</section>
<section class="panel">
<h2>Call Feed</h2>
<?php if(!$calls): ?><div class="call muted">No Retell webhook calls captured yet.</div><?php endif; ?>
<?php foreach($calls as $c): ?>
  <div class="call">
    <div class="top">
      <div>
        <strong><?=h($c['name'] ?: 'Unknown')?></strong>
        <div class="muted"><?=h($c['phone'])?> · <?=h($c['town'])?> · <?=h($c['address'])?> · <?=h(date('M j, g:i A', strtotime($c['created_at'] ?? 'now')))?></div>
      </div>
      <div>
        <span class="badge <?=h($c['priority'] ?: 'nurture')?>"><?=h($c['priority'])?> · <?=h($c['lead_score'])?></span>
        <?php if(!empty($c['appointment_requested'])): ?><span class="badge yes">Appointment</span><?php endif; ?>
        <?php if(!empty($c['hot_lead'])): ?><span class="badge hot">Hot</span><?php endif; ?>
      </div>
    </div>
    <?php if($c['summary']): ?><p><?=h($c['summary'])?></p><?php endif; ?>
    <div class="muted">Duration: <?=h(dur($c['duration_seconds']))?> · Status: <?=h($c['call_status'])?> · End: <?=h($c['end_reason'])?> · Motivation: <?=h($c['motivation'])?></div>
    <?php if($c['recording_url']): ?><a class="btn" href="<?=h($c['recording_url'])?>" target="_blank">▶ Listen</a><?php endif; ?>
    <?php if($c['transcript']): ?><details><summary>Transcript</summary><pre><?=h($c['transcript'])?></pre></details><?php endif; ?>
  </div>
<?php endforeach; ?>
</section>
</main>
</body>
</html>
