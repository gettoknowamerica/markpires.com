<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-mission.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);$b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];}
$mission=$_GET['mission_id']??'';
$commands=$mission?sbq('goliath_commands?select=*&mission_id=eq.'.rawurlencode($mission).'&order=priority.desc,created_at.asc&limit=50'):[];
$events=$mission?sbq('goliath_events?select=*&mission_id=eq.'.rawurlencode($mission).'&order=created_at.asc&limit=100'):[];
if(!$mission){$latest=sbq('goliath_commands?select=mission_id&not.mission_id=is.null&order=created_at.desc&limit=1');$mission=$latest[0]['mission_id']??'';if($mission){header('Location:/dashboard/goliath-mission.php?mission_id='.rawurlencode($mission));exit;}}
$roi=0;foreach($events as $e){$roi+=(float)($e['roi_estimate']??0);}
?><!doctype html>
<html><head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Goliath Mission</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<style>
.missionHero{background:linear-gradient(135deg,#111827,#07101d);border:1px solid #c8a96e55;border-radius:20px;padding:16px;margin-bottom:14px;box-shadow:0 18px 55px #0009}
.missionHero h2{font:900 30px Georgia,serif;color:#c8a96e;margin:0}.missionHero p{color:#cbd5e1}
.timeline{display:grid;gap:10px}
.tEvent{display:grid;grid-template-columns:90px 120px 1fr 70px;gap:10px;background:#08111f;border:1px solid #263753;border-radius:14px;padding:11px;align-items:start}
.dept{border-radius:999px;padding:5px 8px;font-size:11px;font-weight:950;color:#fff;text-align:center}.Jessica{background:#b7791f}.Einstein{background:#475569}.Scout{background:#166534}.Scorsese{background:#6d28d9}.Shakespeare{background:#1d4ed8}.Rockefeller{background:#991b1b}
.progress{height:8px;background:#020617;border-radius:999px;overflow:hidden}.progress span{display:block;height:100%;background:#c8a96e}
@media(max-width:800px){.tEvent{grid-template-columns:1fr}.dept{text-align:left}}
</style>
</head><body>
<div class="g-shell"><?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?><main class="g-main">
<section class="g-top"><div><h1>Mission Timeline</h1><p>One mission. Every department. Every event. No lost threads.</p></div><div class="g-actions"><button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button><a class="g-btn g-btn-dark" href="/dashboard/goliath-mission-control.php">Mission Control</a></div></section>
<section class="missionHero"><h2><?=h($mission?:'No Mission Yet')?></h2><p>Rockefeller tracks the complete chain: Jessica → Einstein → Scout → Scorsese → Shakespeare → Jessica → Rockefeller.</p><div class="progress"><span style="width:<?=min(100,count($events)*12)?>%"></span></div></section>
<section class="g-kpis">
<a class="g-kpi g-red"><div class="n">$<?=number_format($roi,0)?></div><strong>Visible ROI</strong><small>mission events</small></a>
<a class="g-kpi g-blue"><div class="n"><?=count($commands)?></div><strong>Commands</strong><small>department tasks</small></a>
<a class="g-kpi g-green"><div class="n"><?=count(array_filter($commands,fn($c)=>($c['status']??'')==='done'))?></div><strong>Complete</strong><small>finished tasks</small></a>
<a class="g-kpi g-gold"><div class="n"><?=count($events)?></div><strong>Events</strong><small>timeline</small></a>
</section>
<section class="g-panel"><h2>Mission Timeline <span>live event chain</span></h2><div class="g-inner timeline">
<?php if(!$events):?><div class="g-drawerBox"><h3>No events yet</h3><div class="g-drawerValue">Create a mission from Follow-Up Command, Daily Hot Sheet, Voice, or Studio.</div></div><?php endif;?>
<?php foreach($events as $e): $dt=!empty($e['created_at'])?date('h:i A',strtotime($e['created_at'])):''; $dept=$e['department']??'Rockefeller';?>
<div class="tEvent" onclick='openGoliathDrawer(<?=h(json_encode(['name'=>$e['title']??'Mission Event','status'=>$dept.' · '.($e['status']??''),'notes'=>$e['detail']??'','recommended_action'=>'Continue the mission chain and move the next department forward.'],JSON_UNESCAPED_SLASHES))?>)'>
  <div class="g-subtle"><?=$dt?></div>
  <div class="dept <?=h($dept)?>"><?=h($dept)?></div>
  <div><div class="g-name"><?=h($e['title']??'Event')?></div><div class="g-subtle"><?=h($e['detail']??'')?></div></div>
  <div class="g-price"><?=h(($e['progress']??0).'%')?></div>
</div>
<?php endforeach;?>
</div></section>
</main></div></body></html>