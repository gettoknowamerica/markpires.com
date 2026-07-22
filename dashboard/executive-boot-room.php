<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/executive-boot-room.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
$exec=$_GET['exec']??'scout';
$boot=one("SELECT * FROM executive_boot_logs WHERE executive_key=? ORDER BY id DESC LIMIT 1",[$exec]);
$timeline=rows("SELECT * FROM executive_mission_timeline WHERE executive_key=? ORDER BY id DESC LIMIT 80",[$exec]);
$ctx=$boot && !empty($boot['boot_context']) ? json_decode($boot['boot_context'],true) : [];
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>Executive Boot Room</title>
<style>
body{margin:0;background:radial-gradient(circle at top left,rgba(212,175,55,.13),transparent 30%),#030712;color:#fff;font-family:Arial,sans-serif}.wrap{max-width:1200px;margin:auto;padding:18px}.hero,.panel{background:#07111f;border:1px solid #ffffff22;border-radius:22px;padding:16px;margin-bottom:14px;box-shadow:0 18px 45px #0007}.hero h1{margin:0;color:#d4af37}.muted{color:#94a3b8}.btn{display:inline-block;background:#d4af37;color:#111;padding:9px 12px;border-radius:12px;text-decoration:none;font-weight:900;margin:4px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.pre{white-space:pre-wrap;background:#020617;border:1px solid #ffffff16;border-radius:14px;padding:12px;max-height:420px;overflow:auto;color:#cbd5e1}.event{background:#020617;border:1px solid #ffffff14;border-radius:14px;padding:10px;margin:8px 0}.event b{color:#f5d48b}@media(max-width:800px){.wrap{padding:10px}.grid{grid-template-columns:1fr}.hero h1{font-size:24px}}
</style></head><body><div class="wrap">
<section class="hero"><h1>Executive Boot Room</h1><p class="muted">Confirms each executive loads the Constitution, identity file, capabilities, and mission context before taking work.</p>
<a class="btn" target="_blank" href="/lead-engine/executive-boot-v96.php?key=<?=h($key)?>&exec=scout">Boot Scout</a>
<a class="btn" target="_blank" href="/lead-engine/scout-revenue-kernel-v96.php?key=<?=h($key)?>&limit=25">Run Scout Revenue Kernel</a>
<a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a></section>
<section class="grid">
<div class="panel"><h2>Latest Boot: <?=h(ucfirst($exec))?></h2><p class="muted">Boot hash: <?=h($boot['boot_hash']??'none yet')?></p><p class="muted">Identity file: <?=h($boot['identity_file']??'none')?></p><div class="pre"><?=h(($ctx['identity_brief']??'Run Boot Scout first.'))?></div></div>
<div class="panel"><h2>Constitution Loaded</h2><div class="pre"><?=h(($ctx['constitution_brief']??'No boot context yet.'))?></div></div>
</section>
<section class="panel"><h2>Mission Timeline</h2><?php foreach($timeline as $e): ?><div class="event"><b><?=h($e['title'])?></b><div class="muted"><?=h($e['event_type'])?> · <?=h($e['created_at'])?></div><p><?=h($e['details'])?></p></div><?php endforeach; ?></section>
</div></body></html>