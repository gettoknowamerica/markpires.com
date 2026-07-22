<?php
/**
 * V12.5 Cron Control Center
 * Upload: /public_html/dashboard/cron-control-center.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb125d($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPGET=>true,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY
    ],
    CURLOPT_TIMEOUT=>25
  ]);
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}

$keyLen = defined('AFTER_HOURS_CRON_KEY') ? strlen(AFTER_HOURS_CRON_KEY) : 0;
$host = 'https://markpires.com';
$jobs = [
  ['10:05 PM daily','Overnight Research','/lead-engine/build-overnight-research.php?key=YOUR_AFTER_HOURS_CRON_KEY','Research only. No calls.'],
  ['10:20 PM daily','Discovery Intelligence','/lead-engine/build-discovery-intelligence.php?key=YOUR_AFTER_HOURS_CRON_KEY','Builds buyer/seller/builder targets.'],
  ['10:35 PM daily','Compliant Import Queue','/lead-engine/build-compliant-import-queue.php?key=YOUR_AFTER_HOURS_CRON_KEY','Creates approval queue.'],
  ['10:45 PM daily','Cron Monitor','/lead-engine/cron-monitor.php?key=YOUR_AFTER_HOURS_CRON_KEY','Shows cron registry.'],
  ['10:50 PM daily','Launch Control','/lead-engine/run-launch-control.php?key=YOUR_AFTER_HOURS_CRON_KEY','Wakes all main systems.'],
  ['11:00 PM daily','First Ad Campaigns','/lead-engine/build-first-ad-campaigns.php?key=YOUR_AFTER_HOURS_CRON_KEY','Creates ad assets.'],
  ['8:05 AM daily','Master Cron','/lead-engine/cron-master.php?key=YOUR_AFTER_HOURS_CRON_KEY','Morning intelligence.'],
  ['Every 30-60 min daytime','Appointment Automation','/lead-engine/appointment-automation.php?key=YOUR_AFTER_HOURS_CRON_KEY','Calendar/appointment processing.'],
  ['Daytime only after approval','Hunter Calls','/lead-engine/run-hunter-cron.php?key=YOUR_AFTER_HOURS_CRON_KEY','Only after guardrails/import approvals.']
];

$logs = sb125d('cron_run_audit?select=*&order=created_at.desc&limit=50');
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cron Control Center V12.5</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.note{padding:16px;background:#fff8e6;border-radius:12px}.muted{color:#777;font-size:13px}code{background:#f2efe8;padding:3px 5px;border-radius:5px}</style></head><body><div class="header"><div class="brand">Cron Control Center V12.5</div><div>Exact Hostinger cron list + key sanity check</div></div><main class="wrap">
<div class="note"><strong>Your current AFTER_HOURS_CRON_KEY length is <?=h($keyLen)?>.</strong><br>The failed test showed expected length 80, so do not use <code>timetomakethedonuts</code> for cron. That is only the Google Calendar secret.</div>
<p><a class="btn" target="_blank" href="/lead-engine/cron-health-check.php?key=YOUR_AFTER_HOURS_CRON_KEY">Test Cron Key</a><a class="btn light" href="/dashboard/launch-control.php">Launch Control</a></p>
<section class="panel"><h2>Hostinger Cron Jobs</h2><table><tr><th>Start Time</th><th>Job</th><th>URL</th><th>Notes</th></tr><?php foreach($jobs as $j):?><tr><td><strong><?=h($j[0])?></strong></td><td><?=h($j[1])?></td><td><code><?=h($host.$j[2])?></code></td><td><?=h($j[3])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Recent Cron Audit</h2><table><tr><th>Time</th><th>Job</th><th>OK</th><th>Summary</th></tr><?php foreach($logs as $l):?><tr><td><?=h($l['created_at']??'')?></td><td><?=h($l['job_name']??'')?></td><td><?=h(!empty($l['ok'])?'yes':'no')?></td><td><?=h($l['response_summary']??'')?></td></tr><?php endforeach;?></table></section>
</main></body></html>