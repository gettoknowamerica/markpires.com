<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbget($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>45]);
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=sbget('municipal_owner_records?select=*&order=owner_signal_score.desc,updated_at.desc&limit=500');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Municipal Owner Qualifier</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:40px;margin:0}.wrap{max-width:1900px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;box-shadow:0 4px 18px #0001;overflow:hidden;margin-top:16px}.inner{padding:18px}.btn{background:#c8a96e;color:#111;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:900;display:inline-block;margin:3px}table{width:100%;border-collapse:collapse}td,th{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{background:#faf9f6;color:#777;text-transform:uppercase;font-size:11px}.score{font-size:30px;font-weight:900;color:#c8a96e}.tag{display:inline-block;background:#111827;color:white;border-radius:99px;padding:4px 8px;font-size:11px}.warn{background:#92400e}.ok{background:#14532d}.muted{color:#666;font-size:12px}</style></head><body>
<section class="hero"><h1>Municipal Owner Qualifier</h1><p>Jessica reviews imported public owner records, flags 7+ year signals, and keeps outreach behind compliance approval.</p></section>
<main class="wrap">
<div class="panel"><div class="inner">
<a class="btn" href="/dashboard/municipal-csv-import.php">Import CSV</a>
<a class="btn" target="_blank" href="/lead-engine/build-municipal-owner-qualifier.php?key=<?=h($key)?>">Run Jessica Qualifier</a>
<a class="btn" target="_blank" href="/lead-engine/build-municipal-owner-intelligence.php?key=<?=h($key)?>">Run Old Scorer</a>
</div></div>
<section class="panel">
<table><tr><th>Score</th><th>Owner / Property</th><th>Tenure Signal</th><th>Qualification</th><th>Jessica Review</th><th>Mark Action</th></tr>
<?php if(!$rows['ok']):?><tr><td colspan="6"><pre><?=h($rows['body'])?></pre></td></tr><?php else: foreach($rows['data'] as $r):?>
<tr>
<td><div class="score"><?=h($r['owner_signal_score']??0)?></div></td>
<td><strong><?=h($r['owner_name']??'')?></strong><br><?=h($r['property_address']??'')?><br><span class="muted"><?=h($r['town']??'')?> | Tax $<?=h($r['total_tax']??0)?></span></td>
<td><?=h($r['estimated_tenure_years']??0)?> est. years<br><?=h($r['years_observed']??0)?> observed tax years<br><span class="tag warn"><?=h($r['tenure_confidence']??'low')?> confidence</span></td>
<td><span class="tag"><?=h($r['qualification_stage']??$r['priority_status']??'research')?></span><br><span class="muted">DNC: <?=h($r['dnc_status']??'not_checked')?> | Realtor: <?=h($r['realtor_status']??'not_checked')?></span></td>
<td><?=h($r['jessica_review']??$r['notes']??'')?></td>
<td><?=h($r['mark_action']??'Research ownership tenure and verify compliance before outreach.')?></td>
</tr>
<?php endforeach; endif;?>
</table>
</section>
</main></body></html>