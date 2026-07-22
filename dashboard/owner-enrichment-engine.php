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
$rows=sbget('owner_enrichment_queue?select=*&order=priority_score.desc,updated_at.desc&limit=500');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Owner Enrichment Engine</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:42px;margin:0}.wrap{max-width:1900px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;box-shadow:0 4px 18px #0001;overflow:hidden;margin-top:16px}.inner{padding:18px}.btn{background:#c8a96e;color:#111;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:900;display:inline-block;margin:3px}table{width:100%;border-collapse:collapse}td,th{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{background:#faf9f6;color:#777;text-transform:uppercase;font-size:11px}.score{font-size:30px;font-weight:900;color:#c8a96e}.tag{display:inline-block;background:#111827;color:white;border-radius:99px;padding:4px 8px;font-size:11px}.warn{background:#92400e}.ok{background:#14532d}.muted{color:#666;font-size:12px}details{margin-top:8px}summary{cursor:pointer;font-weight:800}pre{white-space:pre-wrap;background:#111827;color:white;padding:10px;border-radius:10px;max-width:600px;overflow:auto}</style></head><body>
<section class="hero"><h1>V20.2 Owner Enrichment Engine</h1><p>Jessica turns municipal owner records into compliant research tasks, seller signals, and owner-enrichment queues.</p></section>
<main class="wrap">
<section class="panel"><div class="inner">
<a class="btn" href="/dashboard/municipal-csv-import.php">Import Municipal CSV</a>
<a class="btn" target="_blank" href="/lead-engine/build-owner-enrichment.php?key=<?=h($key)?>&limit=500">Run Owner Enrichment</a>
<a class="btn" href="/dashboard/municipal-owner-qualifier.php">Owner Qualifier</a>
<a class="btn" href="/dashboard/leads.php">Leads</a>
</div></section>
<section class="panel">
<table><tr><th>Priority</th><th>Owner / Property</th><th>Status</th><th>Seller Signals</th><th>Research Queries</th><th>Compliance / Next Step</th></tr>
<?php if(!$rows['ok']):?><tr><td colspan="6"><pre><?=h($rows['body'])?></pre></td></tr><?php else: foreach($rows['data'] as $r): $queries=is_array($r['search_queries']??null)?$r['search_queries']:json_decode($r['search_queries']??'[]',true); ?>
<tr>
<td><div class="score"><?=h($r['priority_score']??0)?></div><span class="muted">Seller <?=h($r['seller_signal_score']??0)?></span></td>
<td><strong><?=h($r['owner_name']??'')?></strong><br><?=h($r['property_address']??'')?><br><span class="muted"><?=h($r['town']??'')?></span></td>
<td><span class="tag"><?=h($r['enrichment_status']??'queued')?></span><br><span class="muted">Approval: <?=h($r['human_approval_status']??'not_ready')?></span></td>
<td><?=h($r['estimated_years_owned']??0)?> estimated years owned<br><?=h($r['enrichment_notes']??'')?></td>
<td>
<details><summary>Show queries</summary><pre><?php foreach((array)$queries as $q) echo h($q)."\n"; ?></pre></details>
</td>
<td><strong><?=h($r['compliance_status']??'not_checked')?></strong><br>DNC + realtor exclusion required before direct outreach.<br><span class="muted">Manual research first: assessor, land records, people-search review where permitted.</span></td>
</tr>
<?php endforeach; endif;?>
</table>
</section>
</main></body></html>