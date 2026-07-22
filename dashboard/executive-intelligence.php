<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb127d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$snapshots=sb127d('executive_daily_snapshots?select=*&order=created_at.desc&limit=30');
$s=$snapshots[0]??[];
$monthly=is_string($s['monthly_rollup']??null)?json_decode($s['monthly_rollup'],true):($s['monthly_rollup']??[]);
$sources=is_string($s['source_summary']??null)?json_decode($s['source_summary'],true):($s['source_summary']??[]);
$activity=is_string($s['jessica_activity']??null)?json_decode($s['jessica_activity'],true):($s['jessica_activity']??[]);
$alerts=is_string($s['priority_alerts']??null)?json_decode($s['priority_alerts'],true):($s['priority_alerts']??[]);
if(!is_array($monthly))$monthly=[]; if(!is_array($sources))$sources=[]; if(!is_array($activity))$activity=[]; if(!is_array($alerts))$alerts=[];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Executive Intelligence V12.7</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.bar{height:12px;background:#c8a96e;border-radius:999px}.barbg{background:#eee;border-radius:999px;overflow:hidden}.alert{padding:12px;border-bottom:1px solid #eee}.quick{display:flex;flex-wrap:wrap;gap:8px}@media(max-width:1100px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Executive Intelligence V12.7</div><div>One command center for Jessica, leads, ads, appointments, builders, and launch readiness</div></div><main class="wrap">
<p class="quick">
<a class="btn" target="_blank" href="/lead-engine/build-executive-snapshot.php?key=<?=h($cronKey)?>">Build Snapshot</a>
<a class="btn light" href="/dashboard/launch-control.php">Launch Control</a>
<a class="btn light" href="/dashboard/first-ad-campaigns.php">First Ads</a>
<a class="btn light" href="/dashboard/appointment-automation.php">Appointments</a>
<a class="btn light" href="/dashboard/compliant-lead-imports.php">Imports</a>
<a class="btn light" href="/dashboard/builder-forecasting.php">Builder Forecasting</a>
<a class="btn light" href="/dashboard/system-readiness.php">Readiness</a>
</p>

<?php if(empty($s)):?>
<section class="panel"><h2>No Snapshot Yet</h2><div style="padding:18px">Click Build Snapshot.</div></section>
<?php else:?>
<section class="grid">
<div class="kpi"><div class="n"><?=h($s['new_leads_today'])?></div>New Leads Today</div>
<div class="kpi"><div class="n"><?=h($s['hot_leads'])?></div>Hot Leads</div>
<div class="kpi"><div class="n"><?=h($s['appointments_confirmed'])?></div>Confirmed Appts</div>
<div class="kpi"><div class="n"><?=h($s['calendar_created'])?></div>On Calendar</div>
<div class="kpi"><div class="n"><?=h($s['campaign_drafts'])?></div>Campaign Drafts</div>
<div class="kpi"><div class="n">$<?=h(number_format((float)$s['expected_builder_referral']))?></div>Builder Forecast</div>
<div class="kpi"><div class="n"><?=h($s['discovery_opportunities'])?></div>Discovery Opps</div>
<div class="kpi"><div class="n"><?=h($s['compliant_imports'])?></div>Imports</div>
<div class="kpi"><div class="n"><?=h($s['call_eligible_imports'])?></div>Call Eligible</div>
<div class="kpi"><div class="n"><?=h($s['builder_pipeline'])?></div>Builder Pipeline</div>
<div class="kpi"><div class="n"><?=h($s['action_queue_open'])?></div>Open Actions</div>
<div class="kpi"><div class="n"><?=h($s['approved_campaigns'])?></div>Approved Ads</div>
</section>

<div class="layout">
<section class="panel"><h2>12-Month Activity</h2><table><tr><th>Month</th><th>Leads</th><th>Appointments</th><th>Campaigns</th><th>Imports</th></tr><?php $max=1;foreach($monthly as $m){$max=max($max,(int)($m['leads']??0),(int)($m['appointments']??0),(int)($m['campaigns']??0),(int)($m['imports']??0));} foreach($monthly as $m):?><tr><td><?=h($m['month']??'')?></td><td><?=h($m['leads']??0)?><div class="barbg"><div class="bar" style="width:<?=h(round((($m['leads']??0)/$max)*100))?>%"></div></div></td><td><?=h($m['appointments']??0)?></td><td><?=h($m['campaigns']??0)?></td><td><?=h($m['imports']??0)?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Priority Alerts</h2><?php foreach($alerts as $a):?><div class="alert"><strong><?=h($a['type']??'')?></strong><br><?=h($a['message']??'')?></div><?php endforeach;?><h2>Jessica Activity</h2><table><?php foreach($activity as $k=>$v):?><tr><td><?=h($k)?></td><td><strong><?=h($v)?></strong></td></tr><?php endforeach;?></table></section>
</div>

<div class="layout">
<section class="panel"><h2>Lead Source Breakdown</h2><table><tr><th>Source</th><th>Count</th></tr><?php foreach($sources as $src):?><tr><td><?=h($src['source']??'')?></td><td><?=h($src['count']??0)?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Snapshot History</h2><table><tr><th>Time</th><th>Leads</th><th>Hot</th><th>Campaigns</th></tr><?php foreach($snapshots as $x):?><tr><td><?=h($x['created_at'])?></td><td><?=h($x['total_leads'])?></td><td><?=h($x['hot_leads'])?></td><td><?=h($x['campaign_drafts'])?></td></tr><?php endforeach;?></table></section>
</div>
<?php endif;?>
</main></body></html>