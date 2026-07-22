<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function sb131d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $action=$_POST['action']??'';
  if($id && in_array($action,['approve_email','approve_call','reject','review'],true)){
    $patch=['updated_at'=>date('c')];
    if($action==='approve_email') $patch+=['status'=>'approved','approved_contact'=>true,'approval_status'=>'approved','email_eligible'=>true,'recommended_action'=>'Approved for email/nurture.'];
    if($action==='approve_call') $patch+=['status'=>'approved','approved_contact'=>true,'approval_status'=>'approved','dnc_status'=>'clear','dnc_checked'=>true,'call_eligible'=>true,'email_eligible'=>true,'recommended_action'=>'Approved for Jessica call queue.'];
    if($action==='reject') $patch+=['status'=>'rejected','approval_status'=>'rejected','approved_contact'=>false,'call_eligible'=>false,'email_eligible'=>false];
    if($action==='review') $patch+=['status'=>'review','approval_status'=>'review'];
    sb131d('PATCH','contact_acquisition_candidates?id=eq.'.rawurlencode($id),$patch);
    $msg='Candidate updated.';
  }
}
$rows=sb131d('GET','contact_acquisition_candidates?select=*&order=contact_score.desc,created_at.desc&limit=300');
$briefs=sb131d('GET','contact_acquisition_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'approved'=>0,'call'=>0,'email'=>0,'dnc'=>0,'realtor'=>0,'review'=>0];
foreach($rows as $r){ if(!empty($r['approved_contact']))$stats['approved']++; if(!empty($r['call_eligible']))$stats['call']++; if(!empty($r['email_eligible']))$stats['email']++; if(!empty($r['dnc_match']))$stats['dnc']++; if(!empty($r['realtor_match']))$stats['realtor']++; if(($r['status']??'')==='review')$stats['review']++; }
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V13.1 Contact Acquisition</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(7,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.yes{color:#14783c;font-weight:900}.no{color:#9a6400;font-weight:900}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V13.1 Contact Acquisition Center</div><div>Turns research targets and owner records into approved contacts Jessica can act on</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-contact-acquisition.php?key=<?=h($cronKey)?>">Build Acquisition</a><a class="btn light" href="/dashboard/approved-contact-pipeline.php">Approved Pool</a><a class="btn light" href="/dashboard/listing-intelligence-center.php">V13 Listings</a><a class="btn light" href="/dashboard/queue-intelligence.php">Queue</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['call'])?></div>Callable</div><div class="kpi"><div class="n"><?=h($stats['email'])?></div>Email</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['dnc'])?></div>DNC</div><div class="kpi"><div class="n"><?=h($stats['realtor'])?></div>Realtors</div></section>
<div class="layout"><section class="panel"><h2>Contact Candidates</h2><table><tr><th>Score</th><th>Owner</th><th>Property</th><th>Compliance</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['contact_score'])?></strong><div class="muted"><?=h($r['priority_band'])?><br><?=h($r['status'])?></div></td><td><strong><?=h($r['owner_name']?:'Unnamed Owner')?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['source_table'])?></div></td><td><?=h($r['property_address'])?><div class="muted"><?=h($r['town'])?><br><?=money($r['estimated_value'])?> value<br><?=h($r['motivation'])?></div></td><td>DNC: <?=h($r['dnc_status'])?><br>Consent: <?=h($r['consent_status'])?><br>Approval: <?=h($r['approval_status'])?><br><span class="<?=!empty($r['call_eligible'])?'yes':'no'?>">Call: <?=h(!empty($r['call_eligible'])?'yes':'no')?></span><br>Realtor: <?=h(!empty($r['realtor_match'])?'yes':'no')?></td><td><?=h($r['recommended_action'])?><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="action" value="approve_call">Approve Call</button><button class="btn light" name="action" value="approve_email">Approve Email</button><button class="btn light" name="action" value="reject">Reject</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Acquisition Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Acquisition to create briefing.')?></pre></div></section></div>
</main></body></html>