<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb119d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$appts=sb119d('appointment_requests?select=*&order=created_at.desc&limit=200');
$runs=sb119d('appointment_automation_runs?select=*&order=created_at.desc&limit=20');
$msgs=sb119d('appointment_automation_messages?select=*&order=created_at.desc&limit=50');
$stats=['requested'=>0,'offered'=>0,'confirmed'=>0,'calendar'=>0,'errors'=>0];
foreach($appts as $a){
  $s=$a['status']??'';
  if(isset($stats[$s]))$stats[$s]++;
  if(($a['calendar_status']??'')==='created')$stats['calendar']++;
  if(str_contains((string)($a['automation_status']??''),'error'))$stats['errors']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Appointment Automation V11.9</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.layout{display:grid;grid-template-columns:1fr .4fr;gap:18px}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Appointment Automation V11.9</div><div>Jessica offers slots, books selected appointments, creates calendar events, sends confirmations</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/appointment-automation.php?key=<?=h($cronKey)?>">Run Appointment Automation</a><a class="btn light" href="/dashboard/calendar-intelligence.php">Calendar Intelligence</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['requested'])?></div>Requested</div><div class="kpi"><div class="n"><?=h($stats['offered'])?></div>Offered</div><div class="kpi"><div class="n"><?=h($stats['confirmed'])?></div>Confirmed</div><div class="kpi"><div class="n"><?=h($stats['calendar'])?></div>On Calendar</div><div class="kpi"><div class="n"><?=h($stats['errors'])?></div>Errors</div></section>
<div class="layout"><section class="panel"><h2>Appointments</h2><table><tr><th>Lead</th><th>Status</th><th>Selected</th><th>Calendar</th><th>Manual Slot Links</th></tr><?php foreach($appts as $a):?><tr><td><strong><?=h($a['name']??'')?></strong><div class="muted"><?=h($a['phone']??'')?><br><?=h($a['email']??'')?><br><?=h($a['town']??'')?></div></td><td><?=h($a['status']??'')?><br><span class="muted"><?=h($a['automation_status']??'')?></span></td><td><?=h($a['selected_slot_start']??'')?><br><?=h($a['selected_slot_end']??'')?></td><td><?=h($a['calendar_status']??'')?><div class="muted"><?=h($a['google_event_id']??'')?></div></td><td><a class="btn light" target="_blank" href="/lead-engine/select-appointment-slot.php?key=<?=h($cronKey)?>&id=<?=h($a['id'])?>&option=1">Pick 1</a><a class="btn light" target="_blank" href="/lead-engine/select-appointment-slot.php?key=<?=h($cronKey)?>&id=<?=h($a['id'])?>&option=2">Pick 2</a><a class="btn light" target="_blank" href="/lead-engine/select-appointment-slot.php?key=<?=h($cronKey)?>&id=<?=h($a['id'])?>&option=3">Pick 3</a></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Recent Runs</h2><table><tr><th>Time</th><th>Attempted</th><th>Booked</th><th>Errors</th></tr><?php foreach($runs as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['attempted'])?></td><td><?=h($r['booked'])?></td><td><?=h($r['errors'])?></td></tr><?php endforeach;?></table><h2>Recent Messages</h2><table><tr><th>Time</th><th>Type</th><th>Status</th></tr><?php foreach($msgs as $m):?><tr><td><?=h($m['created_at'])?></td><td><?=h($m['message_type'])?></td><td><?=h($m['status'])?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>