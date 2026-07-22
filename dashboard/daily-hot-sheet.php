<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/daily-hot-sheet.php'));
  exit;
}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT=>35
  ]);
  $b=curl_exec($ch); curl_close($ch);
  $d=json_decode($b,true);
  return is_array($d)?$d:[];
}
function moneyfmt($v){
  $n=preg_replace('/[^\d.]/','',(string)$v);
  if($n==='') return '$0';
  $f=(float)$n;
  if($f>=1000000) return '$'.number_format($f/1000000,2).'M';
  if($f>=1000) return '$'.number_format($f/1000,0).'K';
  return '$'.number_format($f,0);
}
function pick($arr,$keys,$default=''){
  foreach($keys as $k){
    if(isset($arr[$k]) && $arr[$k]!=='' && $arr[$k]!==null) return $arr[$k];
  }
  return $default;
}

$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$since=date('c',strtotime('-24 hours'));

$leads=sbq('leads?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$expired=sbq('jessica_opportunity_engine?select=*&created_at=gte.'.rawurlencode($since).'&order=revenue_score.desc,created_at.desc&limit=120');
if(!count($expired)) $expired=sbq('expired_listings?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$numbers=sbq('owner_research_queue?select=*&created_at=gte.'.rawurlencode($since).'&not.phone=is.null&order=created_at.desc&limit=120');
$posts=sbq('blotato_distribution_queue?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$media=sbq('media_projects?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$blogs=sbq('content_posts?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=80');
if(!count($blogs)) $blogs=sbq('blog_posts?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=80');
$tasks=sbq('goliath_daily_tasks?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$commands=sbq('goliath_commands?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');
$events=sbq('goliath_events?select=*&created_at=gte.'.rawurlencode($since).'&order=created_at.desc&limit=120');

$roi=0;
foreach($events as $e){$roi+=(float)($e['roi_estimate']??0);}
foreach($expired as $e){$roi+=(float)($e['estimated_commission']??$e['commission']??0);}
if($roi<=0){$roi=(count($leads)*2500)+(count($expired)*14000)+(count($numbers)*3500)+(count($media)*1500)+(count($blogs)*900);}

$topName='Review the Revenue Engine';
$topType='Executive Priority';
$topDetail='Run Goliath, then work the highest value seller or buyer opportunity first.';
if(count($leads)){
  $l=$leads[0];
  $topName=pick($l,['name','full_name','email'],'New Website Lead');
  $topType='Jessica Follow-Up';
  $topDetail=trim((pick($l,['town','city']).' '.pick($l,['timeline','timeframe']).' '.pick($l,['message','notes'])));
}
if(count($expired)){
  $e=$expired[0];
  $topName=pick($e,['title','address','property_address','owner_name'],'Expired / Seller Opportunity');
  $topType='Rockefeller Priority';
  $topDetail=pick($e,['why_now','summary','detail','remarks'],'High-value seller opportunity. Prepare door-knock package and call strategy.');
}

$brief="Good morning, Mark. Rockefeller here. In the last 24 hours, Goliath tracked ".count($leads)." website leads, ".count($expired)." seller alerts, ".count($numbers)." verified phone opportunities, and ".(count($media)+count($posts)+count($blogs))." content or publishing items. Estimated visible opportunity is ".moneyfmt($roi).". Highest priority: ".$topName.". Recommended next move: ".$topDetail;

$allRows=[];
foreach($leads as $l){
  $allRows[]=['dept'=>'Jessica','type'=>'Website Lead','score'=>(int)($l['lead_score']??$l['adaptive_score']??70),'title'=>pick($l,['name','full_name','email'],'Website Lead'),'sub'=>trim((pick($l,['town','city']).' · '.pick($l,['timeline','timeframe']).' · '.pick($l,['message','notes']))),'raw'=>$l,'action'=>'Begin Drip'];
}
foreach($expired as $e){
  $allRows[]=['dept'=>'Rockefeller','type'=>'Seller Alert','score'=>(int)($e['revenue_score']??$e['score']??85),'title'=>pick($e,['title','address','property_address','owner_name'],'Seller Opportunity'),'sub'=>trim((pick($e,['town','city']).' · '.pick($e,['why_now','summary','detail']))),'raw'=>$e,'action'=>'Build Package'];
}
foreach($numbers as $n){
  $allRows[]=['dept'=>'Scout','type'=>'Phone Found','score'=>80,'title'=>pick($n,['owner_name','name','address'],'Contact Number'),'sub'=>trim((pick($n,['phone']).' · '.pick($n,['town']).' · '.pick($n,['address']))),'raw'=>$n,'action'=>'Call'];
}
foreach($media as $m){
  $allRows[]=['dept'=>'Scorsese','type'=>'Media','score'=>70,'title'=>pick($m,['title','name'],'Media Project'),'sub'=>trim((pick($m,['brand_pillar','brand']).' · '.pick($m,['source_url','media_url']))),'raw'=>$m,'action'=>'Review'];
}
foreach($blogs as $b){
  $allRows[]=['dept'=>'Shakespeare','type'=>'Blog / SEO','score'=>70,'title'=>pick($b,['title','post_title'],'Blog Post'),'sub'=>pick($b,['slug','url','excerpt']),'raw'=>$b,'action'=>'Publish'];
}
foreach($commands as $c){
  $allRows[]=['dept'=>pick($c,['department'],'Goliath'),'type'=>'Command','score'=>(int)($c['priority']??70),'title'=>pick($c,['title','command_type'],'Command'),'sub'=>trim((pick($c,['command_type']).' · '.pick($c,['status']).' · '.pick($c,['prompt']))),'raw'=>$c,'action'=>pick($c,['status'],'Review')];
}
usort($allRows,fn($a,$b)=>($b['score']<=>$a['score']));
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daily Hot Sheet</title>
<link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=354">
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<script src="/dashboard/assets/goliath-command-bus-v35.js?v=35" defer></script>
<style>
.briefing{background:linear-gradient(135deg,#111827,#07101d);border:1px solid #c8a96e55;border-radius:20px;box-shadow:0 18px 50px #0009;overflow:hidden;margin-bottom:14px}
.briefingHead{display:flex;justify-content:space-between;gap:14px;align-items:start;padding:16px;border-bottom:1px solid #263753}
.briefingHead h2{font:900 30px Georgia,serif;color:#c8a96e;margin:0}
.briefingHead p{margin:5px 0 0;color:#cbd5e1;line-height:1.45}
.briefingBody{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;padding:14px}
.priorityBox{background:#0f172a;border:1px solid #283653;border-radius:16px;padding:14px}
.priorityBox h3{margin:0 0 8px;color:#94a3b8;text-transform:uppercase;font-size:12px;letter-spacing:.07em}
.priorityName{font-size:22px;font-weight:950;color:#fff}
.priorityType{color:#f8d784;font-weight:950;margin-top:4px}
.teamGrid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px}
.teamCard{background:#08111f;border:1px solid #263753;border-radius:14px;padding:10px;text-align:center}
.teamCard strong{display:block;color:#fff;font-size:12px}
.teamCard span{display:block;color:#c8a96e;font-size:20px;font-weight:950}
.execRows{display:grid;gap:0}
.deptTag{border-radius:999px;padding:4px 7px;font-size:10px;font-weight:950;display:inline-flex;color:#fff;background:#334155}
.dept-Jessica{background:#b7791f}.dept-Scout{background:#166534}.dept-Scorsese{background:#6d28d9}.dept-Shakespeare{background:#1d4ed8}.dept-Einstein{background:#475569}.dept-Rockefeller{background:#991b1b}
@media(max-width:1000px){.briefingBody,.teamGrid{grid-template-columns:1fr 1fr}.briefingHead{display:block}}
@media(max-width:700px){.teamGrid{grid-template-columns:1fr}.priorityName{font-size:18px}}
</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div>
    <h1>Daily Hot Sheet</h1>
    <p>Rockefeller’s morning briefing. One place to see what happened, what matters, and what to do first.</p>
  </div>
  <div class="g-actions">
    <input class="g-search" id="hotSearch" placeholder="Search today’s activity..." oninput="gFilterRows('hotSearch','#hotRows tr')">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <a class="g-btn g-btn-dark" target="_blank" href="/lead-engine/run-goliath-refresh.php?key=<?=h($key)?>&mode=full">⚡ Run Goliath</a>
  </div>
</section>

<section class="briefing">
  <div class="briefingHead">
    <div>
      <h2>Good Morning, Mark</h2>
      <p><?=h($brief)?></p>
    </div>
    <button class="g-btn g-btn-gold" onclick="speakBriefing()">🔊 Read Briefing</button>
  </div>
  <div class="briefingBody">
    <div class="priorityBox">
      <h3>Highest ROI Action</h3>
      <div class="priorityName"><?=h($topName)?></div>
      <div class="priorityType"><?=h($topType)?></div>
      <p class="g-subtle"><?=h($topDetail)?></p>
      <div class="g-actions">
        <button class="g-btn g-btn-blue" onclick="gToast('Rockefeller recommendation','Start here first. The team can prepare supporting assets.')">Begin</button>
        <button class="g-btn g-btn-purple" onclick="queueBriefingMission()">Assign Team</button>
      </div>
    </div>
    <div class="teamGrid">
      <div class="teamCard"><span><?=count($leads)?></span><strong>Jessica</strong><small>leads</small></div>
      <div class="teamCard"><span><?=count($numbers)?></span><strong>Scout</strong><small>numbers</small></div>
      <div class="teamCard"><span><?=count($expired)?></span><strong>Einstein</strong><small>alerts</small></div>
      <div class="teamCard"><span><?=count($media)?></span><strong>Scorsese</strong><small>media</small></div>
      <div class="teamCard"><span><?=count($posts)+count($blogs)?></span><strong>Shakespeare</strong><small>publishing</small></div>
      <div class="teamCard"><span><?=moneyfmt($roi)?></span><strong>Rockefeller</strong><small>ROI</small></div>
    </div>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-blue"><div class="n"><?=count($leads)?></div><strong>Website Leads</strong><small>Jessica drip</small></a>
  <a class="g-kpi g-red"><div class="n"><?=count($expired)?></div><strong>Seller Alerts</strong><small>Rockefeller priority</small></a>
  <a class="g-kpi g-green"><div class="n"><?=count($numbers)?></div><strong>Numbers Found</strong><small>Scout verified</small></a>
  <a class="g-kpi g-purple"><div class="n"><?=count($media)?></div><strong>Media</strong><small>Scorsese review</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=count($posts)+count($blogs)?></div><strong>Publishing</strong><small>Shakespeare queue</small></a>
  <a class="g-kpi g-orange"><div class="n"><?=count($commands)?></div><strong>Commands</strong><small>Goliath missions</small></a>
</section>

<section class="g-panel">
<h2>Today’s Executive Queue <span>click any row for intelligence</span></h2>
<div class="g-tableWrap">
<table class="g-stealthTable">
<thead><tr><th></th><th>Department</th><th>Item</th><th>Type</th><th>Score</th><th style="text-align:right">Action</th></tr></thead>
<tbody id="hotRows">
<?php foreach($allRows as $r):
  $dot=$r['score']>=85?'g-s-green':($r['score']>=65?'g-s-yellow':'g-s-red');
  $intel=['name'=>$r['title'],'status'=>$r['type'].' · '.$r['dept'],'notes'=>$r['sub'],'recommended_action'=>'Review this item and assign the next highest-value team action.','content_angle'=>'Turn this into personalized follow-up, public content, and a reusable asset when relevant.'];
  $search=strtolower($r['dept'].' '.$r['type'].' '.$r['title'].' '.$r['sub'].' '.$r['action']);
?>
<tr data-search="<?=h($search)?>" onclick='openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>
  <td><span class="g-statusDot <?=$dot?>"></span></td>
  <td><span class="deptTag dept-<?=h($r['dept'])?>"><?=h($r['dept'])?></span></td>
  <td><div class="g-name"><?=h($r['title'])?></div><div class="g-subtle"><?=h(substr($r['sub'],0,150))?></div></td>
  <td><?=h($r['type'])?></td>
  <td><div class="g-price"><?=h($r['score'])?></div></td>
  <td><div class="g-pillRow"><button class="g-pill g-pill-purple" onclick='event.stopPropagation();openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>🧠 Intel</button><button class="g-pill g-pill-gold" onclick="event.stopPropagation();queueRowMission(<?=h(json_encode($r['title']))?>,<?=h(json_encode($r['type']))?>)">⭐ <?=h($r['action'])?></button></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</div>

<script>
const BRIEFING=<?=json_encode($brief)?>;
const KEY=<?=json_encode($key)?>;
function speakBriefing(){
  if(!('speechSynthesis' in window)){gToast('Voice unavailable','This browser does not support speech playback.');return;}
  const u=new SpeechSynthesisUtterance(BRIEFING);
  u.rate=.92; u.pitch=.92; u.volume=1;
  speechSynthesis.cancel(); speechSynthesis.speak(u);
}
async function queueBriefingMission(){
  await goliathCommand({key:KEY,command_type:'executive_briefing_action',department:'Rockefeller',title:'Rockefeller assigned today’s highest ROI action',prompt:BRIEFING,priority:125,roi_estimate:<?=json_encode((float)$roi)?>});
}
async function queueRowMission(title,type){
  await goliathCommand({key:KEY,command_type:'daily_hot_sheet_action',department:'Rockefeller',title:'Daily Hot Sheet action: '+title,prompt:'Take action on '+type+': '+title+'. Assign Jessica, Einstein, Scout, Scorsese, and Shakespeare as needed.',priority:110,roi_estimate:2500});
}
</script>
</body>
</html>