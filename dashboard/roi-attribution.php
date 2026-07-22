<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb128d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $payload=[[
    'spend_date'=>$_POST['spend_date']?:date('Y-m-d'),
    'platform'=>$_POST['platform']??'Meta',
    'campaign_name'=>$_POST['campaign_name']??'',
    'market'=>$_POST['market']??'Fairfield County',
    'town'=>$_POST['town']??'',
    'audience'=>$_POST['audience']??'',
    'spend'=>(float)($_POST['spend']??0),
    'impressions'=>(int)($_POST['impressions']??0),
    'clicks'=>(int)($_POST['clicks']??0),
    'notes'=>$_POST['notes']??'',
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  sb128d('POST','marketing_spend_log',$payload);
  $msg='Spend logged.';
}
$snaps=sb128d('GET','roi_attribution_snapshots?select=*&order=created_at.desc&limit=20');
$s=$snaps[0]??[];
$sources=is_string($s['source_rollup']??null)?json_decode($s['source_rollup'],true):($s['source_rollup']??[]);
$campaigns=is_string($s['campaign_rollup']??null)?json_decode($s['campaign_rollup'],true):($s['campaign_rollup']??[]);
$recs=is_string($s['recommendations']??null)?json_decode($s['recommendations'],true):($s['recommendations']??[]);
if(!is_array($sources))$sources=[]; if(!is_array($campaigns))$campaigns=[]; if(!is_array($recs))$recs=[];
$spend=sb128d('GET','marketing_spend_log?select=*&order=spend_date.desc,created_at.desc&limit=100');
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>ROI Attribution V12.8</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}.muted{color:#777;font-size:13px}input,select{padding:8px;border:1px solid #ddd;border-radius:8px;margin:3px;max-width:180px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">ROI Attribution V12.8</div><div>Spend, leads, appointments, CPL, CPA, and Jessica budget recommendations</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-roi-attribution.php?key=<?=h($cronKey)?>">Build ROI Snapshot</a><a class="btn light" href="/dashboard/executive-intelligence.php">Executive</a><a class="btn light" href="/dashboard/first-ad-campaigns.php">First Ads</a></p>
<?php if(!empty($s)):?><section class="grid"><div class="kpi"><div class="n">$<?=h(number_format((float)$s['total_spend'],2))?></div>Spend</div><div class="kpi"><div class="n"><?=h($s['total_leads'])?></div>Leads</div><div class="kpi"><div class="n"><?=h($s['total_appointments'])?></div>Appointments</div><div class="kpi"><div class="n">$<?=h(number_format((float)$s['cost_per_lead'],2))?></div>CPL</div><div class="kpi"><div class="n">$<?=h(number_format((float)$s['cost_per_appointment'],2))?></div>CPA</div></section><?php endif;?>
<div class="layout"><section class="panel"><h2>Source Rollup</h2><table><tr><th>Source</th><th>Spend</th><th>Leads</th><th>Appointments</th><th>CPL</th></tr><?php foreach($sources as $x):?><tr><td><?=h($x['source']??'')?></td><td>$<?=h(number_format((float)($x['spend']??0),2))?></td><td><?=h($x['leads']??0)?></td><td><?=h($x['appointments']??0)?></td><td>$<?=h(number_format((float)($x['cpl']??0),2))?></td></tr><?php endforeach;?></table><h2>Campaign Rollup</h2><table><tr><th>Campaign</th><th>Spend</th><th>Leads</th><th>Appointments</th><th>CPL</th></tr><?php foreach($campaigns as $x):?><tr><td><?=h($x['campaign']??'')?></td><td>$<?=h(number_format((float)($x['spend']??0),2))?></td><td><?=h($x['leads']??0)?></td><td><?=h($x['appointments']??0)?></td><td>$<?=h(number_format((float)($x['cpl']??0),2))?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Log Spend</h2><form method="post" style="padding:16px"><input type="date" name="spend_date" value="<?=h(date('Y-m-d'))?>"><select name="platform"><option>Meta</option><option>Google</option><option>TikTok</option><option>YouTube</option><option>Organic</option></select><input name="campaign_name" placeholder="Campaign name"><input name="market" placeholder="Market" value="Fairfield County"><input name="town" placeholder="Town"><input name="audience" placeholder="Audience"><input type="number" step="0.01" name="spend" placeholder="Spend"><input type="number" name="impressions" placeholder="Impressions"><input type="number" name="clicks" placeholder="Clicks"><input name="notes" placeholder="Notes"><br><button class="btn">Save Spend</button></form><h2>Recommendations</h2><?php foreach($recs as $r):?><div style="padding:12px;border-bottom:1px solid #eee"><strong><?=h($r['type']??'')?></strong><br><?=h($r['message']??'')?></div><?php endforeach;?><h2>Recent Spend</h2><table><tr><th>Date</th><th>Platform</th><th>Campaign</th><th>Spend</th></tr><?php foreach($spend as $x):?><tr><td><?=h($x['spend_date'])?></td><td><?=h($x['platform'])?></td><td><?=h($x['campaign_name'])?></td><td>$<?=h(number_format((float)$x['spend'],2))?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>