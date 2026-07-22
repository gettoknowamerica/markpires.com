<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb1212d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$rows=sb1212d('seo_aeo_content_opportunities?select=*&order=priority_score.desc,created_at.desc&limit=300');
$briefs=sb1212d('seo_aeo_daily_briefings?select=*&order=created_at.desc&limit=10');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'seller'=>0,'relocation'=>0,'builder'=>0,'draft'=>0];
foreach($rows as $r){
  if(($r['content_type']??'')==='seller')$stats['seller']++;
  if(($r['content_type']??'')==='relocation')$stats['relocation']++;
  if(($r['content_type']??'')==='builder')$stats['builder']++;
  if(($r['status']??'')==='draft')$stats['draft']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath SEO / AEO Engine</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}code{word-break:break-all;background:#f2efe8;padding:3px 5px;border-radius:5px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">SEO / AEO Content Engine V12.12</div><div>Organic opportunity pages, FAQ, schema, town content, seller/buyer/builder search capture</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-seo-aeo-content.php?key=<?=h($cronKey)?>">Build SEO/AEO Content</a><a class="btn light" href="/dashboard/hunter-mode.php">Hunter Mode</a><a class="btn light" href="/dashboard/executive-intelligence.php">Executive</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['seller'])?></div>Seller Pages</div><div class="kpi"><div class="n"><?=h($stats['relocation'])?></div>Relocation</div><div class="kpi"><div class="n"><?=h($stats['builder'])?></div>Builder</div><div class="kpi"><div class="n"><?=h($stats['draft'])?></div>Drafts</div></section>
<div class="layout"><section class="panel"><h2>Content Opportunities</h2><table><tr><th>Score</th><th>Title</th><th>SEO</th><th>CTA</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['priority_score'])?></strong><div class="muted"><?=h($r['content_type'])?><br><?=h($r['status'])?></div></td><td><strong><?=h($r['title'])?></strong><div class="muted"><?=h($r['town'])?> · <?=h($r['market'])?></div></td><td><div>KW: <?=h($r['keyword_primary'])?></div><div>Slug: <code><?=h($r['slug'])?></code></div><div class="muted"><?=h($r['meta_description'])?></div></td><td><?=h($r['cta'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Content Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run SEO/AEO Content to generate briefing.')?></pre></div><h2>Recent Briefs</h2><table><tr><th>Date</th><th>Total</th><th>Seller</th><th>Buyer</th></tr><?php foreach($briefs as $b):?><tr><td><?=h($b['briefing_date'])?></td><td><?=h($b['total_opportunities'])?></td><td><?=h($b['seller_pages'])?></td><td><?=h($b['buyer_pages'])?></td></tr><?php endforeach;?></table></section></div>
</main><?php goliath_ui_close(); ?></body></html>