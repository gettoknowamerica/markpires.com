<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-live-executives.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function icon($e){$e=strtolower($e);return ['scout'=>'🕵️','jessica'=>'✉️','scorsese'=>'🎬','shakespeare'=>'✒️','einstein'=>'📊','goliath'=>'🏛️','columbo'=>'🕵️‍♂️','sherlock'=>'🔎','mozart'=>'🎼','prospector'=>'⛏️','rockefeller'=>'💰','pandora'=>'🌍'][$e]??'⚡';}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$hearts=rows("SELECT * FROM goliath_executive_heartbeat ORDER BY FIELD(status,'working','accepted','queued','idle'), updated_at DESC");
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="20"><title>Live Executives</title>
<style>
body{margin:0;background:#030712;color:#fff;font-family:Arial}.wrap{max-width:1320px;margin:auto;padding:24px}.hero,.card{background:#07111f;border:1px solid #ffffff22;border-radius:22px;padding:18px;margin-bottom:14px;box-shadow:0 18px 45px #0007}.hero h1{margin:0;color:#d4af37}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}.card h2{margin:0 0 8px}.muted{color:#94a3b8}.bar{height:12px;background:#020617;border-radius:99px;overflow:hidden;border:1px solid #ffffff18}.bar i{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#d4af37)}.pill{display:inline-block;border:1px solid #ffffff22;border-radius:999px;padding:4px 8px;margin:3px;color:#cbd5e1;font-size:12px}.live{background:#16a34a;color:#fff}.idle{background:#334155}.btn{display:inline-block;background:#d4af37;color:#111;padding:9px 12px;border-radius:12px;text-decoration:none;font-weight:900}
</style></head><body><div class="wrap"><section class="hero"><h1>Live Executives</h1><p class="muted">Heartbeat board for the V95 Executive Engine.</p><a class="btn" target="_blank" href="/lead-engine/executive-engine/executive-dispatcher.php?key=<?=h($key)?>&limit=200">Run Dispatcher</a> <a class="btn" href="/dashboard/goliath-executive-inbox.php">Executive Inbox</a></section><section class="grid">
<?php foreach($hearts as $h): $p=max(0,min(100,(int)$h['progress'])); ?>
<div class="card"><h2><?=icon($h['executive_key'])?> <?=h(strtoupper($h['executive_key']))?></h2><span class="pill <?=($h['status']==='working'?'live':'idle')?>"><?=h($h['status'])?></span><span class="pill"><?=h($h['browser_status'])?></span><p><?=h($h['current_step']?:'Standing by')?></p><div class="bar"><i style="width:<?=$p?>%"></i></div><p class="muted">Progress <?=$p?>% · Pages <?=h($h['pages_read'])?> · Evidence <?=h($h['evidence_count'])?> · Phones <?=h($h['phones_found'])?> · Emails <?=h($h['emails_found'])?> · Confidence <?=h($h['confidence_score'])?>%</p><p class="muted"><?=h($h['updated_at'])?></p></div>
<?php endforeach; ?>
</section></div></body></html>