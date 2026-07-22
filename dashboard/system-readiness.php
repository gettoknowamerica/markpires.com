<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb126d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$rows=sb126d('system_readiness_checks?select=*&order=created_at.desc&limit=300');
$latestTime=$rows[0]['created_at']??'';
$latest=[];
foreach($rows as $r){
  if($latestTime && ($r['created_at']??'')!==$latestTime)continue;
  $latest[]=$r;
}
$stats=['ok'=>0,'warning'=>0,'error'=>0,'unknown'=>0,'total'=>count($latest),'score'=>0];
foreach($latest as $r){$s=$r['status']??'unknown';if(isset($stats[$s]))$stats[$s]++;$stats['score']+=(int)($r['score']??0);}
$overall=$stats['total']?round($stats['score']/$stats['total']):0;
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V12.6 System Readiness</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.ok{color:#14783c;font-weight:900}.warning{color:#9a6400;font-weight:900}.error{color:#a40000;font-weight:900}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V12.6 System Readiness</div><div>Final scanner before first ad push and Jessica live monitoring</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/system-readiness-scan.php?key=<?=h($cronKey)?>">Run Readiness Scan</a><a class="btn light" href="/dashboard/launch-control.php">Launch Control</a><a class="btn light" href="/dashboard/first-ad-campaigns.php">First Ads</a><a class="btn light" href="/dashboard/cron-control-center.php">Cron</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($overall)?>%</div>Overall</div><div class="kpi"><div class="n"><?=h($stats['ok'])?></div>OK</div><div class="kpi"><div class="n"><?=h($stats['warning'])?></div>Warnings</div><div class="kpi"><div class="n"><?=h($stats['error'])?></div>Errors</div><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Checks</div></section>
<section class="panel"><h2>Latest Checks <?=h($latestTime)?></h2><table><tr><th>Status</th><th>Group</th><th>Check</th><th>Detail</th></tr><?php foreach($latest as $r):?><tr><td class="<?=h($r['status'])?>"><?=h($r['status'])?></td><td><?=h($r['check_group'])?></td><td><strong><?=h($r['check_name'])?></strong><div class="muted">Score <?=h($r['score'])?></div></td><td><?=h($r['detail'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>