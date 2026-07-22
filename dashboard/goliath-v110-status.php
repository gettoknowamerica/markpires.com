<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-v110-status.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
$execs=rows("SELECT * FROM goliath_executive_activity_v110 ORDER BY FIELD(executive_key,'goliath','scout','jessica','shakespeare','scorsese','einstein','columbo','prospector','rockefeller','pandora','mozart','sherlock')");
$handoffs=rows("SELECT * FROM goliath_required_handoffs_v110 WHERE status='required' ORDER BY priority DESC,id DESC LIMIT 40");
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath V110</title><link rel="stylesheet" href="/dashboard/assets/goliath-carbon-v105.css"></head><body><div class="wrap">
<header class="top"><div><div class="logo">⚡ GOLIATH V110 — COHESIVE EXECUTIVE OS</div><div class="muted">One organization · Twelve executives · Required handoffs · Finished outcomes</div></div><div><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn" href="/dashboard/executive-council-v107.php">Council</a><a class="btn" href="/dashboard/goliath-projects-v106.php">Projects</a></div></header>
<section class="two"><main class="panel"><h2>12 Executive Departments</h2><div class="list"><?php foreach($execs as $e): ?><div class="item"><b><?=h($e['display_name'])?></b><span class="score"> <?=h(strtoupper($e['current_mode']))?></span><div class="muted"><?=h($e['department'])?></div><div><?=h($e['current_action'])?></div><div class="muted"><?=h($e['last_heartbeat_at'])?></div></div><?php endforeach; ?></div></main>
<aside class="panel"><h2>Required Mission Handoffs</h2><div class="list"><?php foreach($handoffs as $x): ?><div class="item"><b><?=h($x['from_executive'])?> → <?=h($x['to_executive'])?></b><div class="score"><?=h($x['title'])?></div><div class="muted"><?=h($x['instructions'])?></div></div><?php endforeach; ?></div></aside></section>
</div></body></html>