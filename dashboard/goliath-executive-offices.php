<?php
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
function go_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function go_row($exec){
  $key=strtolower($exec);
  $hb=go_table('executive_heartbeats') ? (gdb_one('SELECT * FROM executive_heartbeats WHERE executive_key=? LIMIT 1',[$key])?:[]) : [];
  $counts=['queued'=>0,'working'=>0,'review'=>0,'complete'=>0];
  if(go_table('executive_commissions')){
    $rows=gdb_all("SELECT status, COUNT(*) c FROM executive_commissions WHERE executive_key=? GROUP BY status",[$key]);
    foreach($rows as $r){$counts[$r['status']] = (int)$r['c'];}
  }
  return [$hb,$counts];
}
$execs=[
 ['Shakespeare','Chief Storyteller & Keeper of the Constitution','literary'],['Pandora','Chief Possibility & Creative Expansion Officer','pandora'],['Mozart','Chief Music & Audio Officer','mozart'],['Scorsese','Chief Media Director','scorsese'],['Einstein','Chief Intelligence Officer','einstein'],['Columbo','Chief Research & Archive Intelligence Officer','columbo'],['Jessica','Chief Relationship & Human Touch Officer','jessica'],['Scout','Chief Intelligence & Lead Discovery Officer','scout'],['Prospector','Chief Opportunity & Partnerships Officer','prospector'],['Rockefeller','Chief Revenue & Priority Officer','rockefeller']
];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Executive Offices</title><link rel="stylesheet" href="/dashboard/assets/goliath-offices-v72.css?v=721"></head>
<body class="gox-body"><aside class="gox-side"><div class="brand logoBrand"><a href="/dashboard/goliath-mission-control.php"><img src="/dashboard/assets/goliath-ai-full-logo.png?v=33" alt="Goliath Omni"></a></div><a href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="active" href="/dashboard/goliath-executive-offices.php">Executive Offices</a><a href="/dashboard/goliath-constitution.php">Constitution</a><a href="/dashboard/goliath-worker-output.php">Worker Output</a><a href="/dashboard/scorsese-media-center.php">Scorsese Media Center</a><div class="always">Goliath is always working<br><b>24/7 • 365 • ∞</b></div></aside>
<main class="gox-main"><header><h1>Executive Offices</h1><p>Your Team. Your Empire. Your Results.</p><div class="status">● System Status<br><span>All Systems Operational</span></div></header>
<section class="office-grid">
<?php foreach($execs as $e): [$name,$title,$theme]=$e; [$hb,$c]=go_row($name); $task=$hb['current_task']??($hb['phase']??'Monitoring executive work'); $progress=(int)($hb['progress']??0); ?>
  <article class="office-card <?=htmlspecialchars($theme)?>">
    <div class="shade"></div><div class="card-content"><h2><?=htmlspecialchars($name)?></h2><h3><?=htmlspecialchars($title)?></h3>
    <div class="today"><b>Today's Work</b><ul><li><?=htmlspecialchars(mb_substr($task,0,80))?></li><li>In Progress: <?=($c['working']??0)+($c['review']??0)?></li><li>Ready for Review: <?=htmlspecialchars($hb['ready_for_review']??0)?></li><li>Progress: <?=$progress?>%</li></ul></div>
    <a class="enter" href="/dashboard/executive-office.php?exec=<?=urlencode(strtolower($name))?>">Enter Office</a></div>
  </article>
<?php endforeach; ?>
</section><footer class="office-footer"><div>Team Completed Today <b><?=array_sum(array_map(fn($e)=> (int)((go_row($e[0])[1]['complete']??0)), $execs))?></b></div><div>Ready For Review <b><?php $sum=0; foreach($execs as $e){$sum+=(int)(go_row($e[0])[0]['ready_for_review']??0);} echo $sum; ?></b></div><a href="/dashboard/goliath-worker-output.php">View All Activity</a></footer></main></body></html>
