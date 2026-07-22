<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php'; require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-projects-v106.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$projects=rows("SELECT * FROM goliath_projects ORDER BY priority DESC,health_score DESC,updated_at DESC LIMIT 60");
$uid=$_GET['project']??($projects[0]['project_uid']??'');
$project=$uid?one("SELECT * FROM goliath_projects WHERE project_uid=?",[$uid]):null;
$deps=$uid?rows("SELECT * FROM goliath_project_departments WHERE project_uid=? ORDER BY FIELD(department_key,'research','verification','authority','media','relationship','revenue','operations'),id",[$uid]):[];
$dels=$uid?rows("SELECT * FROM goliath_project_deliverables WHERE project_uid=? ORDER BY status,title",[$uid]):[];
$timeline=$uid?rows("SELECT * FROM goliath_project_timeline WHERE project_uid=? ORDER BY id DESC LIMIT 30",[$uid]):[];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Projects V106</title><link rel="stylesheet" href="/dashboard/assets/goliath-carbon-v105.css"></head><body><div class="wrap">
<header class="top"><div><div class="logo">🧬 GOLIATH PROJECT GENOME</div><div class="muted">Projects are living containers: research → authority → media → promotion → sales → learning</div></div><div><a class="btn" href="/dashboard/mission-control-intake-v105.php">Intake</a> <a class="btn" href="/dashboard/goliath-organization-runtime.php">Runtime</a> <a class="btn gold" target="_blank" href="/lead-engine/project-genome-engine-v106.php?key=<?=h($key)?>">Run Project Genome</a></div></header>
<section class="grid"><aside class="panel"><h2>Projects</h2><div class="list"><?php foreach($projects as $p): ?><div class="item"><a class="btn" href="?project=<?=h($p['project_uid'])?>"><?=h($p['title'])?></a><div class="muted"><?=h($p['business_unit'])?> · <?=h($p['current_phase'])?> · Priority <?=h($p['priority'])?></div><div class="score">Health <?=h($p['health_score'])?> · Revenue $<?=h(number_format((float)$p['revenue_potential']))?></div></div><?php endforeach; ?></div></aside>
<main class="panel"><h2><?=h($project['title']??'No Project Yet')?></h2><?php if($project): ?><div class="big"><?=h($project['health_score'])?></div><div class="muted"><?=h($project['why_text'])?></div><hr><h2>Department Timeline</h2><?php foreach($deps as $d): ?><div class="item"><b><?=h(ucfirst($d['department_key']))?> — <?=h(ucfirst($d['executive_key']))?></b><div class="muted"><?=h($d['status'])?> · Score <?=h($d['score'])?></div><div class="muted"><?=h($d['summary'])?></div></div><?php endforeach; ?><?php endif; ?></main>
<aside class="panel"><h2>Deliverables</h2><?php foreach($dels as $d): ?><div class="item"><b><?=h($d['title'])?></b><div class="muted"><?=h($d['executive_key'])?> · <?=h($d['status'])?> · Score <?=h($d['score'])?></div></div><?php endforeach; ?><h2>Timeline</h2><?php foreach($timeline as $t): ?><div class="item"><b><?=h($t['title'])?></b><div class="muted"><?=h($t['executive_key'])?> · <?=h($t['created_at'])?></div></div><?php endforeach; ?></aside></section>
</div></body></html>