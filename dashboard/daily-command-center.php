<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb1213d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$snaps=sb1213d('daily_command_center_snapshots?select=*&order=created_at.desc&limit=20');
$s=$snaps[0]??[];
function arr($v){ if(is_string($v)){$x=json_decode($v,true);return is_array($x)?$x:[];} return is_array($v)?$v:[]; }
$towns=arr($s['top_towns']??[]);
$campaigns=arr($s['top_campaigns']??[]);
$content=arr($s['top_content']??[]);
$hunter=arr($s['top_hunter_items']??[]);
$recs=arr($s['jessica_recommendations']??[]);
$ready=arr($s['readiness']??[]);
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V12.13 Daily Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.ok{color:#14783c;font-weight:900}.warn{color:#9a6400;font-weight:900}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">Daily Command Center V12.13</div><div>Jessica’s single-screen morning direction for Mark</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-daily-command-center.php?key=<?=h($cronKey)?>">Build Command Snapshot</a><a class="btn light" href="/dashboard/hunter-mode.php">Hunter</a><a class="btn light" href="/dashboard/seo-aeo-engine.php">SEO/AEO</a><a class="btn light" href="/dashboard/live-ad-launch.php">Live Ads</a><a class="btn light" href="/dashboard/roi-attribution.php">ROI</a></p>
<?php if(empty($s)):?><section class="panel"><h2>No Command Snapshot Yet</h2><div style="padding:18px">Click Build Command Snapshot.</div></section><?php else:?>
<section class="grid">
<div class="kpi"><div class="n"><?=h($s['discovery_opportunities'])?></div>Discovery</div>
<div class="kpi"><div class="n"><?=h($s['compliant_imports'])?></div>Imports</div>
<div class="kpi"><div class="n"><?=h($s['call_eligible_imports'])?></div>Call Eligible</div>
<div class="kpi"><div class="n"><?=h($s['hunter_rankings'])?></div>Hunter</div>
<div class="kpi"><div class="n"><?=h($s['campaign_drafts'])?></div>Campaigns</div>
<div class="kpi"><div class="n"><?=h($s['seo_opportunities'])?></div>SEO/AEO</div>
<div class="kpi"><div class="n"><?=h($s['campaign_assets'])?></div>Assets</div>
<div class="kpi"><div class="n"><?=h($s['live_ad_ready'])?></div>Ad Ready</div>
<div class="kpi"><div class="n">$<?=h(number_format((float)$s['roi_spend'],2))?></div>Spend</div>
<div class="kpi"><div class="n"><?=h($s['roi_leads'])?></div>ROI Leads</div>
<div class="kpi"><div class="n"><?=h($s['roi_appointments'])?></div>ROI Appts</div>
<div class="kpi"><div class="n"><?=h($s['call_today'])?></div>Call Today</div>
</section>
<div class="layout"><section class="panel"><h2>Jessica Recommendations</h2><table><?php foreach($recs as $i=>$r):?><tr><td><strong><?=h($i+1)?></strong></td><td><?=h($r)?></td></tr><?php endforeach;?></table><h2>Top Towns</h2><table><tr><th>Town</th><th>Signal</th></tr><?php foreach($towns as $t):?><tr><td><?=h($t['town']??'')?></td><td><?=h($t['score']??0)?></td></tr><?php endforeach;?></table><h2>Top Campaigns</h2><table><tr><th>Campaign</th><th>Score</th><th>CTA</th></tr><?php foreach($campaigns as $c):?><tr><td><?=h($c['campaign_name']??'')?><div class="muted"><?=h($c['landing_page']??'')?></div></td><td><?=h($c['score']??0)?></td><td><?=h($c['cta']??'')?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Command Brief</h2><div style="padding:16px"><pre><?=h($s['command_brief']??'')?></pre></div><h2>Readiness</h2><table><?php foreach($ready as $k=>$v):?><tr><td><?=h($k)?></td><td class="<?=str_contains((string)$v,'ready')?'ok':'warn'?>"><?=h($v)?></td></tr><?php endforeach;?></table></section></div>
<div class="layout"><section class="panel"><h2>Top Content</h2><table><tr><th>Title</th><th>Type</th><th>Score</th></tr><?php foreach($content as $c):?><tr><td><?=h($c['title']??'')?><div class="muted"><?=h($c['slug']??'')?></div></td><td><?=h($c['type']??'')?></td><td><?=h($c['score']??0)?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Top Hunter Items</h2><table><tr><th>Item</th><th>Score</th><th>Rec</th></tr><?php foreach($hunter as $h):?><tr><td><?=h($h['name']??'')?><div class="muted"><?=h($h['town']??'')?> · <?=h($h['type']??'')?></div></td><td><?=h($h['score']??0)?></td><td><?=h($h['recommendation']??'')?></td></tr><?php endforeach;?></table></section></div>
<section class="panel"><h2>Snapshot History</h2><table><tr><th>Time</th><th>Discovery</th><th>Hunter</th><th>SEO</th></tr><?php foreach($snaps as $x):?><tr><td><?=h($x['created_at'])?></td><td><?=h($x['discovery_opportunities'])?></td><td><?=h($x['hunter_rankings'])?></td><td><?=h($x['seo_opportunities'])?></td></tr><?php endforeach;?></table></section>
<?php endif;?>
</main><?php goliath_ui_close(); ?></body></html>