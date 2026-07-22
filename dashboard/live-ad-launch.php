<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb1291d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['ready','launched','paused','needs_fix'],true)){
    sb1291d('PATCH','live_ad_launch_checklists?id=eq.'.rawurlencode($id),['launch_status'=>$status,'updated_at'=>date('c')]);
    $msg='Campaign marked '.$status.'.';
  }
}
$rows=sb1291d('GET','live_ad_launch_checklists?select=*&order=created_at.desc&limit=300');
$stats=['total'=>count($rows),'ready'=>0,'launched'=>0,'needs_fix'=>0,'paused'=>0,'draft'=>0];
foreach($rows as $r){$s=$r['launch_status']??'draft'; if(isset($stats[$s]))$stats[$s]++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Live Ad Launch</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}code{word-break:break-all;background:#f2efe8;padding:4px;border-radius:6px}.ready{color:#14783c;font-weight:900}.needs_fix{color:#9a6400;font-weight:900}.launched{color:#174ea6;font-weight:900}.paused{color:#777;font-weight:900}@media(max-width:1000px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Live Ad Launch</div><div>UTM links, checklist, and manual launch tracker</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-live-ad-launch.php?key=<?=h($cronKey)?>">Build Launch Links</a><a class="btn light" href="/dashboard/daily-command-center.php">Command</a><a class="btn light" href="/dashboard/roi-attribution.php">ROI</a><a class="btn light" href="/dashboard/first-ad-campaigns.php">Ad Assets</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['ready'])?></div>Ready</div><div class="kpi"><div class="n"><?=h($stats['launched'])?></div>Launched</div><div class="kpi"><div class="n"><?=h($stats['needs_fix'])?></div>Needs Fix</div><div class="kpi"><div class="n"><?=h($stats['paused'])?></div>Paused</div></section>
<section class="panel"><h2>Launch Checklist</h2><table><tr><th>Status</th><th>Campaign</th><th>Final URL</th><th>Missing</th><th>Actions</th></tr><?php foreach($rows as $r):$missing=is_string($r['missing_items']??null)?json_decode($r['missing_items'],true):($r['missing_items']??[]); if(!is_array($missing))$missing=[]; ?><tr><td class="<?=h($r['launch_status'])?>"><?=h($r['launch_status'])?><div class="muted">$<?=h($r['daily_budget'])?>/day<br><?=h($r['platform'])?></div></td><td><strong><?=h($r['campaign_name'])?></strong><div class="muted"><?=h($r['utm_campaign'])?></div></td><td><code><?=h($r['final_url'])?></code><br><a class="btn light" target="_blank" href="<?=h($r['final_url'])?>">Open</a></td><td><?php foreach($missing as $m):?><?=h($m)?><br><?php endforeach;?><div class="muted"><?=h($r['launch_notes'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="status" value="launched">Launched</button><button class="btn light" name="status" value="ready">Ready</button><button class="btn light" name="status" value="paused">Pause</button></form></td></tr><?php endforeach;?></table></section>
</main></body></html>