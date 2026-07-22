<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb115d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($b,true);
  return is_array($d)?$d:[];
}
$briefings=sb115d('builder_daily_briefings?select=*&order=created_at.desc&limit=30');
$latest=$briefings[0]??null;
$opps=sb115d('builder_developer_opportunities?select=*&order=builder_score.desc&limit=10');
$pipeline=sb115d('builder_pipeline?select=*&order=deal_probability.desc&limit=10');
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';

$towns=[];
$alerts=[];
$topOpp=[];
$topPipe=[];
if($latest){
  $towns=is_string($latest['town_summary']??null)?json_decode($latest['town_summary'],true):($latest['town_summary']??[]);
  $alerts=is_string($latest['mark_priority_alerts']??null)?json_decode($latest['mark_priority_alerts'],true):($latest['mark_priority_alerts']??[]);
  $topOpp=is_string($latest['top_opportunities']??null)?json_decode($latest['top_opportunities'],true):($latest['top_opportunities']??[]);
  $topPipe=is_string($latest['top_pipeline']??null)?json_decode($latest['top_pipeline'],true):($latest['top_pipeline']??[]);
  if(!is_array($towns))$towns=[];
  if(!is_array($alerts))$alerts=[];
  if(!is_array($topOpp))$topOpp=[];
  if(!is_array($topPipe))$topPipe=[];
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Builder Executive Center V11.5</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}
.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}
.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}
.btn{display:inline-block;background:#10101a;color:#fff;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}
table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}.muted{color:#777;font-size:13px}.brief{white-space:pre-wrap;padding:18px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header"><div class="brand">Builder Executive Center V11.5</div><div>Builder opportunities · matches · intros · pipeline · forecast</div></div>
<main class="wrap">
<p>
<a class="btn gold" target="_blank" href="/lead-engine/build-builder-briefing.php?key=<?=h($cronKey)?>">Build Builder Briefing</a>
<a class="btn light" target="_blank" href="/lead-engine/build-builder-briefing.php?key=<?=h($cronKey)?>&send=1">Build + Send</a>
<a class="btn light" href="/dashboard/builder-developer-radar.php">Radar</a>
<a class="btn light" href="/dashboard/builder-matchmaker.php">Matchmaker</a>
<a class="btn light" href="/dashboard/builder-intro-outreach.php">Outreach</a>
<a class="btn light" href="/dashboard/builder-pipeline.php">Pipeline</a>
</p>

<?php if($latest): ?>
<section class="grid">
<div class="kpi"><div class="n"><?=h($latest['total_opportunities'])?></div>Opportunities</div>
<div class="kpi"><div class="n"><?=h($latest['hot_opportunities'])?></div>Hot</div>
<div class="kpi"><div class="n"><?=h($latest['builder_matches'])?></div>Matches</div>
<div class="kpi"><div class="n"><?=h($latest['active_pipeline'])?></div>Active Pipeline</div>
<div class="kpi"><div class="n"><?=h($latest['followups_due'])?></div>Followups</div>
<div class="kpi"><div class="n"><?=h($latest['intros_drafted'])?></div>Drafts</div>
<div class="kpi"><div class="n"><?=h($latest['intros_sent'])?></div>Sent</div>
<div class="kpi"><div class="n"><?=h($latest['offers_possible'])?></div>Offer Possible</div>
<div class="kpi"><div class="n">$<?=h(number_format((float)$latest['referral_potential']))?></div>Referral Potential</div>
<div class="kpi"><div class="n">$<?=h(number_format((float)$latest['expected_referral_value']))?></div>Expected Value</div>
</section>

<div class="layout">
<section class="panel"><h2>Priority Alerts</h2><table><tr><th>Priority</th><th>Alert</th><th>Detail</th></tr><?php foreach($alerts as $a):?><tr><td><?=h($a['priority']??'')?></td><td><strong><?=h($a['title']??'')?></strong><div class="muted"><?=h($a['type']??'')?></div></td><td><?=h($a['detail']??'')?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Town Leaderboard</h2><table><tr><th>Town</th><th>Opps</th><th>Hot</th><th>Referral</th></tr><?php foreach($towns as $t):?><tr><td><?=h($t['town']??'')?></td><td><?=h($t['opportunities']??0)?></td><td><?=h($t['hot']??0)?></td><td>$<?=h(number_format((float)($t['referral_potential']??0)))?></td></tr><?php endforeach;?></table></section>
</div>

<div class="layout">
<section class="panel"><h2>Top Opportunities</h2><table><tr><th>Property</th><th>Type</th><th>Score</th><th>Reason</th></tr><?php foreach($topOpp as $o):?><tr><td><strong><?=h($o['address']??'')?></strong><div class="muted"><?=h($o['town']??'')?><br><?=h($o['top_builder_match']??'')?></div></td><td><?=h($o['type']??'')?></td><td><?=h($o['score']??'')?></td><td><?=h($o['reason']??'')?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Top Pipeline</h2><table><tr><th>Builder</th><th>Property</th><th>Stage</th><th>Forecast</th></tr><?php foreach($topPipe as $p):?><tr><td><strong><?=h($p['builder']??'')?></strong><div class="muted"><?=h($p['company']??'')?></div></td><td><?=h($p['address']??'')?><div class="muted"><?=h($p['town']??'')?></div></td><td><?=h($p['stage']??'')?><br><?=h($p['probability']??0)?>%</td><td>$<?=h(number_format((float)($p['referral_potential']??0)))?><div class="muted"><?=h($p['next_step']??'')?></div></td></tr><?php endforeach;?></table></section>
</div>

<section class="panel"><h2>Latest Briefing</h2><div class="brief"><?=h($latest['briefing_text'])?></div></section>
<?php else: ?>
<section class="panel"><h2>No Briefing Yet</h2><div style="padding:18px">Click Build Builder Briefing to generate the first report.</div></section>
<?php endif; ?>

<section class="panel"><h2>Briefing History</h2><table><tr><th>Date</th><th>Hot</th><th>Pipeline</th><th>Expected</th><th>Sent</th></tr><?php foreach($briefings as $b):?><tr><td><?=h($b['briefing_date'])?><div class="muted"><?=h($b['created_at'])?></div></td><td><?=h($b['hot_opportunities'])?></td><td><?=h($b['active_pipeline'])?></td><td>$<?=h(number_format((float)$b['expected_referral_value']))?></td><td>Email <?=h(!empty($b['email_sent'])?'yes':'no')?> / SMS <?=h(!empty($b['sms_sent'])?'yes':'no')?></td></tr><?php endforeach;?></table></section>
</main>
</body>
</html>