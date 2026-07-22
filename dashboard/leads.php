<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php'))require_once __DIR__.'/includes/goliath-nav.php';
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function phone($p):string{$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)===11&&str_starts_with($d,'1'))$d=substr($d,1);return $d;}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=gdb_all("SELECT l.*,c.best_phone,c.best_email,c.research_status,c.contact_enrichment_status,c.relationship_status
 FROM leads l LEFT JOIN internal_crm_contacts c ON c.id=l.crm_contact_id
 ORDER BY l.created_at DESC,l.id DESC LIMIT 1000")?:[];
$hot=count(array_filter($rows,fn($r)=>(int)($r['lead_score']??0)>=75));
$drip=count(array_filter($rows,fn($r)=>($r['drip_status']??'')==='enrolled'));
$missing=count(array_filter($rows,fn($r)=>empty($r['best_phone'])&&empty($r['phone'])));
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Internal Website Leads</title>
<style>
body{margin:0;background:#050914;color:#edf4ff;font-family:Arial,sans-serif}.hero{background:linear-gradient(135deg,#0a1423,#111c32);padding:25px}.hero h1{margin:0;color:#f0cd73;font-family:Georgia,serif}.wrap{padding:18px;max-width:1800px;margin:auto}.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px}.kpi,.panel{background:#0b1321;border:1px solid #253650;border-radius:15px}.kpi{padding:14px}.kpi b{font-size:28px;color:#f0cd73;display:block}.panel{overflow:hidden}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #1e2a3b;text-align:left;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#8fa2ba}.pill{display:inline-block;padding:4px 8px;border-radius:99px;background:#253550;font-size:11px}.green{background:#0d6b43}.orange{background:#8b4b08}.btn{display:inline-block;padding:7px 9px;border-radius:8px;background:#805f12;color:#fff;text-decoration:none;font-weight:900;font-size:12px;margin:2px}.empty{padding:30px;color:#9ba9bb}@media(max-width:900px){.kpis{grid-template-columns:1fr 1fr}.panel{overflow:auto}}
</style></head><body><header class="hero"><h1>Internal Website Leads</h1><p>Hostinger MySQL is the only CRM source of truth. No HubSpot. No Supabase.</p></header><main class="wrap">
<div class="kpis"><div class="kpi"><b><?=count($rows)?></b>Total</div><div class="kpi"><b><?=$hot?></b>Mark Priority</div><div class="kpi"><b><?=$drip?></b>Jessica Drip</div><div class="kpi"><b><?=$missing?></b>Need Phone</div><div class="kpi"><b>LIVE</b>Internal CRM</div></div>
<p><a class="btn" target="_blank" href="/lead-engine/goliath-v119-3-repair-lead.php?key=<?=h($key)?>&name=Sofia">Repair Sofia</a><a class="btn" target="_blank" href="/lead-engine/goliath-v119-3-orchestration-tick.php?key=<?=h($key)?>">Run Revenue Tick</a><a class="btn" href="/dashboard/contact-enrichment-center.php">Contact Enrichment</a></p>
<section class="panel"><?php if(!$rows):?><div class="empty">No internal website leads yet.</div><?php else:?><table><thead><tr><th>Score</th><th>Lead</th><th>Contact</th><th>Property / Intent</th><th>CRM</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $r):$bestPhone=$r['best_phone']?:$r['phone'];$bestEmail=$r['best_email']?:$r['email'];$digits=phone($bestPhone);?>
<tr><td><b style="font-size:24px;color:#f0cd73"><?=(int)$r['lead_score']?></b><br><span class="pill"><?=h($r['route'])?></span></td>
<td><strong><?=h($r['name']?:'Unknown')?></strong><br><small><?=h($r['created_at'])?></small></td>
<td><?=h($bestEmail?:'No email')?><br><?=h($bestPhone?:'No phone')?></td>
<td><?=h($r['address'])?><br><?=h($r['town'])?><br><span class="pill"><?=h($r['type'])?></span><br><?=h($r['message'])?></td>
<td><span class="pill green"><?=h($r['drip_status'])?></span><br><span class="pill orange"><?=h($r['contact_enrichment_status']?:$r['research_status'])?></span></td>
<td><?php if($digits):?><a class="btn" href="tel:+1<?=h($digits)?>">Call</a><a class="btn" href="sms:+1<?=h($digits)?>">Text</a><?php endif;?><?php if($bestEmail):?><a class="btn" href="mailto:<?=h($bestEmail)?>">Email</a><?php endif;?></td></tr>
<?php endforeach;?></tbody></table><?php endif;?></section></main></body></html>