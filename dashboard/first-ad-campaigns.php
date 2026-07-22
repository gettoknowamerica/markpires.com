<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb124d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id&&$action==='approve'){
    sb124d('PATCH','first_campaign_plan?id=eq.'.rawurlencode($id),['approved_for_launch'=>true,'status'=>'approved','updated_at'=>date('c')]);
    $msg='Campaign approved for launch.';
  }
}
$campaigns=sb124d('GET','first_campaign_plan?select=*&order=priority_score.desc,created_at.desc&limit=100');
$assets=sb124d('GET','campaign_launch_assets?select=*&order=created_at.desc&limit=200');
$stats=['campaigns'=>count($campaigns),'assets'=>count($assets),'approved'=>0,'seller'=>0,'buyer'=>0];
foreach($campaigns as $c){if(!empty($c['approved_for_launch']))$stats['approved']++;if(str_contains(strtolower($c['campaign_name']??''),'home value'))$stats['seller']++;if(str_contains(strtolower($c['campaign_name']??''),'buyer')||str_contains(strtolower($c['campaign_name']??''),'nyc'))$stats['buyer']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V12.4 First Ad Campaign Builder</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V12.4 First Ad Campaign Builder</div><div>Meta, Google, retargeting, creative prompts, and launch checklist</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-first-ad-campaigns.php?key=<?=h($cronKey)?>">Build Ad Assets</a><a class="btn light" href="/dashboard/launch-control.php">Launch Control</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['campaigns'])?></div>Campaigns</div><div class="kpi"><div class="n"><?=h($stats['assets'])?></div>Assets</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['seller'])?></div>Seller</div><div class="kpi"><div class="n"><?=h($stats['buyer'])?></div>Buyer</div></section>
<div class="layout"><section class="panel"><h2>Campaigns</h2><table><tr><th>Score</th><th>Campaign</th><th>Meta Ad</th><th>Google Search</th><th>Approve</th></tr><?php foreach($campaigns as $c):$heads=is_string($c['google_search_headlines']??null)?json_decode($c['google_search_headlines'],true):($c['google_search_headlines']??[]);$descs=is_string($c['google_search_descriptions']??null)?json_decode($c['google_search_descriptions'],true):($c['google_search_descriptions']??[]);?><tr><td><strong><?=h($c['priority_score'])?></strong><br>$<?=h($c['campaign_budget']??25)?>/day</td><td><strong><?=h($c['campaign_name'])?></strong><div class="muted"><?=h($c['town'])?><br><?=h($c['landing_page'])?><br><?=h($c['status'])?></div></td><td><strong><?=h($c['facebook_headline'])?></strong><br><?=h($c['facebook_primary_text'])?><div class="muted"><?=h($c['creative_prompt'])?></div></td><td><?php foreach((array)$heads as $x):?><?=h($x)?><br><?php endforeach;?><div class="muted"><?php foreach((array)$descs as $x):?><?=h($x)?><br><?php endforeach;?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($c['id'])?>"><button class="btn" name="action" value="approve">Approve</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Assets</h2><table><tr><th>Type</th><th>Copy</th></tr><?php foreach($assets as $a):?><tr><td><?=h($a['asset_type'])?><div class="muted"><?=h($a['campaign_name'])?></div></td><td><strong><?=h($a['headline'])?></strong><br><?=h($a['body'])?><div class="muted"><?=h($a['cta'])?> · <?=h($a['landing_page'])?></div></td></tr><?php endforeach;?></table></section></div>
</main></body></html>