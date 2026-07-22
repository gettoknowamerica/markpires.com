<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-kernel.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
function sbq($ep){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);$b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return ($http>=200&&$http<300&&is_array($d))?$d:[];}
$missions=sbq('goliath_missions?select=*&order=created_at.desc&limit=40');
$jobs=sbq('agent_jobs?select=*&order=created_at.desc&limit=40');
$leads=sbq('lead_brain?select=*&order=created_at.desc&limit=20');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Kernel</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
<style>
.kernelWrap{padding:22px}.kernelHero{border:1px solid rgba(245,200,93,.35);background:linear-gradient(135deg,rgba(0,0,0,.88),rgba(45,25,5,.88));border-radius:22px;padding:22px;margin-bottom:18px;box-shadow:0 0 35px rgba(245,200,93,.14)}
.kernelHero h1{margin:0;color:#f5c85d}.kernelGrid{display:grid;grid-template-columns:1.2fr 1fr;gap:16px}.kernelPanel{border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:16px;background:rgba(0,0,0,.45)}
.kernelBtn{display:inline-block;background:#f5c85d;color:#111;padding:12px 16px;border-radius:12px;text-decoration:none;font-weight:800;margin-right:8px}.kernelBtn.dark{background:#111;color:#f5c85d;border:1px solid rgba(245,200,93,.35)}
.kernelItem{display:block;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:12px;margin:9px 0;background:rgba(255,255,255,.04);color:#fff;text-decoration:none}.kernelItem strong{color:#f5c85d}.pill{padding:3px 8px;border-radius:99px;background:rgba(245,200,93,.15);color:#f5c85d;font-size:12px}
@media(max-width:900px){.kernelGrid{grid-template-columns:1fr}}
</style></head><body><div class="shell"><?php require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main kernelWrap">
<section class="kernelHero"><h1>Goliath Kernel</h1><p>The nervous system: missions, agent jobs, Lead Brain, and proof of work.</p><p><a class="kernelBtn" target="_blank" href="/lead-engine/goliath-kernel.php?key=<?=h($key)?>">Begin Operations</a><a class="kernelBtn dark" target="_blank" href="/lead-engine/goliath-kernel.php?action=health&key=<?=h($key)?>">Health JSON</a><a class="kernelBtn dark" href="/dashboard/goliath-mission-control.php">Mission Control</a></p></section>
<section class="kernelGrid"><div class="kernelPanel"><h2>Latest Missions</h2><?php foreach($missions as $m):?><div class="kernelItem"><strong><?=h($m['agent']??'')?></strong> <span class="pill"><?=h($m['status']??'')?></span><br><?=h($m['title']??'')?><br><small><?=h($m['priority']??'normal')?> · <?=h($m['created_at']??'')?></small></div><?php endforeach;?></div>
<div class="kernelPanel"><h2>Agent Jobs</h2><?php foreach($jobs as $j):?><div class="kernelItem"><strong><?=h($j['agent']??'')?></strong> <span class="pill"><?=h($j['status']??'')?></span><br><?=h($j['job_type']??'')?><br><small><?=h($j['priority']??'normal')?> · <?=h($j['created_at']??'')?></small></div><?php endforeach;?></div></section>
<section class="kernelPanel" style="margin-top:16px"><h2>Lead Brain Preview</h2><?php foreach($leads as $l):?><div class="kernelItem"><strong><?=h($l['lead_name']??'Unnamed Lead')?></strong> <span class="pill"><?=h($l['status']??'new')?></span><br><?=h($l['town']??'')?> <?=h($l['phone']??'')?> <?=h($l['email']??'')?><br><small>Score <?=h($l['lead_score']??50)?> · <?=h($l['source']??'')?></small></div><?php endforeach;?></section>
</main></div></body></html>
