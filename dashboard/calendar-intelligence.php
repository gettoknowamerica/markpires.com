<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb118d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$appts=sb118d('appointment_requests?select=*&order=created_at.desc&limit=100');
$logs=sb118d('google_calendar_sync_logs?select=*&order=created_at.desc&limit=50');
$stats=['total'=>count($appts),'created'=>0,'error'=>0,'pending'=>0];
foreach($appts as $a){$s=$a['calendar_status']??'not_created';if($s==='created')$stats['created']++;elseif($s==='error')$stats['error']++;else $stats['pending']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendar Intelligence V11.8</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Calendar Intelligence V11.8</div><div>Jessica ↔ Google Calendar bridge</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/test-google-calendar.php">Test Calendar</a><a class="btn light" target="_blank" href="/lead-engine/calendar-check-availability.php">Check Tomorrow Availability</a><a class="btn light" target="_blank" href="/lead-engine/process-calendar-appointments.php?key=<?=h($cronKey)?>">Process Confirmed Appointments</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Appointments</div><div class="kpi"><div class="n"><?=h($stats['created'])?></div>Calendar Created</div><div class="kpi"><div class="n"><?=h($stats['pending'])?></div>Pending</div><div class="kpi"><div class="n"><?=h($stats['error'])?></div>Errors</div></section>
<div class="layout"><section class="panel"><h2>Appointment Calendar Status</h2><table><tr><th>Lead</th><th>Time</th><th>Status</th><th>Event</th></tr><?php foreach($appts as $a):?><tr><td><strong><?=h($a['name']??'')?></strong><div class="muted"><?=h($a['phone']??'')?><br><?=h($a['town']??'')?></div></td><td><?=h($a['confirmed_start']??'')?><br><?=h($a['confirmed_end']??'')?></td><td><?=h($a['status']??'')?><br><span class="muted"><?=h($a['calendar_status']??'')?></span></td><td><?=h($a['google_event_id']??'')?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Calendar Sync Logs</h2><table><tr><th>Time</th><th>Action</th><th>OK</th><th>HTTP</th></tr><?php foreach($logs as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['action'])?></td><td><?=h(!empty($l['ok'])?'yes':'no')?></td><td><?=h($l['http_status'])?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>