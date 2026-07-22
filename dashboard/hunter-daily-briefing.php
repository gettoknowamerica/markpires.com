<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbd107($m,$ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}
$briefings=sbd107('GET','hunter_daily_briefings?select=*&order=created_at.desc&limit=30')['data'];
$latest=$briefings[0]??null;
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hunter Daily Briefing V10.7</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1300px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#10101a;color:#fff;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.brief{white-space:pre-wrap;padding:18px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Hunter Daily Briefing V10.7</div><div>Daily target summary · performance · top towns · top calls</div></div><main class="wrap">
<p><a class="btn gold" target="_blank" href="/lead-engine/build-hunter-briefing.php?key=<?=h($cronKey)?>">Build Briefing</a><a class="btn light" target="_blank" href="/lead-engine/build-hunter-briefing.php?key=<?=h($cronKey)?>&send=1">Build + Send</a><a class="btn light" href="/dashboard/hunter-guardrails.php">Guardrails</a></p>
<?php if($latest):?>
<section class="grid"><div class="kpi"><div class="n"><?=h($latest['total_hunter_targets'])?></div>Total</div><div class="kpi"><div class="n"><?=h($latest['approved_targets'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($latest['called_today'])?></div>Called Today</div><div class="kpi"><div class="n"><?=h($latest['future_sellers_found'])?></div>Future Sellers</div><div class="kpi"><div class="n"><?=h($latest['appointments_found'])?></div>Appointments</div></section>
<section class="panel"><h2>Latest Briefing</h2><div class="brief"><?=h($latest['briefing_text'])?></div></section>
<?php endif;?>
<section class="panel"><h2>Briefing History</h2><table><tr><th>Date</th><th>Total</th><th>Approved</th><th>Called</th><th>Future</th><th>Appts</th><th>Sent</th></tr><?php foreach($briefings as $b):?><tr><td><?=h($b['briefing_date'])?><div class="muted"><?=h($b['created_at'])?></div></td><td><?=h($b['total_hunter_targets'])?></td><td><?=h($b['approved_targets'])?></td><td><?=h($b['called_today'])?></td><td><?=h($b['future_sellers_found'])?></td><td><?=h($b['appointments_found'])?></td><td>Email <?=h($b['email_sent']?'yes':'no')?> / SMS <?=h($b['sms_sent']?'yes':'no')?></td></tr><?php endforeach;?></table></section>
</main></body></html>