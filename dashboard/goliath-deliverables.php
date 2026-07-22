<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/../lead-engine/goliath-db.php';
require_once __DIR__ . '/../lead-engine/goliath-v76-operating-system.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-deliverables.php'));exit;}
gv76_install();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}

$exec=strtolower($_GET['exec']??'');
$status=$_GET['status']??'';
$id=(int)($_GET['deliverable_id']??0);
$where=[];$p=[];
if($exec){$where[]='executive_key=?';$p[]=$exec;}
if($status){$where[]='evidence_status=?';$p[]=$status;}
if($id){$where[]='id=?';$p[]=$id;}
$w=$where?'WHERE '.implode(' AND ',$where):'';
$items=rows("SELECT * FROM goliath_deliverables $w ORDER BY created_at DESC, id DESC LIMIT 250",$p);
$counts=gv76_counts();
$selected=$id&&count($items)?$items[0]:null;
function json_arr($v){$j=json_decode((string)$v,true);return is_array($j)?$j:[];}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Deliverables</title><link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456"><style>
body{background:#030712;color:#fff}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.stat{background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px}.stat b{font-size:30px;color:#f5d48b}.filters{display:flex;flex-wrap:wrap;gap:8px}.chip{color:#fff;text-decoration:none;background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:8px 11px;font-weight:900}.chip:hover,.chip.active{border-color:#d4af37}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px}.card{background:#07111f;border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:14px}.card h3{margin:0;color:#f5d48b}.meta{color:#94a3b8;font-size:12px}.badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:1000}.ok{background:#14532d;color:#dcfce7}.warn{background:#451a03;color:#fed7aa}.bad{background:#7f1d1d;color:#fee2e2}.links{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.outLink{background:#14532d;color:#dcfce7;border:1px solid #22c55e66;border-radius:999px;padding:7px 9px;text-decoration:none;font-weight:900;font-size:12px}.summary{color:#dbe3f2;margin-top:8px;line-height:1.45}</style></head><body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="top"><div><h1>Deliverable Registry</h1><p>V76 single source of truth: every executive output, evidence status, source link, handoff, and next action.</p></div><div class="brandbar"><a class="btn dark" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn" href="/dashboard/goliath-worker-output.php">Completed Work</a></div></section>
<section class="panel"><div class="inner"><div class="stats"><div class="stat"><strong>Total Deliverables</strong><br><b><?=h($counts['deliverables']??0)?></b></div><div class="stat"><strong>Verified</strong><br><b><?=h($counts['verified']??0)?></b></div><div class="stat"><strong>Needs Evidence</strong><br><b><?=h($counts['needs_evidence']??0)?></b></div><div class="stat"><strong>Queued Handoffs</strong><br><b><?=h($counts['handoffs_queued']??0)?></b></div></div></div></section>
<section class="panel"><h2>Filters</h2><div class="inner"><div class="filters"><a class="chip <?=!$exec&&!$status?'active':''?>" href="/dashboard/goliath-deliverables.php">All</a><a class="chip <?=$status==='verified'?'active':''?>" href="/dashboard/goliath-deliverables.php?status=verified">Verified</a><a class="chip <?=$status==='needs_evidence'?'active':''?>" href="/dashboard/goliath-deliverables.php?status=needs_evidence">Needs Evidence</a><a class="chip <?=$status==='missing_clickable_output'?'active':''?>" href="/dashboard/goliath-deliverables.php?status=missing_clickable_output">Missing Clickable Output</a><?php foreach(['scout','jessica','scorsese','einstein','shakespeare','prospector','pandora','rockefeller','columbo','mozart','goliath'] as $a): ?><a class="chip <?=$exec===$a?'active':''?>" href="/dashboard/goliath-deliverables.php?exec=<?=h($a)?>"><?=h(ucfirst($a))?></a><?php endforeach;?></div></div></section>
<section class="panel"><h2>Registry <span><?=count($items)?> shown</span></h2><div class="inner"><div class="grid"><?php foreach($items as $d): $urls=json_arr($d['source_urls']??'[]'); $badge=$d['evidence_status']==='verified'?'ok':($d['evidence_status']==='needs_evidence'?'bad':'warn'); ?>
<div class="card"><h3><?=h(ucfirst($d['executive_key']))?> — <?=h($d['title'])?></h3><div class="meta">#<?=h($d['id'])?> · <?=h($d['deliverable_type'])?> · <?=h($d['created_at'])?></div><p><span class="badge <?=$badge?>"><?=h($d['evidence_status'])?></span> <span class="badge warn">ROI <?=h($d['roi_score'])?></span></p><div class="summary"><?=h(mb_substr(strip_tags($d['output_summary']??''),0,260))?></div><div class="links"><a class="outLink" href="/dashboard/goliath-deliverables.php?deliverable_id=<?=h($d['id'])?>">Open</a><?php if(!empty($d['output_url'])):?><a class="outLink" target="_blank" href="<?=h($d['output_url'])?>">Output</a><?php endif; ?><?php foreach(array_slice($urls,0,3) as $u): ?><a class="outLink" target="_blank" href="<?=h($u)?>">Source</a><?php endforeach; ?></div><p class="meta"><b>Next:</b> <?=h($d['next_action']??'')?><?php if(!empty($d['next_executive'])): ?><br><b>Handoff:</b> <?=h($d['next_executive'])?><?php endif;?></p></div>
<?php endforeach; ?><?php if(!count($items)):?><p>No deliverables found yet. Run the local worker after installing V76.</p><?php endif;?></div></div></section>
</main></div></body></html>