<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb117d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$forecasts=sb117d('builder_forecasts?select=*&status=eq.active&order=forecast_score.desc&limit=300');
$runs=sb117d('builder_forecast_runs?select=*&order=created_at.desc&limit=20');
$stats=['total'=>count($forecasts),'urgent'=>0,'high'=>0,'risk'=>0,'expected'=>0];
$towns=[];$builders=[];
foreach($forecasts as $f){
  if(($f['forecast_band']??'')==='urgent')$stats['urgent']++;
  if(($f['forecast_band']??'')==='high')$stats['high']++;
  if(in_array(($f['risk_level']??''),['elevated','high'],true))$stats['risk']++;
  $stats['expected']+=(float)($f['expected_referral_value']??0);
  $town=$f['opportunity_town']?:'Unknown';$towns[$town]=($towns[$town]??0)+1;
  $builder=$f['builder_name']?:'Unknown';$builders[$builder]=($builders[$builder]??0)+1;
}
arsort($towns);arsort($builders);
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Builder Forecasting V11.7</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.layout{display:grid;grid-template-columns:1fr .35fr;gap:18px}.muted{color:#777;font-size:13px}.badge{border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase;font-weight:900}.urgent{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.moderate{background:#e9f2ff;color:#174ea6}.low{background:#eee;color:#555}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Builder Forecasting V11.7</div><div>Predicted outcomes · risk detection · expected referral revenue</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-builder-forecasts.php?key=<?=h($cronKey)?>">Build Forecasts</a> <a class="btn" href="/dashboard/builder-executive-center.php">Builder Executive Center</a> <a class="btn" href="/dashboard/builder-performance.php">Builder Performance</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Forecasts</div><div class="kpi"><div class="n"><?=h($stats['urgent'])?></div>Urgent</div><div class="kpi"><div class="n"><?=h($stats['high'])?></div>High</div><div class="kpi"><div class="n"><?=h($stats['risk'])?></div>Risk Alerts</div><div class="kpi"><div class="n">$<?=h(number_format($stats['expected']))?></div>Expected</div></section>
<div class="layout"><section class="panel"><h2>Top Forecasted Opportunities</h2><table><tr><th>Score</th><th>Builder</th><th>Opportunity</th><th>Forecast</th><th>Risk</th><th>Action</th></tr><?php foreach($forecasts as $f):$band=$f['forecast_band']?:'moderate';?><tr><td><span class="badge <?=h($band)?>"><?=h($f['forecast_score'])?> <?=h($band)?></span></td><td><strong><?=h($f['builder_name'])?></strong><div class="muted"><?=h($f['company'])?><br><?=h($f['builder_tier'])?><br>Response <?=h(round(((float)$f['builder_response_rate'])*100,1))?>%</div></td><td><strong><?=h($f['opportunity_address'])?></strong><div class="muted"><?=h($f['opportunity_town'])?><br><?=h($f['opportunity_type'])?></div></td><td><?=h($f['expected_outcome'])?><div class="muted">Stage: <?=h($f['pipeline_stage'])?><br>Expected $<?=h(number_format((float)$f['expected_referral_value']))?></div></td><td><?=h($f['risk_level'])?><div class="muted"><?=h($f['risk_reason'])?></div></td><td><?=h($f['recommended_action'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Top Towns</h2><table><tr><th>Town</th><th>Count</th></tr><?php foreach(array_slice($towns,0,15,true) as $k=>$v):?><tr><td><?=h($k)?></td><td><?=h($v)?></td></tr><?php endforeach;?></table><h2>Top Builders</h2><table><tr><th>Builder</th><th>Count</th></tr><?php foreach(array_slice($builders,0,15,true) as $k=>$v):?><tr><td><?=h($k)?></td><td><?=h($v)?></td></tr><?php endforeach;?></table><h2>Recent Runs</h2><table><tr><th>Time</th><th>Total</th><th>Expected</th></tr><?php foreach($runs as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['total_forecasts'])?></td><td>$<?=h(number_format((float)$r['expected_referral_value']))?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>