<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb171d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['active','completed','deferred','blocked'],true)){
    sb171d('PATCH','approved_contact_pool?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>date('c')]);
    $msg='Contact marked '.$status.'.';
  }
}
$rows=sb171d('GET','approved_contact_pool?select=*&status=eq.active&order=contact_score.desc,created_at.desc&limit=300');
$briefs=sb171d('GET','approved_contact_pipeline_briefings?select=*&order=created_at.desc&limit=10');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'call'=>0,'sms'=>0,'email'=>0,'review'=>0];
foreach($rows as $r){ if(!empty($r['call_eligible']))$stats['call']++; if(!empty($r['sms_eligible']))$stats['sms']++; if(!empty($r['email_eligible']))$stats['email']++; if(($r['recommended_channel']??'')==='review')$stats['review']++; }
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Approved Contact Pipeline V12.17</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .42fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.call{color:#14783c;font-weight:900}.email{color:#174ea6;font-weight:900}.review{color:#9a6400;font-weight:900}.do_not_call{color:#9b1c1c;font-weight:900}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Approved Contact Pipeline V12.17</div><div>Safe source-to-contact bridge for Jessica calls, texts, emails, and review queue</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-approved-contact-pipeline.php?key=<?=h($cronKey)?>">Build Approved Pool</a><a class="btn light" href="/dashboard/queue-intelligence.php">Queue</a><a class="btn light" href="/dashboard/compliant-lead-imports.php">Imports</a><a class="btn light" href="/dashboard/conversation-intelligence.php">Conversation Intel</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['call'])?></div>Call</div><div class="kpi"><div class="n"><?=h($stats['sms'])?></div>SMS</div><div class="kpi"><div class="n"><?=h($stats['email'])?></div>Email</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div></section>
<div class="layout"><section class="panel"><h2>Approved Contact Pool</h2><table><tr><th>Score</th><th>Contact</th><th>Channel</th><th>Compliance</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['contact_score'])?></strong><div class="muted"><?=h($r['priority_band'])?></div></td><td><strong><?=h($r['name']?:'Unnamed')?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['town'])?> <?=h($r['market'])?></div></td><td class="<?=h($r['recommended_channel'])?>"><?=h($r['recommended_channel'])?><div class="muted"><?=h($r['lead_type'])?><br><?=h($r['timeline'])?></div></td><td><?=h($r['approval_status'])?> / <?=h($r['dnc_status'])?> / <?=h($r['consent_status'])?><div class="muted">Call: <?=h(!empty($r['call_eligible'])?'yes':'no')?> · Realtor: <?=h(!empty($r['realtor_flag'])?'yes':'no')?></div></td><td><?=h($r['recommended_action'])?><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="status" value="completed">Done</button><button class="btn light" name="status" value="deferred">Defer</button><button class="btn light" name="status" value="blocked">Block</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Pipeline Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Approved Pool to create briefing.')?></pre></div></section></div>
</main></body></html>