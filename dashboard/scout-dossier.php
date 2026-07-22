<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scout-dossier.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
$id=(int)($_GET['id']??0);
$d=one("SELECT * FROM scout_intel_dossiers WHERE id=?",[$id]);
if(!$d){echo 'Dossier not found';exit;}
$events=rows("SELECT * FROM scout_intel_events WHERE dossier_id=? ORDER BY created_at DESC",[$id]);
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scout Dossier</title><link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><style>
body{background:#020617;color:#fff}.panel{background:#07111f;border:1px solid #22c55e33;border-radius:22px;padding:16px;margin:14px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.box{background:#050914;border:1px solid #ffffff16;border-radius:14px;padding:12px}.box small{color:#94a3b8}.box b{display:block;color:#fff}.btn{display:inline-flex;border-radius:12px;padding:10px 13px;background:#d4af37;color:#111;text-decoration:none;font-weight:1000}.muted{color:#94a3b8}pre{white-space:pre-wrap;background:#020617;border:1px solid #ffffff16;border-radius:14px;padding:12px;color:#cbd5e1}
</style></head><body><div class="shell"><?php if(file_exists(__DIR__.'/includes/goliath-sidebar-v33.php')) require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<a class="btn" href="/dashboard/scout-intelligence-center.php">← Scout Center</a>
<div class="panel"><h1>🕵️ Scout Dossier: <?=h($d['owner_name']?:'Unknown Owner')?></h1><p class="muted"><?=h($d['property_address'])?> <?=h($d['town']?'· '.$d['town']:'')?></p></div>
<div class="grid">
 <div class="box"><small>Status</small><b><?=h($d['handoff_status'])?></b></div>
 <div class="box"><small>Confidence</small><b><?=h($d['confidence_score'])?>%</b></div>
 <div class="box"><small>Phone</small><b><?=h($d['phone']?:'Not found')?></b></div>
 <div class="box"><small>Email</small><b><?=h($d['email']?:'Not found')?></b></div>
 <div class="box"><small>Recommended Blog</small><b><?=h($d['recommended_blog'])?></b></div>
 <div class="box"><small>Next Action</small><b><?=h($d['next_action'])?></b></div>
</div>
<div class="panel"><h2>Call Strategy</h2><pre><?=h($d['call_strategy'])?></pre></div>
<div class="panel"><h2>Email Strategy</h2><pre><?=h($d['email_strategy'])?></pre></div>
<div class="panel"><h2>Listing History</h2><pre><?=h($d['listing_history']?:'Not available yet.')?></pre></div>
<div class="panel"><h2>Nearby Sales / Market Context</h2><pre><?=h($d['nearby_sales']?:'Not available yet.')?></pre></div>
<div class="panel"><h2>Evidence Log</h2><pre><?=h($d['evidence_log'])?></pre></div>
<div class="panel"><h2>Activity</h2><?php foreach($events as $e): ?><div class="box"><b><?=h($e['title'])?></b><br><span class="muted"><?=h($e['created_at'])?> · <?=h($e['event_type'])?></span><p><?=h($e['details'])?></p></div><?php endforeach; ?></div>
</main></div></body></html>