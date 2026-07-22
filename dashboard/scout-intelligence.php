<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scout-intelligence.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=sbq('scout_research_queue?select=*&order=created_at.desc&limit=160');
$done=count(array_filter($rows,fn($r)=>($r['status']??'')==='done'));
$running=count(array_filter($rows,fn($r)=>($r['status']??'')==='running'));
$queued=count(array_filter($rows,fn($r)=>($r['status']??'')==='queued'));
$phones=count(array_filter($rows,fn($r)=>!empty($r['found_phone'])));
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scout Intelligence</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=36">
<script src="/dashboard/assets/goliath-ui.js?v=36" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<style>
.scoutForm{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.scoutForm input,.scoutForm textarea{width:100%;background:#050b16;color:#fff;border:1px solid #263753;border-radius:12px;padding:10px;font-weight:800}
.scoutForm textarea{grid-column:1/-1;min-height:80px}
.statusPill{border-radius:999px;padding:4px 8px;font-size:10px;font-weight:950;color:#fff;background:#475569}.statusPill.done{background:#166534}.statusPill.running{background:#b7791f}.statusPill.failed{background:#991b1b}
@media(max-width:800px){.scoutForm{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div><h1>Scout Intelligence</h1><p>Public-record research queue for owner/contact enrichment. Scout finds what is allowed, cites sources, and sends Jessica the package.</p></div>
  <div class="g-actions">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <button class="g-btn g-btn-green" onclick="enqueueScout()">🕵️ Assign Scout</button>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-blue"><div class="n"><?=count($rows)?></div><strong>Total</strong><small>research items</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=$queued?></div><strong>Queued</strong><small>waiting</small></a>
  <a class="g-kpi g-purple"><div class="n"><?=$running?></div><strong>Running</strong><small>Scout working</small></a>
  <a class="g-kpi g-green"><div class="n"><?=$phones?></div><strong>Phones Found</strong><small>verified leads</small></a>
  <a class="g-kpi g-red"><div class="n"><?=$done?></div><strong>Complete</strong><small>research packages</small></a>
</section>

<section class="g-panel">
<h2>Assign Scout <span>owner / property / public search</span></h2>
<div class="g-inner">
  <div class="scoutForm">
    <input id="owner_name" placeholder="Owner name">
    <input id="property_address" placeholder="Property address">
    <input id="town" placeholder="Town">
    <input id="price" placeholder="Price / value">
    <textarea id="notes" placeholder="Notes for Scout..."></textarea>
  </div>
</div>
</section>

<br>

<section class="g-panel">
<h2>Scout Queue <span>click a row for full intelligence</span></h2>
<div class="g-tableWrap">
<table class="g-stealthTable">
<thead><tr><th></th><th>Owner / Address</th><th>Town</th><th>Phone</th><th>Status</th><th>Confidence</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($rows as $r):
$intel=[
  'name'=>$r['owner_name'] ?: 'Unknown Owner',
  'phone'=>$r['found_phone'] ?: '',
  'email'=>$r['found_email'] ?: '',
  'address'=>$r['property_address'] ?: '',
  'status'=>($r['status']??'').' · Confidence '.($r['confidence']??0).'%',
  'notes'=>$r['research_notes'] ?: 'Scout has not finished this research package yet.',
  'recommended_action'=>$r['recommended_action'] ?: 'When Scout completes research, send to Jessica for outreach and Einstein for 90-day MLS statistics.',
  'content_angle'=>'If seller opportunity: create door-knock package, House Detective pitch, local market update, and personalized drip.'
];
?>
<tr onclick='openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>
<td><span class="g-statusDot <?=!empty($r['found_phone'])?'g-s-green':(($r['status']??'')==='running'?'g-s-yellow':'g-s-red')?>"></span></td>
<td><div class="g-name"><?=h($r['owner_name'] ?: 'Unknown Owner')?></div><div class="g-subtle"><?=h($r['property_address'] ?: 'No address')?></div></td>
<td><?=h($r['town'] ?: '')?></td>
<td><?=h($r['found_phone'] ?: '—')?></td>
<td><span class="statusPill <?=h($r['status']??'')?>"><?=h($r['status']??'queued')?></span></td>
<td><?=h($r['confidence']??0)?>%</td>
<td><div class="g-pillRow"><?php if(!empty($r['found_phone'])):?><a class="g-pill g-pill-blue" href="tel:+1<?=h(preg_replace('/\D+/','',$r['found_phone']))?>" onclick="event.stopPropagation()">☎ Call</a><?php endif; ?><button class="g-pill g-pill-gold" onclick="event.stopPropagation();sendToJessica(<?=h(json_encode($r['id']))?>)">Jessica</button></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</div>

<script>
const KEY=<?=json_encode($key)?>;
async function enqueueScout(){
  const payload={
    key:KEY,
    owner_name:document.getElementById('owner_name').value,
    property_address:document.getElementById('property_address').value,
    town:document.getElementById('town').value,
    price:document.getElementById('price').value,
    metadata:{notes:document.getElementById('notes').value,source:'scout_intelligence_page'}
  };
  const r=await fetch('/lead-engine/scout-research-enqueue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const j=await r.json();
  gToast(j.success?'Scout assigned':'Scout issue',j.success?'Research assignment queued.':'Check SQL/table.');
}
async function sendToJessica(id){
  const r=await fetch('/lead-engine/goliath-event-bus.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,action:'command',command_type:'jessica_followup_from_scout',department:'Jessica',title:'Jessica received Scout intelligence package',prompt:'Use Scout research queue item '+id+' to begin appropriate call/text/email/drip follow-up.',priority:120,source:'scout_intelligence'})});
  const j=await r.json(); gToast(j.success?'Sent to Jessica':'Issue',j.success?'Jessica follow-up queued.':'Check event bus.');
}
</script>
</body>
</html>