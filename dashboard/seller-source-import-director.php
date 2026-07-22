<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function sb151d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='add_import'){
    sb151d('POST','seller_source_import_queue',[[
      'source_type'=>$_POST['source_type']??'fsbo','source_platform'=>$_POST['source_platform']??'manual','source_url'=>$_POST['source_url']??'',
      'listing_title'=>$_POST['listing_title']??'','property_address'=>$_POST['property_address']??'','town'=>$_POST['town']??'','state'=>'CT',
      'list_price'=>is_numeric($_POST['list_price']??0)?(float)$_POST['list_price']:0,'owner_name'=>$_POST['owner_name']??'',
      'owner_phone'=>$_POST['owner_phone']??'','owner_email'=>$_POST['owner_email']??'','notes'=>$_POST['notes']??'',
      'import_status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')
    ]]); $msg='Seller source import added.';
  }
  if($action==='add_watch'){
    sb151d('POST','seller_source_watchlist',[[
      'source_name'=>$_POST['source_name']??'','source_type'=>$_POST['source_type']??'fsbo','source_platform'=>$_POST['source_platform']??'',
      'source_url'=>$_POST['source_url']??'','target_market'=>$_POST['target_market']??'Fairfield County','target_town'=>$_POST['target_town']??'',
      'notes'=>$_POST['notes']??'','status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ]]); $msg='Watch source added.';
  }
}
$imports=sb151d('GET','seller_source_import_queue?select=*&order=created_at.desc&limit=300');
$watch=sb151d('GET','seller_source_watchlist?select=*&status=eq.active&order=created_at.asc&limit=200');
$briefs=sb151d('GET','seller_source_import_briefings?select=*&order=created_at.desc&limit=3');
$brief=$briefs[0]??[];
$stats=['imports'=>count($imports),'watch'=>count($watch),'waiting'=>0,'pushed'=>0,'fsbo'=>0];
foreach($imports as $i){if(($i['import_status']??'')==='new')$stats['waiting']++;if(($i['import_status']??'')==='pushed')$stats['pushed']++;if(($i['source_type']??'')==='fsbo')$stats['fsbo']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V15.1 Seller Source Import Director</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:26px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .36fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}input,select,textarea{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px 0}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V15.1 Seller Source Import Director</div><div>Safe intake layer for FSBO / Homes / Zillow / FSBO.com / expired / withdrawn sources before enrichment and calls</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-seller-source-import-director.php?key=<?=h($cronKey)?>">Push Imports</a><a class="btn light" href="/dashboard/seller-opportunity-engine.php">Seller Engine</a><a class="btn light" href="/dashboard/contact-enrichment-center.php">Enrichment</a><a class="btn light" href="/dashboard/seller-acquisition-director.php">Acquisition Director</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['watch'])?></div>Watch Sources</div><div class="kpi"><div class="n"><?=h($stats['imports'])?></div>Imports</div><div class="kpi"><div class="n"><?=h($stats['waiting'])?></div>Waiting</div><div class="kpi"><div class="n"><?=h($stats['pushed'])?></div>Pushed</div><div class="kpi"><div class="n"><?=h($stats['fsbo'])?></div>FSBO</div></section>
<div class="layout"><section class="panel"><h2>Import Queue</h2><table><tr><th>Status</th><th>Source</th><th>Property</th><th>Owner</th></tr><?php foreach($imports as $i):?><tr><td><?=h($i['import_status'])?></td><td><?=h($i['source_platform'])?><div class="muted"><?=h($i['source_type'])?><br><?php if($i['source_url']):?><a target="_blank" href="<?=h($i['source_url'])?>">Open</a><?php endif;?></div></td><td><strong><?=h($i['property_address']?:$i['listing_title'])?></strong><div class="muted"><?=h($i['town'])?><br><?=money($i['list_price'])?></div></td><td><?=h($i['owner_name'])?><div class="muted"><?=h($i['owner_phone'])?><br><?=h($i['owner_email'])?></div></td></tr><?php endforeach;?></table><h2>Watchlist</h2><table><tr><th>Source</th><th>URL</th></tr><?php foreach($watch as $w):?><tr><td><strong><?=h($w['source_name'])?></strong><div class="muted"><?=h($w['source_platform'])?> / <?=h($w['source_type'])?></div></td><td><a target="_blank" href="<?=h($w['source_url'])?>"><?=h($w['source_url'])?></a></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Add Seller Import</h2><div style="padding:16px"><form method="post"><input type="hidden" name="action" value="add_import"><select name="source_type"><option value="fsbo">FSBO</option><option value="expired">Expired</option><option value="withdrawn">Withdrawn</option><option value="price_reduced">Price Reduced</option></select><input name="source_platform" placeholder="Zillow / Homes / FSBO.com / MLS / Manual"><input name="source_url" placeholder="Listing URL"><input name="listing_title" placeholder="Listing title"><input name="property_address" placeholder="Property address"><input name="town" placeholder="Town"><input name="list_price" placeholder="List price"><input name="owner_name" placeholder="Owner first/full name"><input name="owner_phone" placeholder="Phone"><input name="owner_email" placeholder="Email"><textarea name="notes" placeholder="Notes"></textarea><button class="btn">Add Import</button></form></div><h2>Add Watch Source</h2><div style="padding:16px"><form method="post"><input type="hidden" name="action" value="add_watch"><input name="source_name" placeholder="Source name"><select name="source_type"><option value="fsbo">FSBO</option><option value="expired">Expired</option><option value="withdrawn">Withdrawn</option></select><input name="source_platform" placeholder="Platform"><input name="source_url" placeholder="URL"><input name="target_town" placeholder="Town optional"><textarea name="notes" placeholder="Notes"></textarea><button class="btn">Add Watch</button></form></div><h2>Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run Push Imports to create briefing.')?></pre></div></section></div>
</main></body></html>