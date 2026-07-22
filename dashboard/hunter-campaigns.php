<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbc102($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='create'){
    $payload=[[
      'name'=>$_POST['name']??'',
      'campaign_type'=>'homeowner',
      'campaign_segment'=>$_POST['campaign_segment']??'general',
      'town'=>$_POST['town']??null,
      'min_years_owned'=>(float)($_POST['min_years_owned']??5),
      'min_equity'=>(float)($_POST['min_equity']??0),
      'min_hunter_score'=>(int)($_POST['min_hunter_score']??70),
      'max_daily_calls'=>(int)($_POST['max_daily_calls']??25),
      'script_key'=>$_POST['script_key']??'cold_homeowner',
      'status'=>'active',
      'notes'=>$_POST['notes']??'',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];
    $r=sbc102('POST','hunter_campaigns',$payload);
    $msg=$r['ok']?'Campaign created.':'Create failed: '.$r['body'];
  } elseif($action==='toggle'){
    $id=$_POST['id']??'';$status=$_POST['status']??'paused';
    $r=sbc102('PATCH','hunter_campaigns?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>date('c')]);
    $msg=$r['ok']?'Campaign updated.':'Update failed.';
  }
}
$campaigns=sbc102('GET','hunter_campaigns?select=*&order=created_at.desc&limit=200')['data'];
$queue=sbc102('GET','hunter_queue?select=campaign_name,status,hunter_score,town&limit=1000')['data'];
$byCampaign=[];$hot=0;
foreach($queue as $q){$name=$q['campaign_name']?:'No Campaign';$byCampaign[$name]=($byCampaign[$name]??0)+1;if((int)($q['hunter_score']??0)>=100)$hot++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hunter Campaigns V10.2</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.card{background:#fff;border-radius:16px;padding:18px;box-shadow:0 2px 12px #0001}.n{font-size:34px;font-weight:900}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}input,select,textarea{padding:9px;border:1px solid #ddd;border-radius:8px;margin:3px;width:95%;max-width:260px}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Hunter Campaigns V10.2</div><div>Campaign segmentation · daily target controls · scripts · <a style="color:#fff" href="/dashboard/homeowner-hunter.php">Hunter Queue</a></div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/build-hunter-campaigns.php?key=<?=h($cronKey)?>">Build Campaign Targets</a></p>
<section class="grid"><div class="card"><div class="n"><?=h(count($campaigns))?></div>Campaigns</div><div class="card"><div class="n"><?=h(array_sum($byCampaign))?></div>Targets</div><div class="card"><div class="n"><?=h($hot)?></div>100+ Hunter Score</div></section>
<section class="panel"><h2>Create Campaign</h2><form method="post" style="padding:18px"><input type="hidden" name="action" value="create"><input name="name" placeholder="Campaign Name"><select name="campaign_segment"><option value="general">General</option><option value="10_year_owner">10+ Year Owner</option><option value="luxury_equity">Luxury Equity</option><option value="downsizer">Likely Downsizer</option></select><input name="town" placeholder="Town optional"><input type="number" name="min_years_owned" value="5" placeholder="Min Years Owned"><input type="number" name="min_equity" value="0" placeholder="Min Equity"><input type="number" name="min_hunter_score" value="70" placeholder="Min Score"><input type="number" name="max_daily_calls" value="25" placeholder="Daily Limit"><select name="script_key"><option value="cold_homeowner">Cold Homeowner</option><option value="luxury_homeowner">Luxury Homeowner</option><option value="downsizer_homeowner">Downsizer</option><option value="expired_listing">Expired Listing</option><option value="warm_lead">Warm Lead</option></select><textarea name="notes" placeholder="Notes"></textarea><button class="btn gold">Create</button></form></section>
<section class="panel"><h2>Campaigns</h2><table><tr><th>Name</th><th>Segment</th><th>Rules</th><th>Daily</th><th>Status</th><th>Built</th><th>Actions</th></tr><?php foreach($campaigns as $c):?><tr><td><strong><?=h($c['name'])?></strong><div class="muted"><?=h($c['notes'])?></div></td><td><?=h($c['campaign_segment'])?><br><?=h($c['script_key'])?></td><td>Town: <?=h($c['town']?:'Any')?><br>Years: <?=h($c['min_years_owned'])?>+<br>Equity: $<?=h(number_format((float)$c['min_equity']))?><br>Score: <?=h($c['min_hunter_score'])?>+</td><td><?=h($c['max_daily_calls'])?></td><td><?=h($c['status'])?></td><td><?=h($c['last_built_at'])?><br>Created: <?=h($c['targets_created'])?></td><td><form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=h($c['id'])?>"><button class="btn light" name="status" value="<?=h($c['status']==='active'?'paused':'active')?>"><?=h($c['status']==='active'?'Pause':'Activate')?></button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Targets By Campaign</h2><table><tr><th>Campaign</th><th>Targets</th></tr><?php foreach($byCampaign as $name=>$count):?><tr><td><?=h($name)?></td><td><?=h($count)?></td></tr><?php endforeach;?></table></section></main></body></html>