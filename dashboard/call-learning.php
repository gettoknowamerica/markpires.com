<?php
/**
 * Cold Call Learning Dashboard V4
 * Upload to: /public_html/dashboard/call-learning.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/');
  exit;
}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb_get($endpoint){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPGET=>true,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY
    ],
    CURLOPT_TIMEOUT=>20
  ]);
  $body=curl_exec($ch);
  curl_close($ch);
  $d=json_decode($body,true);
  return is_array($d)?$d:[];
}

$rows=sb_get('cold_call_outcomes?select=*&order=created_at.desc&limit=500');
$counts=[];$towns=[];$future=[];
foreach($rows as $r){
  $o=$r['outcome'] ?: 'unknown';
  $counts[$o]=($counts[$o]??0)+1;
  $t=$r['town'] ?: 'Unknown';
  if(!isset($towns[$t])) $towns[$t]=['total'=>0,'interested'=>0,'future'=>0,'dnc'=>0,'dead'=>0];
  $towns[$t]['total']++;
  if(in_array($o,['interested','appointment'],true)) $towns[$t]['interested']++;
  if($o==='future_seller'){$towns[$t]['future']++; $future[]=$r;}
  if($o==='dnc_request') $towns[$t]['dnc']++;
  if(in_array($o,['wrong_number','dead_lead','not_interested'],true)) $towns[$t]['dead']++;
}
uasort($towns, fn($a,$b)=>($b['interested']*5+$b['future']*3+$b['total'])<=>($a['interested']*5+$a['future']*3+$a['total']));
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cold Call Learning V4</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:24px 28px;display:flex;justify-content:space-between}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1400px;margin:0 auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:18px}
.kpi,.panel{background:#fff;border-radius:16px;padding:18px;box-shadow:0 8px 30px rgba(0,0,0,.06)}.panel{margin-bottom:18px}.n{font-size:32px;font-weight:900}
table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
.badge{border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.interested,.appointment{background:#2b2110;color:#ffd36b}.future_seller{background:#fff4d7;color:#8a5a00}.dnc_request,.wrong_number{background:#ffeaea;color:#9b1c1c}.not_interested,.nurture{background:#eee;color:#555}
.btn{display:inline-block;background:#10101a;color:#fff;padding:9px 12px;border-radius:9px;text-decoration:none;font-weight:800}.gold{background:#c8a96e;color:#111}
</style>
</head>
<body>
<div class="header"><strong>Cold Call Learning Engine V4</strong><div><a href="/dashboard/homeowner-radar.php">Radar</a> · <a href="/dashboard/jessica-batch-caller.php">Batch Caller</a></div></div>
<main class="wrap">
<p><a class="btn gold" href="/lead-engine/process-call-outcomes.php?key=<?=h($cronKey)?>" target="_blank">Process New Outcomes</a></p>

<section class="grid">
<div class="kpi"><div class="n"><?=h(count($rows))?></div>Total Outcomes</div>
<div class="kpi"><div class="n"><?=h(($counts['interested']??0)+($counts['appointment']??0))?></div>Interested</div>
<div class="kpi"><div class="n"><?=h($counts['future_seller']??0)?></div>Future Sellers</div>
<div class="kpi"><div class="n"><?=h($counts['dnc_request']??0)?></div>DNC Requests</div>
<div class="kpi"><div class="n"><?=h(($counts['wrong_number']??0)+($counts['not_interested']??0))?></div>Dead/Wrong</div>
</section>

<section class="panel">
<h2>Town Learning</h2>
<table><tr><th>Town</th><th>Total</th><th>Interested</th><th>Future</th><th>DNC</th><th>Dead</th></tr>
<?php foreach($towns as $town=>$r): ?>
<tr><td><strong><?=h($town)?></strong></td><td><?=h($r['total'])?></td><td><?=h($r['interested'])?></td><td><?=h($r['future'])?></td><td><?=h($r['dnc'])?></td><td><?=h($r['dead'])?></td></tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Future Seller Queue</h2>
<table><tr><th>Owner</th><th>Town</th><th>Phone</th><th>Follow Up</th><th>Summary</th></tr>
<?php foreach(array_slice($future,0,50) as $r): ?>
<tr><td><?=h($r['owner_name'])?></td><td><?=h($r['town'])?></td><td><?=h($r['phone'])?></td><td><?=h($r['next_followup_at'])?></td><td><?=h($r['jessica_summary'])?></td></tr>
<?php endforeach; ?>
</table>
</section>

<section class="panel">
<h2>Recent Outcomes</h2>
<table><tr><th>Outcome</th><th>Owner</th><th>Town</th><th>Phone</th><th>Confidence</th><th>Summary</th></tr>
<?php foreach(array_slice($rows,0,100) as $r): ?>
<tr><td><span class="badge <?=h($r['outcome'])?>"><?=h($r['outcome'])?></span></td><td><?=h($r['owner_name'])?></td><td><?=h($r['town'])?></td><td><?=h($r['phone'])?></td><td><?=h($r['outcome_confidence'])?></td><td><?=h($r['jessica_summary'])?></td></tr>
<?php endforeach; ?>
</table>
</section>
</main>
</body>
</html>
