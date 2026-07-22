<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=gdb_all("SELECT q.*,c.name,c.owner_name,c.property_address c_address,c.town c_town,c.notes
 FROM goliath_contact_enrichment_queue q JOIN internal_crm_contacts c ON c.id=q.contact_id
 ORDER BY FIELD(q.status,'ready_for_mark','working','queued','retry','needs_tool_access','not_found'),q.priority DESC,q.updated_at DESC LIMIT 1000")?:[];
$stats=['total'=>count($rows),'queued'=>0,'working'=>0,'ready'=>0,'tool'=>0,'notfound'=>0];
foreach($rows as $r){if($r['status']==='queued')$stats['queued']++;elseif($r['status']==='working')$stats['working']++;elseif($r['status']==='ready_for_mark')$stats['ready']++;elseif($r['status']==='needs_tool_access')$stats['tool']++;elseif($r['status']==='not_found')$stats['notfound']++;}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Internal Contact Enrichment</title>
<style>body{margin:0;background:#050914;color:#edf4ff;font-family:Arial}.hero{padding:25px;background:#0c1728}.hero h1{margin:0;color:#f0cd73}.wrap{padding:18px}.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:9px}.kpi,.panel{background:#0b1321;border:1px solid #253650;border-radius:14px}.kpi{padding:12px}.kpi b{font-size:25px;color:#f0cd73;display:block}.panel{margin-top:14px;overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #1e2a3b;text-align:left;vertical-align:top}th{color:#91a3b8;font-size:11px;text-transform:uppercase}.btn{display:inline-block;background:#805f12;color:#fff;padding:8px 10px;border-radius:8px;text-decoration:none;font-weight:900}.status{display:inline-block;padding:4px 7px;border-radius:99px;background:#263753}.ready{background:#0b7648}@media(max-width:900px){.kpis{grid-template-columns:1fr 1fr}}</style></head><body><header class="hero"><h1>Scout / OpenClaw Contact Enrichment</h1><p>Every internal contact missing a phone or email becomes real research work. Results require evidence and confidence.</p></header><main class="wrap">
<div class="kpis"><div class="kpi"><b><?=$stats['total']?></b>Total</div><div class="kpi"><b><?=$stats['queued']?></b>Queued</div><div class="kpi"><b><?=$stats['working']?></b>Working</div><div class="kpi"><b><?=$stats['ready']?></b>Ready for Mark</div><div class="kpi"><b><?=$stats['tool']?></b>Needs Tools</div><div class="kpi"><b><?=$stats['notfound']?></b>Not Found</div></div>
<p><a class="btn" target="_blank" href="/lead-engine/goliath-v119-3-enrichment-dispatch.php?key=<?=h($key)?>&limit=50">Dispatch 50</a> <a class="btn" target="_blank" href="/lead-engine/goliath-v119-3-enrichment-apply.php?key=<?=h($key)?>&limit=200">Apply Results</a> <a class="btn" href="/dashboard/leads.php">Website Leads</a></p>
<section class="panel"><table><thead><tr><th>Status</th><th>Contact</th><th>Property</th><th>Missing</th><th>Result</th><th>Evidence</th></tr></thead><tbody><?php foreach($rows as $r):?><tr>
<td><span class="status <?=$r['status']==='ready_for_mark'?'ready':''?>"><?=h($r['status'])?></span><br>Priority <?=h($r['priority'])?></td>
<td><strong><?=h($r['owner_name']?:$r['name'])?></strong><br>Contact #<?=h($r['contact_id'])?></td>
<td><?=h($r['property_address']?:$r['c_address'])?><br><?=h($r['town']?:$r['c_town'])?></td>
<td><?=!empty($r['missing_phone'])?'Phone ':''?><?=!empty($r['missing_email'])?'Email':''?></td>
<td><?=h($r['best_phone'])?><br><?=h($r['best_email'])?><br>Confidence <?=max((int)$r['phone_confidence'],(int)$r['email_confidence'])?>%</td>
<td><?=nl2br(h($r['source_evidence']?:$r['error_message']))?></td>
</tr><?php endforeach;?></tbody></table></section></main></body></html>