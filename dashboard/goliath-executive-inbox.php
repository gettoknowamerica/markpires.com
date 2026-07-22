<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-executive-inbox.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function ex_icon($e){$e=strtolower($e);return ['scout'=>'🕵️','jessica'=>'✉️','scorsese'=>'🎬','shakespeare'=>'✒️','einstein'=>'📊','goliath'=>'🏛️','columbo'=>'🕵️‍♂️','sherlock'=>'🔎','mozart'=>'🎼'][$e]??'⚡';}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$filter=$_GET['exec']??'';
$where="archived=0"; $params=[];
if($filter){$where.=" AND executive_key=?"; $params[]=$filter;}
$items=rows("SELECT * FROM executive_deliverables WHERE {$where} ORDER BY viewed ASC, created_at DESC, id DESC LIMIT 200",$params);
$counts=[
 'new'=>(int)(one("SELECT COUNT(*) c FROM executive_deliverables WHERE archived=0 AND viewed=0")['c']??0),
 'all'=>(int)(one("SELECT COUNT(*) c FROM executive_deliverables WHERE archived=0")['c']??0),
 'scout'=>(int)(one("SELECT COUNT(*) c FROM executive_deliverables WHERE archived=0 AND executive_key='scout'")['c']??0),
 'content'=>(int)(one("SELECT COUNT(*) c FROM executive_deliverables WHERE archived=0 AND executive_key IN ('shakespeare','einstein','scorsese')")['c']??0)
];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Executive Inbox</title>
<style>
body{margin:0;background:radial-gradient(circle at top,#101827,#030712 58%,#01030a);color:#fff;font-family:Arial,sans-serif}.wrap{max-width:1320px;margin:auto;padding:24px}.hero,.panel,.item{background:#07111f;border:1px solid #ffffff20;border-radius:22px;padding:18px;margin-bottom:14px;box-shadow:0 18px 45px #0007}.hero h1{margin:0;color:#d4af37}.muted{color:#94a3b8}.btn{display:inline-block;background:#111827;color:#fff;border:1px solid #ffffff24;padding:9px 12px;border-radius:12px;text-decoration:none;font-weight:900;margin:4px}.btn.gold{background:linear-gradient(135deg,#f5d48b,#d4af37);color:#111;border:0}.btn.green{background:linear-gradient(135deg,#16a34a,#064e3b)}.btn.red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.stat{background:#050914;border:1px solid #ffffff18;border-radius:16px;padding:14px}.stat b{font-size:32px}.item{display:grid;grid-template-columns:90px minmax(0,1fr) 260px;gap:14px;align-items:center}.badge{display:inline-block;border-radius:999px;background:#dc2626;color:#fff;font-size:11px;font-weight:1000;padding:5px 8px}.exec{font-size:34px}.title{font-size:20px;font-weight:1000}.preview{color:#cbd5e1;margin-top:5px}.pill{display:inline-block;border:1px solid #ffffff22;border-radius:999px;padding:4px 8px;color:#cbd5e1;font-size:11px;margin:2px}.actions{text-align:right}@media(max-width:850px){.item{grid-template-columns:1fr}.actions{text-align:left}}
</style></head><body><div class="wrap">
<section class="hero"><h1>Goliath Executive Inbox</h1><p class="muted">Newest completed work from every executive. NEW badges clear when you view an item.</p><a class="btn gold" target="_blank" href="/lead-engine/executive-engine/executive-dispatcher.php?key=<?=h($key)?>&limit=200">Run V95 Dispatcher</a><a class="btn" href="/dashboard/goliath-live-executives.php">Live Executives</a><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a></section>
<section class="stats"><div class="stat"><b><?=h($counts['new'])?></b><div class="muted">New</div></div><div class="stat"><b><?=h($counts['all'])?></b><div class="muted">Open</div></div><div class="stat"><b><?=h($counts['scout'])?></b><div class="muted">Scout</div></div><div class="stat"><b><?=h($counts['content'])?></b><div class="muted">Content Team</div></div></section>
<section class="panel">
<?php if(!count($items)): ?><p class="muted">No executive deliverables yet. Run the V95 dispatcher.</p><?php endif; ?>
<?php foreach($items as $it): $url=$it['action_url']?:'/dashboard/goliath-deliverables.php'; ?>
<div class="item">
  <div><div class="exec"><?=ex_icon($it['executive_key'])?></div><?php if(!(int)$it['viewed']): ?><span class="badge">NEW</span><?php endif; ?></div>
  <div><div class="title"><?=h($it['title'])?></div><div class="preview"><?=h($it['preview'])?></div><div><span class="pill"><?=h(strtoupper($it['executive_key']))?></span><span class="pill"><?=h($it['deliverable_type'])?></span><span class="pill"><?=h($it['created_at'])?></span><?php if($it['lead_status']):?><span class="pill"><?=h($it['lead_status'])?></span><?php endif;?></div></div>
  <div class="actions"><a class="btn gold" target="_blank" href="<?=h($url)?>" onclick="fetch('/lead-engine/executive-engine/executive-deliverable.php?key=<?=h($key)?>&action=viewed&id=<?=h($it['id'])?>')">View</a><a class="btn green" href="/lead-engine/executive-engine/executive-deliverable.php?key=<?=h($key)?>&action=viewed&id=<?=h($it['id'])?>">Mark Viewed</a><a class="btn red" href="/lead-engine/executive-engine/executive-deliverable.php?key=<?=h($key)?>&action=archive&id=<?=h($it['id'])?>">Archive</a></div>
</div>
<?php endforeach; ?>
</section></div></body></html>