<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-social.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

$platforms=[
 ['youtube','YouTube','▶️','Official OAuth/API recommended','Videos, Shorts, thumbnails'],
 ['instagram','Instagram','📸','Meta connection recommended','Reels, posts, stories'],
 ['facebook','Facebook','f','Meta connection recommended','Pages, reels, groups'],
 ['tiktok','TikTok','♪','Connector/API recommended','Short-form video'],
 ['linkedin','LinkedIn','in','OAuth/API recommended','Professional posts'],
 ['x','X / Twitter','𝕏','API key recommended','Short updates'],
 ['threads','Threads','◎','Meta connection recommended','Conversation posts'],
 ['pinterest','Pinterest','📌','OAuth/API recommended','Pins and boards'],
 ['google_business','Google Business','G','Google OAuth recommended','Local business updates'],
 ['email_list','Email List','✉️','SMTP/list provider','Fans, buyers, sellers'],
 ['youtube_music','YouTube Music','♫','Reference/artist hub','Music promo'],
 ['spotify','Spotify','♬','Reference/artist hub','Artist links']
];

$existing=[];
foreach(rows("SELECT * FROM goliath_social_accounts") as $r){$existing[$r['platform_key']]=$r;}
$queue=rows("SELECT * FROM goliath_social_queue ORDER BY COALESCE(scheduled_at,created_at) ASC, id DESC LIMIT 60");
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Goliath Social Command</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33">
<link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
<style>
:root{--gold:#d4af37;--panel:#07111f;--line:#ffffff20}
body{background:radial-gradient(circle at 20% 0%,rgba(14,165,233,.22),transparent 35%),linear-gradient(180deg,#030712,#0f172a 75%,#030712);color:#fff}
.hero,.panel,.platform{background:#07111f;border:1px solid var(--line);border-radius:22px;padding:16px;box-shadow:0 18px 45px #0007;margin-bottom:14px}
.hero h1{margin:0;font-size:36px}.hero p,.muted{color:#94a3b8}
.btn{display:inline-flex;align-items:center;gap:6px;border-radius:12px;padding:10px 13px;font-weight:1000;text-decoration:none;border:1px solid #ffffff22;color:#fff;background:#111827;cursor:pointer}
.btn.gold{background:linear-gradient(135deg,#f5d48b,#d4af37);color:#111;border:0}.btn.green{background:linear-gradient(135deg,#16a34a,#064e3b)}.btn.blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}.btn.orange{background:linear-gradient(135deg,#f97316,#7c2d12)}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.layout{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:14px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px}
.head{display:flex;align-items:center;justify-content:space-between;gap:10px}.logo{width:46px;height:46px;border-radius:15px;background:#0f172a;border:1px solid #ffffff22;display:flex;align-items:center;justify-content:center;font-weight:1000;font-size:22px}
.status{border-radius:999px;padding:5px 8px;font-size:11px;font-weight:1000}.on{background:#14532d;color:#dcfce7}.off{background:#450a0a;color:#fee2e2}.pending{background:#422006;color:#fde68a}
input,select,textarea{width:100%;box-sizing:border-box;background:#020617;color:#fff;border:1px solid #ffffff24;border-radius:12px;padding:10px;margin:7px 0}
label{font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:1000}
.safe{background:#052e1a;border:1px solid #22c55e55;border-radius:16px;padding:12px;color:#dcfce7;margin:10px 0}
.queueItem,.calendarBlock{background:#050914;border:1px solid #ffffff16;border-radius:14px;padding:12px;margin:8px 0}.queueItem b,.calendarBlock b{color:#f5d48b}
.toggleRow{display:grid;grid-template-columns:1fr 1fr;gap:8px}
@media(max-width:1050px){.layout{grid-template-columns:1fr}.toggleRow{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="shell"><?php if(file_exists(__DIR__.'/includes/goliath-sidebar-v33.php')) require __DIR__.'/includes/goliath-sidebar-v33.php'; ?>
<main class="main">
<section class="hero">
  <h1>📡 Goliath Social Command</h1>
  <p>Your all-in-one visual posting and scheduling platform. Add platform access notes/connectors here, then Jessica can schedule approved media by best posting windows.</p>
  <div class="safe"><b>Friendly setup:</b> You can type usernames and setup notes here. For actual posting, use OAuth/API/app tokens when possible. Avoid saving raw passwords unless you intentionally accept that risk.</div>
  <div class="actions">
    <a class="btn gold" href="/dashboard/goliath-mission-control.php">Mission Control</a>
    <a class="btn blue" href="/dashboard/scorsese-studio-pro.php">Scorsese Studio Pro</a>
    <a class="btn green" target="_blank" href="/lead-engine/goliath-social-install.php?key=<?=h($key)?>">Install / Verify Tables</a>
  </div>
</section>

<div class="layout">
<section>
  <h2>Platform Connections</h2>
  <div class="grid">
    <?php foreach($platforms as $p): $e=$existing[$p[0]]??[]; $status=$e['status']??'disconnected'; ?>
    <form class="platform" method="post" action="/lead-engine/goliath-social-save.php">
      <input type="hidden" name="key" value="<?=h($key)?>">
      <input type="hidden" name="platform_key" value="<?=h($p[0])?>">
      <input type="hidden" name="platform_name" value="<?=h($p[1])?>">
      <div class="head">
        <div style="display:flex;align-items:center;gap:10px"><div class="logo"><?=h($p[2])?></div><div><b><?=h($p[1])?></b><div class="muted"><?=h($p[4])?></div></div></div>
        <span class="status <?=$status==='connected'?'on':($status==='pending'?'pending':'off')?>"><?=$status==='connected'?'✓ Connected':($status==='pending'?'Pending':'✕ Disconnected')?></span>
      </div>
      <label>Username / Handle</label><input name="username" value="<?=h($e['username']??'')?>" placeholder="@markpires">
      <div class="toggleRow">
        <div><label>Connection Method</label><select name="connection_method"><option value="oauth">OAuth / official login</option><option value="api">API token / app password</option><option value="manual">Manual / notes only</option><option value="future">Future connector</option></select></div>
        <div><label>Status</label><select name="status"><option value="disconnected" <?=$status==='disconnected'?'selected':''?>>Disconnected</option><option value="pending" <?=$status==='pending'?'selected':''?>>Pending Setup</option><option value="connected" <?=$status==='connected'?'selected':''?>>Connected</option></select></div>
      </div>
      <label>Credential / Setup Note</label><input name="credential_note" value="<?=h($e['credential_note']??'')?>" placeholder="OAuth complete, API token reference, app password note, or manual login note">
      <div class="actions"><button class="btn gold" type="submit">Save</button><button class="btn blue" type="button" onclick="alert('OAuth connector launch comes next for this platform.')">Connect</button></div>
    </form>
    <?php endforeach; ?>
  </div>
</section>

<aside>
  <section class="panel">
    <h2>Jessica Social Calendar</h2>
    <p class="muted">Approved media from Scorsese, captions from Shakespeare/Columbo, and final scheduling from Jessica will appear here.</p>
    <div class="calendarBlock"><b>Morning</b><br><span class="muted">LinkedIn, Google Business, email, real estate advice.</span></div>
    <div class="calendarBlock"><b>Midday</b><br><span class="muted">Instagram/Facebook, Discover CT community clips.</span></div>
    <div class="calendarBlock"><b>Evening</b><br><span class="muted">YouTube, TikTok, BeatSeat, storytelling and longer watch-time content.</span></div>
  </section>

  <section class="panel">
    <h2>Approved Social Queue</h2>
    <?php if(!count($queue)): ?><p class="muted">No queued posts yet.</p><?php endif; ?>
    <?php foreach($queue as $q): ?>
      <div class="queueItem"><b><?=h($q['title']??'Social Post')?></b><br><span class="muted"><?=h($q['platform']??'all')?> · <?=h($q['status']??'draft')?> · <?=h($q['scheduled_at']??'unscheduled')?></span></div>
    <?php endforeach; ?>
  </section>
</aside>
</div>
</main></div>
</body></html>