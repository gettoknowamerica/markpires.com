<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-launch-candidate.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Goliath Launch Candidate</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=36">
<script src="/dashboard/assets/goliath-ui.js?v=36" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<script src="/dashboard/assets/goliath-command-bus-v35.js?v=35" defer></script>
<style>
.launchGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.launchCard{background:#08111f;border:1px solid #263753;border-radius:18px;padding:14px}
.launchCard h3{margin:0;color:#c8a96e}.launchCard p{color:#cbd5e1;line-height:1.4}
.resultBox{white-space:pre-wrap;background:#020617;border:1px solid #263753;border-radius:14px;padding:12px;color:#cbd5e1;min-height:150px}
@media(max-width:900px){.launchGrid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div>
    <h1>Launch Candidate</h1>
    <p>One page to run end-to-end Goliath tests before final revisions.</p>
  </div>
  <div class="g-actions">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <a class="g-btn g-btn-dark" href="/dashboard/goliath-system-health.php">System Health</a>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-red"><div class="n">1</div><strong>Mission</strong><small>create timeline</small></a>
  <a class="g-kpi g-blue"><div class="n">2</div><strong>Voice</strong><small>command route</small></a>
  <a class="g-kpi g-purple"><div class="n">3</div><strong>Studio</strong><small>Director render</small></a>
  <a class="g-kpi g-green"><div class="n">4</div><strong>Follow-Up</strong><small>Jessica drip</small></a>
  <a class="g-kpi g-gold"><div class="n">5</div><strong>Daily Brief</strong><small>Rockefeller</small></a>
  <a class="g-kpi g-orange"><div class="n">6</div><strong>Mission Control</strong><small>events</small></a>
</section>

<section class="launchGrid">
  <div class="launchCard">
    <h3>Full Mission Test</h3>
    <p>Creates a complete Jessica → Einstein → Scout → Scorsese → Shakespeare → Rockefeller mission.</p>
    <button class="g-btn g-btn-gold" onclick="runLaunch('mission')">Run Mission Test</button>
  </div>
  <div class="launchCard">
    <h3>Director Video Test</h3>
    <p>Queues Scorsese to create a short video mission through the event bus and local worker.</p>
    <button class="g-btn g-btn-purple" onclick="runLaunch('director_video')">Run Director Test</button>
  </div>
  <div class="launchCard">
    <h3>Jessica Drip Test</h3>
    <p>Queues a personalized follow-up campaign with MLS-stat instruction for Einstein.</p>
    <button class="g-btn g-btn-blue" onclick="runLaunch('drip')">Run Drip Test</button>
  </div>
  <div class="launchCard">
    <h3>Rockefeller Briefing Test</h3>
    <p>Creates a briefing command and event for Mission Control.</p>
    <button class="g-btn g-btn-green" onclick="runLaunch('briefing')">Run Briefing Test</button>
  </div>
</section>

<br>
<section class="g-panel">
<h2>Launch Test Result <span>watch Mission Control after each run</span></h2>
<div class="g-inner">
  <div id="result" class="resultBox">Ready. Run one test at a time, then check Mission Control and the local worker terminal.</div>
</div>
</section>
</main>
</div>
<script>
const KEY=<?=json_encode($key)?>;
async function runLaunch(type){
  document.getElementById('result').textContent='Running '+type+' test...';
  const r=await fetch('/lead-engine/goliath-launch-test.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({key:KEY,type})
  });
  const txt=await r.text();
  document.getElementById('result').textContent=txt;
  try{const j=JSON.parse(txt); if(j.success&&j.mission_id){setTimeout(()=>location.href='/dashboard/goliath-mission.php?mission_id='+encodeURIComponent(j.mission_id),1200);}}catch(e){}
}
</script>
</body>
</html>