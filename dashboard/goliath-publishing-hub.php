<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-publishing-hub.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$accounts=sbq('goliath_social_accounts?select=*&order=created_at.desc&limit=100');
$queue=sbq('goliath_publish_queue?select=*&order=scheduled_at.asc,created_at.desc&limit=160');
$media=sbq('media_projects?select=*&order=created_at.desc&limit=40');
$platforms=['TikTok','Instagram','Facebook','YouTube Shorts','LinkedIn','Pinterest','Threads','X','Blog','Email Drip','Press Release'];
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Shakespeare Publishing Hub</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=36">
<script src="/dashboard/assets/goliath-ui.js?v=36" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<style>
.pubGrid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(360px,.9fr);gap:14px}
.platformGrid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.platformBox{background:#08111f;border:1px solid #263753;border-radius:12px;padding:9px;font-weight:900;color:#fff}
.platformBox input{margin-right:6px}
.pubInput,.pubText{width:100%;background:#050b16;color:#fff;border:1px solid #263753;border-radius:12px;padding:10px;font-weight:800}
.pubText{min-height:120px;line-height:1.45}
.accountRow{display:grid;grid-template-columns:120px 1fr 90px;gap:8px;align-items:center;padding:8px;border-bottom:1px solid #1f2a44}
.status{border-radius:999px;padding:4px 8px;font-size:10px;font-weight:950;text-align:center;background:#991b1b;color:#fff}
.status.manual_ready,.status.connected{background:#166534}
@media(max-width:1000px){.pubGrid{grid-template-columns:1fr}.platformGrid{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.platformGrid{grid-template-columns:1fr}.accountRow{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div>
    <h1>Shakespeare Publishing Hub</h1>
    <p>Social accounts, API credentials, approval queue, and daily publishing schedule.</p>
  </div>
  <div class="g-actions">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <button class="g-btn g-btn-blue" onclick="queuePost()">📡 Queue Selected</button>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-blue"><div class="n"><?=count($accounts)?></div><strong>Accounts</strong><small>platform connections</small></a>
  <a class="g-kpi g-green"><div class="n"><?=count(array_filter($queue,fn($q)=>($q['status']??'')==='queued'))?></div><strong>Queued</strong><small>scheduled posts</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=count($media)?></div><strong>Media</strong><small>ready assets</small></a>
  <a class="g-kpi g-purple"><div class="n">Auto</div><strong>Best Times</strong><small>Shakespeare timing</small></a>
</section>

<section class="pubGrid">
<div class="stack">
<section class="g-panel">
<h2>Create Publishing Queue <span>select channels</span></h2>
<div class="g-inner">
  <label class="g-subtle">Title</label>
  <input class="pubInput" id="pubTitle" value="Goliath approved creation">
  <br><br>
  <label class="g-subtle">Media URL</label>
  <select class="pubInput" id="mediaUrl">
    <option value="">No media selected / text-only</option>
    <?php foreach($media as $m): if(!empty($m['source_url'])): ?>
      <option value="<?=h($m['source_url'])?>"><?=h(($m['title']??'Media').' — '.$m['source_url'])?></option>
    <?php endif; endforeach; ?>
  </select>
  <br><br>
  <label class="g-subtle">Caption / Post Copy</label>
  <textarea class="pubText" id="caption">High-value local insight from Mark Pires and Goliath. Review, approve, and distribute across the selected channels.</textarea>
  <br><br>
  <label class="g-subtle">Hashtags</label>
  <input class="pubInput" id="hashtags" value="#FairfieldCountyCT #RealEstate #DiscoverCT #MarkPires">
  <br><br>
  <div class="platformGrid">
    <?php foreach($platforms as $p): ?>
    <label class="platformBox"><input type="checkbox" value="<?=h($p)?>" class="plat" <?=in_array($p,['TikTok','Instagram','Facebook','YouTube Shorts'])?'checked':''?>> <?=h($p)?></label>
    <?php endforeach; ?>
  </div>
  <br>
  <button class="g-btn g-btn-blue" onclick="queuePost()">📡 Shakespeare Queue</button>
</div>
</section>

<section class="g-panel">
<h2>Publishing Queue <span>review before posting</span></h2>
<div class="g-tableWrap">
<table class="g-stealthTable">
<thead><tr><th>Platform</th><th>Title</th><th>Scheduled</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($queue as $q): ?>
<tr onclick='openGoliathDrawer(<?=h(json_encode(['name'=>$q['title']??'Post','status'=>($q['platform']??'').' · '.($q['status']??''),'notes'=>$q['caption']??'','recommended_action'=>'Review, approve, and publish through Shakespeare.','content_angle'=>$q['hashtags']??''],JSON_UNESCAPED_SLASHES))?>)'>
<td><?=h($q['platform']??'')?></td><td><div class="g-name"><?=h($q['title']??'Post')?></div><div class="g-subtle"><?=h($q['media_url']??'')?></div></td><td><?=h(!empty($q['scheduled_at'])?date('M j g:i A',strtotime($q['scheduled_at'])):'')?></td><td><span class="g-pill g-pill-gold"><?=h($q['status']??'draft')?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</div>

<aside class="stack">
<section class="g-panel">
<h2>Account/API Vault <span>manual setup</span></h2>
<div class="g-inner">
  <p class="g-subtle">Store platform names, profile URLs, and API placeholders here. OAuth/live posting comes next; this gives Shakespeare one central publishing map.</p>
  <select class="pubInput" id="accPlatform"><?php foreach($platforms as $p): ?><option><?=h($p)?></option><?php endforeach; ?></select><br><br>
  <input class="pubInput" id="accName" placeholder="Account name"><br><br>
  <input class="pubInput" id="profileUrl" placeholder="Profile URL"><br><br>
  <input class="pubInput" id="apiKey" placeholder="API Key / Client ID"><br><br>
  <input class="pubInput" id="apiSecret" placeholder="API Secret / Client Secret"><br><br>
  <textarea class="pubText" id="accNotes" placeholder="Notes, OAuth status, posting rules..."></textarea><br><br>
  <button class="g-btn g-btn-gold" onclick="saveAccount()">Save Account</button>
</div>
</section>

<section class="g-panel">
<h2>Connected Accounts</h2>
<div class="g-inner">
<?php foreach($accounts as $a): ?>
  <div class="accountRow"><strong><?=h($a['platform']??'')?></strong><div><div class="g-name"><?=h($a['account_name']??'')?></div><div class="g-subtle"><?=h($a['profile_url']??'')?></div></div><span class="status <?=h($a['status']??'')?>"><?=h($a['status']??'not_connected')?></span></div>
<?php endforeach; ?>
<?php if(!count($accounts)): ?><p class="g-subtle">No accounts saved yet.</p><?php endif; ?>
</div>
</section>
</aside>
</section>
</main>
</div>

<script>
const KEY=<?=json_encode($key)?>;
async function queuePost(){
  const platforms=[...document.querySelectorAll('.plat:checked')].map(x=>x.value);
  const r=await fetch('/lead-engine/shakespeare-publish-queue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,action:'queue',title:document.getElementById('pubTitle').value,media_url:document.getElementById('mediaUrl').value,caption:document.getElementById('caption').value,hashtags:document.getElementById('hashtags').value,platforms,source:'publishing_hub'})});
  const j=await r.json();
  gToast(j.success?'Shakespeare queued posts':'Publishing issue',j.success?platforms.length+' channels queued.':'Check endpoint/table.');
}
async function saveAccount(){
  const r=await fetch('/lead-engine/shakespeare-publish-queue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,action:'save_account',platform:document.getElementById('accPlatform').value,account_name:document.getElementById('accName').value,profile_url:document.getElementById('profileUrl').value,api_key:document.getElementById('apiKey').value,api_secret:document.getElementById('apiSecret').value,notes:document.getElementById('accNotes').value,status:'manual_ready'})});
  const j=await r.json();
  gToast(j.success?'Account saved':'Account issue',j.success?'Shakespeare can see this channel.':'Check endpoint/table.');
}
</script>
</body>
</html>