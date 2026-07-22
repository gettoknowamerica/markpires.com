<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb132d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['source_review','pushed_to_acquisition','rejected','archived'],true)){
    sb132d('PATCH','source_hunter_targets?id=eq.'.rawurlencode($id),['acquisition_status'=>$status,'updated_at'=>date('c')]);
    $msg='Target updated.';
  }
}
$targets=sb132d('GET','source_hunter_targets?select=*&status=eq.active&order=intent_score.desc,created_at.desc&limit=300');
$missions=sb132d('GET','source_hunter_missions?select=*&order=priority_score.desc,created_at.desc&limit=200');
$briefs=sb132d('GET','source_hunter_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['targets'=>count($targets),'missions'=>count($missions),'fsbo'=>0,'expired'=>0,'cancelled'=>0,'withdrawn'=>0,'review'=>0];
foreach($targets as $t){$type=$t['source_type']??''; if(str_contains($type,'fsbo'))$stats['fsbo']++; if(str_contains($type,'expired'))$stats['expired']++; if(str_contains($type,'cancelled'))$stats['cancelled']++; if(str_contains($type,'withdrawn'))$stats['withdrawn']++; if(($t['acquisition_status']??'')==='source_review')$stats['review']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V13.2 Source Hunter</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V13.2 Source Hunter</div><div>FSBO, Make Me Move, expired/cancelled/withdrawn, investor and rental-owner opportunity sources</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-source-hunter.php?key=<?=h($cronKey)?>">Build Source Hunter</a><a class="btn light" href="/dashboard/contact-acquisition-center.php">Acquisition</a><a class="btn light" href="/dashboard/import-owner-records.php">Import Center</a><a class="btn light" href="/dashboard/listing-intelligence-center.php">V13 Listings</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['targets'])?></div>Targets</div><div class="kpi"><div class="n"><?=h($stats['missions'])?></div>Missions</div><div class="kpi"><div class="n"><?=h($stats['fsbo'])?></div>FSBO</div><div class="kpi"><div class="n"><?=h($stats['expired'])?></div>Expired</div><div class="kpi"><div class="n"><?=h($stats['cancelled'])?></div>Cancelled</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div></section>
<div class="layout"><section class="panel"><h2>Top Source Targets</h2><table><tr><th>Score</th><th>Target</th><th>Source</th><th>Action</th><th>Status</th></tr><?php foreach($targets as $t):?><tr><td><strong><?=h($t['intent_score'])?></strong><div class="muted">Signal <?=h($t['signal_strength'])?></div></td><td><strong><?=h($t['opportunity_reason'])?></strong><div class="muted"><?=h($t['property_address'])?><br><?=h($t['town'])?> <?=h($t['market'])?></div></td><td><?=h($t['source_type'])?><div class="muted"><?=h($t['source_platform'])?><br><?php if($t['source_url']):?><a target="_blank" href="<?=h($t['source_url'])?>">Open Source</a><?php endif;?></div></td><td><?=h($t['recommended_action'])?></td><td><?=h($t['acquisition_status'])?><form method="post"><input type="hidden" name="id" value="<?=h($t['id'])?>"><button class="btn" name="status" value="pushed_to_acquisition">Mark Pushed</button><button class="btn light" name="status" value="rejected">Reject</button><button class="btn light" name="status" value="archived">Archive</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Source Hunter Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Source Hunter to create briefing.')?></pre></div><h2>Search Missions</h2><table><tr><th>Score</th><th>Mission</th><th>Query</th></tr><?php foreach(array_slice($missions,0,60) as $m):?><tr><td><?=h($m['priority_score'])?></td><td><?=h($m['mission_name'])?></td><td><?=h($m['search_query'])?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>