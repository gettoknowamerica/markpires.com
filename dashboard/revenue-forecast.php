<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function arr($v){ if(is_string($v)){ $d=json_decode($v,true); return is_array($d)?$d:[]; } return is_array($v)?$v:[]; }
function sb21d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$rows=sb21d('revenue_forecast_snapshots?select=*&order=created_at.desc&limit=20');
$s=$rows[0]??[];
$towns=arr($s['top_towns']??[]);
$sources=arr($s['top_revenue_sources']??[]);
$recs=arr($s['recommendations']??[]);
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revenue Forecast V12.21</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Revenue Forecast V12.21</div><div>Pipeline value, commission forecast, referral value, and next revenue actions</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-revenue-forecast.php?key=<?=h($cronKey)?>">Build Forecast</a><a class="btn light" href="/dashboard/jessica-deliverables.php">Deliverables</a><a class="btn light" href="/dashboard/approved-contact-pipeline.php">Contacts</a><a class="btn light" href="/dashboard/queue-intelligence.php">Queue</a></p>
<?php if(empty($s)):?><section class="panel"><h2>No Forecast Yet</h2><div style="padding:18px">Click Build Forecast.</div></section><?php else:?>
<section class="grid">
<div class="kpi"><div class="n"><?=money($s['estimated_pipeline_value'])?></div>Pipeline</div>
<div class="kpi"><div class="n"><?=money($s['estimated_commission_value'])?></div>Commission</div>
<div class="kpi"><div class="n"><?=money($s['expected_close_forecast'])?></div>Expected</div>
<div class="kpi"><div class="n"><?=money($s['estimated_referral_value'])?></div>Referral Value</div>
<div class="kpi"><div class="n"><?=h($s['total_leads'])?></div>Contacts</div>
<div class="kpi"><div class="n"><?=h($s['call_eligible_contacts'])?></div>Callable</div>
<div class="kpi"><div class="n"><?=h($s['appointments_pending'])?></div>Appointments</div>
<div class="kpi"><div class="n"><?=h($s['live_ads_ready'])?></div>Ads Ready</div>
</section>
<div class="layout"><section class="panel"><h2>Forecast Brief</h2><div style="padding:16px"><pre><?=h($s['forecast_brief'])?></pre></div><h2>Forecast History</h2><table><tr><th>Time</th><th>Pipeline</th><th>Expected</th><th>Callable</th></tr><?php foreach($rows as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=money($r['estimated_pipeline_value'])?></td><td><?=money($r['expected_close_forecast'])?></td><td><?=h($r['call_eligible_contacts'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Recommendations</h2><table><?php foreach($recs as $i=>$r):?><tr><td><strong><?=h($i+1)?></strong></td><td><?=h($r)?></td></tr><?php endforeach;?></table><h2>Top Towns</h2><table><tr><th>Town</th><th>Count</th></tr><?php foreach($towns as $t):?><tr><td><?=h($t['town']??'')?></td><td><?=h($t['count']??0)?></td></tr><?php endforeach;?></table><h2>Sources</h2><table><tr><th>Source</th><th>Count</th></tr><?php foreach($sources as $src):?><tr><td><?=h($src['source']??'')?></td><td><?=h($src['count']??0)?></td></tr><?php endforeach;?></table></section></div>
<?php endif;?></main></body></html>