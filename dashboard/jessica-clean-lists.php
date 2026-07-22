<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb1511d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$batches=sb1511d('jessica_clean_list_batches?select=*&order=created_at.desc&limit=50');
$items=sb1511d('jessica_clean_list_items?select=*&order=lead_score.desc,created_at.desc&limit=300');
$stats=['items'=>count($items),'accepted'=>0,'review'=>0,'duplicates'=>0,'research_only'=>0,'pushed'=>0];
foreach($items as $i){
  $d=$i['decision']??'review';
  if($d==='accepted')$stats['accepted']++;
  if($d==='review')$stats['review']++;
  if($d==='duplicate')$stats['duplicates']++;
  if($d==='research_only')$stats['research_only']++;
  if(!empty($i['pushed_to_compliant_imports']))$stats['pushed']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Clean Lists V12.15.1</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.accepted{color:#14783c;font-weight:900}.duplicate,.needs_cleanup{color:#9a6400;font-weight:900}.research_only{color:#777;font-weight:900}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Jessica Clean Lists V12.15.1</div><div>Cleaned contact intake, dedupe, scoring, and compliant import bridge</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/jessica-clean-list-intake.php?key=<?=h($cronKey)?>&demo=1">Run Demo Intake</a><a class="btn light" href="/dashboard/compliant-lead-imports.php">Compliant Imports</a><a class="btn light" href="/dashboard/hunter-mode.php">Hunter</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['items'])?></div>Items</div><div class="kpi"><div class="n"><?=h($stats['accepted'])?></div>Accepted</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['duplicates'])?></div>Duplicates</div><div class="kpi"><div class="n"><?=h($stats['research_only'])?></div>Research</div><div class="kpi"><div class="n"><?=h($stats['pushed'])?></div>Pushed</div></section>
<div class="layout"><section class="panel"><h2>Clean List Items</h2><table><tr><th>Score</th><th>Contact</th><th>Decision</th><th>Compliance</th><th>Reason</th></tr><?php foreach($items as $i):?><tr><td><strong><?=h($i['lead_score'])?></strong><div class="muted">Clean <?=h($i['cleanliness_score'])?></div></td><td><strong><?=h($i['name']?:'Unnamed')?></strong><div class="muted"><?=h($i['phone'])?><br><?=h($i['email'])?><br><?=h($i['town'])?> <?=h($i['market'])?></div></td><td class="<?=h($i['decision'])?>"><?=h($i['decision'])?><div class="muted">Pushed: <?=h(!empty($i['pushed_to_compliant_imports'])?'yes':'no')?></div></td><td><?=h($i['consent_status'])?><br>DNC <?=h($i['dnc_status'])?><br><?=h($i['approval_status'])?></td><td><?=h($i['decision_reason'])?><div class="muted"><?=h($i['notes'])?></div></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Batches</h2><table><tr><th>Batch</th><th>Totals</th></tr><?php foreach($batches as $b):?><tr><td><strong><?=h($b['batch_name'])?></strong><div class="muted"><?=h($b['created_at'])?><br><?=h($b['batch_status'])?></div></td><td>Submitted <?=h($b['total_submitted'])?><br>Accepted <?=h($b['accepted'])?><br>Skipped <?=h($b['skipped'])?><br>Duplicates <?=h($b['duplicates'])?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>