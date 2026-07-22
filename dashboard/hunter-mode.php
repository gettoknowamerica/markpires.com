<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb1211d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$rows=sb1211d('hunter_priority_rankings?select=*&status=eq.active&order=hunter_score.desc,created_at.desc&limit=300');
$briefs=sb1211d('hunter_daily_briefings?select=*&order=created_at.desc&limit=10');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'call_first'=>0,'call_today'=>0,'seller'=>0,'buyer'=>0,'builder'=>0];
foreach($rows as $r){
  if(($r['call_recommendation']??'')==='call_first')$stats['call_first']++;
  if(($r['call_recommendation']??'')==='call_today')$stats['call_today']++;
  if(($r['hunter_type']??'')==='seller')$stats['seller']++;
  if(in_array(($r['hunter_type']??''),['buyer','relocation'],true))$stats['buyer']++;
  if(in_array(($r['hunter_type']??''),['builder','developer'],true))$stats['builder']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V12.11 Hunter Mode</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.A{color:#14783c;font-weight:900}.B{color:#9a6400;font-weight:900}.nurture{color:#777}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Jessica Hunter Mode V12.11</div><div>Top calls, seller/buyer/builder ranking, and morning action direction</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-hunter-mode.php?key=<?=h($cronKey)?>">Build Hunter Rankings</a><a class="btn light" href="/dashboard/executive-intelligence.php">Executive</a><a class="btn light" href="/dashboard/live-ad-launch.php">Live Ads</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Ranked</div><div class="kpi"><div class="n"><?=h($stats['call_first'])?></div>Call First</div><div class="kpi"><div class="n"><?=h($stats['call_today'])?></div>Call Today</div><div class="kpi"><div class="n"><?=h($stats['seller'])?></div>Sellers</div><div class="kpi"><div class="n"><?=h($stats['buyer'])?></div>Buyers</div><div class="kpi"><div class="n"><?=h($stats['builder'])?></div>Builder/Dev</div></section>
<div class="layout"><section class="panel"><h2>Hunter Priority Rankings</h2><table><tr><th>Score</th><th>Target</th><th>Type</th><th>Recommendation</th><th>Reason</th></tr><?php foreach($rows as $r):?><tr><td><span class="<?=h($r['priority_band'])?>"><?=h($r['priority_band'])?></span><br><strong><?=h($r['hunter_score'])?></strong></td><td><strong><?=h($r['name'] ?: $r['town'].' '.$r['hunter_type'])?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['town'])?> <?=h($r['market'])?></div></td><td><?=h($r['hunter_type'])?><div class="muted"><?=h($r['audience'])?></div></td><td><strong><?=h($r['call_recommendation'])?></strong><div class="muted">Eligible: <?=h(!empty($r['call_eligible'])?'yes':'no')?><br><?=h($r['compliance_status'])?></div></td><td><?=h($r['reason'])?><div class="muted"><?=h($r['next_action'])?></div></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Morning Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run Hunter Rankings to create today’s brief.')?></pre></div><h2>Brief History</h2><table><tr><th>Date</th><th>Ranked</th><th>Calls</th></tr><?php foreach($briefs as $b):?><tr><td><?=h($b['briefing_date'])?></td><td><?=h($b['total_ranked'])?></td><td><?=h($b['call_first'])?> first / <?=h($b['call_today'])?> today</td></tr><?php endforeach;?></table></section></div>
</main></body></html>