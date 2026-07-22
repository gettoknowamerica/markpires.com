<?php
/**
 * V10.9 Jessica Command Center
 * Upload: /public_html/dashboard/command-center.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb109($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>20]);
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($b,true); return is_array($d)?$d:[];
}
function count_rows109($table,$filter=''){
  $ep=$table.'?select=id'.($filter?'&'.$filter:'').'&limit=1000';
  return count(sb109($ep));
}

$stats=[
  'leads'=>count_rows109('leads'),
  'mission'=>count_rows109('jessica_priority_queue','status=eq.pending'),
  'appointments'=>count_rows109('appointment_requests','status=in.(requested,offered,confirmed)'),
  'hunter_review'=>count_rows109('hunter_queue','status=eq.review'),
  'hunter_approved'=>count_rows109('hunter_queue','status=in.(approved,queued)'),
  'future_sellers'=>count_rows109('future_seller_pipeline','status=eq.active'),
  'actions'=>count_rows109('mark_action_queue','status=in.(open,pending)'),
  'hot_alerts'=>count_rows109('hot_lead_alerts'),
];

$recentLeads=sb109('leads?select=*&order=created_at.desc&limit=8');
$recentAppointments=sb109('appointment_requests?select=*&order=created_at.desc&limit=8');
$recentHunter=sb109('hunter_queue?select=*&order=hunter_score.desc&limit=8');
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';

$sections=[
  'Jessica Operations'=>[
    ['Jessica Mission Control','/dashboard/jessica-mission-control.php','Daily next-best-action queue'],
    ['Opportunity Router','/dashboard/opportunity-router.php','Routes hot signals into appointments/actions'],
    ['Action Queue','/dashboard/action-queue.php','Mark action tasks'],
    ['System Health','/dashboard/system-health.php','Operational health check'],
    ['Operations','/dashboard/operations.php','Operations overview'],
  ],
  'Hunter System'=>[
    ['Homeowner Hunter','/dashboard/homeowner-hunter.php','Hunter target queue'],
    ['Hunter Campaigns','/dashboard/hunter-campaigns.php','Campaign segments and call limits'],
    ['Autonomous Hunter Calling','/dashboard/autonomous-hunter-calling.php','Approved Retell outbound calls'],
    ['Hunter Learning','/dashboard/hunter-learning.php','Outcomes and learning'],
    ['Hunter Guardrails','/dashboard/hunter-guardrails.php','Safety switches and caps'],
    ['Hunter Daily Briefing','/dashboard/hunter-daily-briefing.php','Daily summary'],
  ],
  'Appointments'=>[
    ['Appointment Engine','/dashboard/appointment-engine.php','Appointment requests and confirmations'],
    ['Calendar Bridge','/dashboard/calendar-bridge.php','Google calendar bridge setup'],
    ['Calendar Booking Console','/dashboard/calendar-booking-console.php','Availability and booking'],
  ],
  'Intelligence'=>[
    ['Adaptive Intelligence','/dashboard/adaptive-intelligence.php','Town/source/outcome learning'],
    ['Homeowner Intelligence','/dashboard/homeowner-intelligence.php','Homeowner database'],
    ['Homeowner Radar','/dashboard/homeowner-radar.php','Radar scoring'],
    ['Future Seller Pipeline','/dashboard/future-sellers.php','Future seller tracking'],
    ['Jessica Script Library','/dashboard/jessica-script-library.php','Scripts and objections'],
  ],
  'Run Engines'=>[
    ['Run Master Cron','/lead-engine/cron-master.php?key='.$cronKey,'Full intelligence loop'],
    ['Run Hunter Queue','/lead-engine/build-hunter-queue.php?key='.$cronKey,'Build hunter queue'],
    ['Run Campaign Builder','/lead-engine/build-hunter-campaigns.php?key='.$cronKey,'Build campaign targets'],
    ['Apply Scripts','/lead-engine/apply-jessica-scripts.php?key='.$cronKey,'Apply scripts to queues'],
    ['Run Opportunity Router','/lead-engine/route-jessica-opportunities.php?key='.$cronKey,'Route signals'],
  ],
];
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:34px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:40px}.wrap{max-width:1500px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel,.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:18px}.card{box-shadow:none;border:1px solid #eee;padding:16px}.card a{font-weight:900;color:#10101a;text-decoration:none}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #eee;font-size:13px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.dark{background:#10101a;color:#fff}@media(max-width:1000px){.grid,.cards,.layout{grid-template-columns:1fr}.wrap{padding:14px}.brand{font-size:32px}}
</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">Jessica Command Center</div><div>Mark Pires AI Operations System · hunter · appointments · intelligence · routing</div></div><main class="wrap">
<section class="grid">
<div class="kpi"><div class="n"><?=h($stats['leads'])?></div>Total Leads</div>
<div class="kpi"><div class="n"><?=h($stats['mission'])?></div>Mission Items</div>
<div class="kpi"><div class="n"><?=h($stats['appointments'])?></div>Appointments</div>
<div class="kpi"><div class="n"><?=h($stats['hunter_review'])?></div>Hunter Review</div>
<div class="kpi"><div class="n"><?=h($stats['hunter_approved'])?></div>Hunter Approved</div>
<div class="kpi"><div class="n"><?=h($stats['future_sellers'])?></div>Future Sellers</div>
<div class="kpi"><div class="n"><?=h($stats['actions'])?></div>Action Queue</div>
<div class="kpi"><div class="n"><?=h($stats['hot_alerts'])?></div>Hot Alerts</div>
</section>

<?php foreach($sections as $title=>$items):?>
<section class="panel"><h2><?=h($title)?></h2><div class="cards">
<?php foreach($items as $item):?><div class="card"><a target="<?=str_starts_with($item[1],'/lead-engine')?'_blank':'_self'?>" href="<?=h($item[1])?>"><?=h($item[0])?></a><div class="muted"><?=h($item[2])?></div></div><?php endforeach;?>
</div></section>
<?php endforeach;?>

<div class="layout">
<section class="panel"><h2>Recent Leads</h2><table><tr><th>Lead</th><th>Score</th><th>Source</th></tr><?php foreach($recentLeads as $l):?><tr><td><strong><?=h($l['name']??'')?></strong><div class="muted"><?=h($l['phone']??'')?><br><?=h($l['town']??'')?></div></td><td><?=h($l['lead_score']??'')?></td><td><?=h($l['source']??($l['type']??''))?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Recent Appointments</h2><table><tr><th>Lead</th><th>Status</th><th>When</th></tr><?php foreach($recentAppointments as $a):?><tr><td><strong><?=h($a['name']??'')?></strong><div class="muted"><?=h($a['phone']??'')?><br><?=h($a['town']??'')?></div></td><td><?=h($a['status']??'')?></td><td><?=h($a['confirmed_start']??'')?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Top Hunter Targets</h2><table><tr><th>Homeowner</th><th>Score</th><th>Status</th></tr><?php foreach($recentHunter as $hrow):?><tr><td><strong><?=h($hrow['owner_name']??'')?></strong><div class="muted"><?=h($hrow['town']??'')?><br><?=h($hrow['address']??'')?></div></td><td><?=h($hrow['hunter_score']??'')?></td><td><?=h($hrow['status']??'')?></td></tr><?php endforeach;?></table></section>
</div>
</main><?php goliath_ui_close(); ?></body></html>
