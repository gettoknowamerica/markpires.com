<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb108d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return is_array($d)?$d:[];
}
$logs=sb108d('jessica_opportunity_router_log?select=*&order=created_at.desc&limit=200');
$appts=sb108d('appointment_requests?select=*&order=created_at.desc&limit=50');
$actions=sb108d('mark_action_queue?select=*&order=created_at.desc&limit=50');
$stats=['logs'=>count($logs),'ok'=>0,'appointments'=>0,'actions'=>0,'errors'=>0];
foreach($logs as $l){if(!empty($l['ok']))$stats['ok']++;else $stats['errors']++;if(($l['routed_to']??'')==='appointment_requests')$stats['appointments']++;if(($l['routed_to']??'')==='mark_action_queue')$stats['actions']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Opportunity Router V10.8</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">Jessica Opportunity Router V10.8</div><div>Turns hot signals into appointment requests and Mark action tasks</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/route-jessica-opportunities.php?key=<?=h($cronKey)?>">Run Opportunity Router</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['logs'])?></div>Routes Logged</div><div class="kpi"><div class="n"><?=h($stats['ok'])?></div>Successful</div><div class="kpi"><div class="n"><?=h($stats['appointments'])?></div>To Appointments</div><div class="kpi"><div class="n"><?=h($stats['actions'])?></div>To Actions</div></section>
<div class="layout">
<section class="panel"><h2>Recent Appointment Requests</h2><table><tr><th>Lead</th><th>Status</th><th>Source</th><th>Notes</th></tr><?php foreach($appts as $a):?><tr><td><strong><?=h($a['name'])?></strong><div class="muted"><?=h($a['phone'])?><br><?=h($a['town'])?></div></td><td><?=h($a['status'])?></td><td><?=h($a['source'])?></td><td><?=h($a['notes'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Recent Action Queue</h2><table><tr><th>Lead</th><th>Priority</th><th>Action</th><th>Due</th></tr><?php foreach($actions as $a):?><tr><td><strong><?=h($a['name'])?></strong><div class="muted"><?=h($a['phone'])?><br><?=h($a['town'])?></div></td><td><?=h($a['priority'])?></td><td><?=h($a['recommended_action'])?></td><td><?=h($a['due_at'])?></td></tr><?php endforeach;?></table></section>
</div>
<section class="panel"><h2>Router Log</h2><table><tr><th>Time</th><th>Source</th><th>Route</th><th>OK</th><th>Reason</th></tr><?php foreach($logs as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['source_table'])?><div class="muted"><?=h($l['source_id'])?></div></td><td><?=h($l['routed_to'])?><br><?=h($l['route_type'])?></td><td><?=h(!empty($l['ok'])?'yes':'no')?></td><td><?=h($l['reason'])?></td></tr><?php endforeach;?></table></section>
</main><?php goliath_ui_close(); ?></body></html>