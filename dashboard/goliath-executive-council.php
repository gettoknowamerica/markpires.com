<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
require_once __DIR__.'/../lead-engine/goliath-v75-mission-engine.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-executive-council.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
gv75_install_schema(); gv75_seed_missions();
if(isset($_GET['award'])) gv75_award_daily();
$missions=gv75_all("SELECT * FROM executive_missions ORDER BY priority DESC");
$award=gv75_one("SELECT * FROM executive_awards WHERE trophy_active=1 AND award_type='daily_mvp' ORDER BY award_date DESC LIMIT 1");
$awards=gv75_all("SELECT * FROM executive_awards ORDER BY award_date DESC, created_at DESC LIMIT 30");
$today=[]; foreach(['scout','jessica','scorsese','shakespeare','einstein','rockefeller','prospector','columbo','mozart','pandora'] as $e){
  $today[$e]=[
    'complete'=>(int)((gv75_one("SELECT COUNT(*) c FROM goliath_worker_completions WHERE LOWER(executive)=? AND DATE(created_at)=CURRENT_DATE",[$e])?:['c'=>0])['c']),
    'review'=>(int)((gv75_one("SELECT COUNT(*) c FROM goliath_review_queue WHERE LOWER(executive)=? AND DATE(created_at)=CURRENT_DATE",[$e])?:['c'=>0])['c']),
    'active'=>(int)((gv75_one("SELECT COUNT(*) c FROM executive_commissions WHERE executive_key=? AND status IN ('queued','working','review','ready_for_review') AND COALESCE(progress,0)<100",[$e])?:['c'=>0])['c']),
  ];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Executive Council</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456"><link rel="stylesheet" href="/dashboard/assets/goliath-v75-trophy.css?v=75">
</head><body><div class="shell"><?php require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="top"><div><h1>Executive Council</h1><p>V75 autonomous operating system: every executive has a permanent mission, creates proactive work, and competes for value created — not busywork.</p></div><div class="brandbar"><a class="btn" target="_blank" href="/lead-engine/run-goliath-autonomous-dispatcher.php?key=<?=h($key)?>&limit=10">Run Dispatcher</a><a class="btn dark" target="_blank" href="/lead-engine/goliath-autonomous-health.php?key=<?=h($key)?>">Health</a><a class="btn purple" href="?award=1">Award Trophy</a></div></section>
<?php if($award): ?><section class="v75TrophyHero"><div class="cup">🏆</div><div><p>Current Trophy Holder</p><h2><?=h(ucfirst($award['executive_key']))?> — <?=h($award['title'])?></h2><p><?=h($award['reason'])?></p></div><div class="score"><b><?=h($award['impact_score'])?></b><span>Impact Score</span></div></section><?php endif; ?>
<section class="panel"><h2>Today’s Executive Floor <span>never idle</span></h2><div class="inner"><div class="v75CouncilGrid"><?php foreach($today as $exec=>$s): ?><a class="v75ExecTile <?=($award&&$award['executive_key']===$exec)?'winner':''?>" href="/dashboard/executive-office.php?exec=<?=h($exec)?>"><span class="miniCup"><?=($award&&$award['executive_key']===$exec)?'🏆':'⚡'?></span><strong><?=h(ucfirst($exec))?></strong><p><?=h($s['complete'])?> done · <?=h($s['review'])?> review · <?=h($s['active'])?> active</p><small><?=($s['active']>0?'Working':'Ready for autonomous mission')?></small></a><?php endforeach;?></div></div></section>
<section class="panel"><h2>Permanent Missions <span>the no-idle doctrine</span></h2><div class="inner"><div class="v75MissionList"><?php foreach($missions as $m): ?><div class="v75Mission"><b><?=h(ucfirst($m['executive_key']))?> — <?=h($m['title'])?></b><p><?=h(mb_substr($m['mission_statement'],0,280))?></p><small>Priority <?=h($m['priority'])?> · Cadence <?=h($m['cadence_minutes'])?> min · Max/day <?=h($m['max_daily_commissions'])?> · Last dispatch <?=h($m['last_dispatched_at']?:'never')?></small></div><?php endforeach;?></div></div></section>
<section class="panel"><h2>Hall of Achievement <span>recent awards</span></h2><div class="inner"><?php foreach($awards as $a): ?><div class="v75Award"><span>🏆</span><div><b><?=h($a['award_date'])?> — <?=h(ucfirst($a['executive_key']))?></b><p><?=h($a['reason'])?></p></div><strong><?=h($a['impact_score'])?></strong></div><?php endforeach;?><?php if(!count($awards)):?><p>No awards yet. Run the award button after work has been completed today.</p><?php endif;?></div></section>
</main></div></body></html>
