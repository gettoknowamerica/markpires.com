<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';
  if($id){
    $approval=$_POST['approval_status']??'needs_review';
    $perm=$_POST['contact_permission_status']??'research_only';
    $patch=[
      'dnc_status'=>$_POST['dnc_status']??'not_checked',
      'realtor_status'=>$_POST['realtor_status']??'not_checked',
      'opt_out_status'=>$_POST['opt_out_status']??'not_checked',
      'contact_permission_status'=>$perm,
      'approval_status'=>$approval,
      'recommended_contact_method'=>$_POST['recommended_contact_method']??'research',
      'mark_notes'=>$_POST['mark_notes']??'',
      'approved_by'=>($approval==='approved'?'Mark':null),
      'approved_at'=>($approval==='approved'?date('c'):null),
      'updated_at'=>date('c')
    ];
    $r=sb('PATCH','owner_compliance_reviews?id=eq.'.rawurlencode($id),$patch);
    $msg=$r['ok']?'Review updated. Run builder to push approved items to contact queue.':'Update failed: '.$r['body'];
  }
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$reviews=sb('GET','owner_compliance_reviews?select=*&order=priority_score.desc,updated_at.desc&limit=500');
$approved=sb('GET','approved_owner_contact_queue?select=*&order=priority_score.desc,created_at.desc&limit=100');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Compliance Contact Approval</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:42px;margin:0}.wrap{max-width:1900px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;box-shadow:0 4px 18px #0001;overflow:hidden;margin-top:16px}.inner{padding:18px}.btn{background:#c8a96e;color:#111;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:900;display:inline-block;margin:3px;border:0;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{background:#faf9f6;color:#777;text-transform:uppercase;font-size:11px}.score{font-size:30px;font-weight:900;color:#c8a96e}.tag{display:inline-block;background:#111827;color:white;border-radius:99px;padding:4px 8px;font-size:11px}.muted{color:#666;font-size:12px}select,textarea,input{width:100%;box-sizing:border-box;padding:7px;border:1px solid #ddd;border-radius:8px;margin:4px 0 8px}</style></head><body>
<section class="hero"><h1>V20.3 Compliance + Contact Approval</h1><p>Human gate between Jessica research and real outreach.</p></section>
<main class="wrap">
<section class="panel"><div class="inner">
<a class="btn" href="/dashboard/owner-enrichment-engine.php">Owner Enrichment</a>
<a class="btn" target="_blank" href="/lead-engine/build-compliance-contact-approval.php?key=<?=h($key)?>">Run Compliance Builder</a>
<a class="btn" href="/dashboard/leads.php">Leads</a>
<?php if($msg):?><p><strong><?=h($msg)?></strong></p><?php endif;?>
</div></section>

<section class="panel"><div class="inner"><h2>Approved Contact Queue</h2></div>
<table><tr><th>Score</th><th>Owner</th><th>Contact</th><th>Method</th><th>Script Notes</th></tr>
<?php if($approved['ok']): foreach($approved['data'] as $a): ?>
<tr><td><div class="score"><?=h($a['priority_score'])?></div></td><td><strong><?=h($a['owner_name'])?></strong><br><?=h($a['property_address'])?><br><?=h($a['town'])?></td><td><?=h($a['possible_phone'])?><br><?=h($a['possible_email'])?><br><?=h($a['mailing_address'])?></td><td><span class="tag"><?=h($a['contact_method'])?></span><br><?=h($a['assigned_to'])?></td><td><?=h($a['script_notes'])?></td></tr>
<?php endforeach; endif;?>
</table></section>

<section class="panel"><div class="inner"><h2>Compliance Reviews</h2></div>
<table><tr><th>Score</th><th>Owner / Property</th><th>Jessica Reason</th><th>Compliance Controls</th></tr>
<?php if(!$reviews['ok']):?><tr><td colspan="4"><pre><?=h($reviews['body'])?></pre></td></tr><?php else: foreach($reviews['data'] as $r):?>
<tr>
<td><div class="score"><?=h($r['priority_score'])?></div><span class="muted">Seller <?=h($r['seller_signal_score'])?></span></td>
<td><strong><?=h($r['owner_name'])?></strong><br><?=h($r['property_address'])?><br><?=h($r['town'])?><br><span class="tag"><?=h($r['approval_status'])?></span></td>
<td><?=h($r['jessica_reason'])?><br><span class="muted">Phone: <?=h($r['possible_phone'])?> | Email: <?=h($r['possible_email'])?></span></td>
<td><form method="post">
<input type="hidden" name="id" value="<?=h($r['id'])?>">
<label>DNC</label><select name="dnc_status"><option>not_checked</option><option <?=($r['dnc_status']==='clear'?'selected':'')?>>clear</option><option <?=($r['dnc_status']==='blocked'?'selected':'')?>>blocked</option><option <?=($r['dnc_status']==='unknown'?'selected':'')?>>unknown</option></select>
<label>Realtor Exclusion</label><select name="realtor_status"><option>not_checked</option><option <?=($r['realtor_status']==='clear'?'selected':'')?>>clear</option><option <?=($r['realtor_status']==='blocked'?'selected':'')?>>blocked</option><option <?=($r['realtor_status']==='unknown'?'selected':'')?>>unknown</option></select>
<label>Opt-Out</label><select name="opt_out_status"><option>not_checked</option><option <?=($r['opt_out_status']==='clear'?'selected':'')?>>clear</option><option <?=($r['opt_out_status']==='opted_out'?'selected':'')?>>opted_out</option></select>
<label>Permission</label><select name="contact_permission_status"><option>research_only</option><option <?=($r['contact_permission_status']==='mail_only'?'selected':'')?>>mail_only</option><option <?=($r['contact_permission_status']==='approved_for_mark_call'?'selected':'')?>>approved_for_mark_call</option><option <?=($r['contact_permission_status']==='approved_for_jessica_followup'?'selected':'')?>>approved_for_jessica_followup</option><option <?=($r['contact_permission_status']==='blocked'?'selected':'')?>>blocked</option></select>
<label>Approval</label><select name="approval_status"><option>needs_review</option><option <?=($r['approval_status']==='approved'?'selected':'')?>>approved</option><option <?=($r['approval_status']==='rejected'?'selected':'')?>>rejected</option><option <?=($r['approval_status']==='blocked'?'selected':'')?>>blocked</option></select>
<label>Contact Method</label><select name="recommended_contact_method"><option>research</option><option <?=($r['recommended_contact_method']==='mail'?'selected':'')?>>mail</option><option <?=($r['recommended_contact_method']==='mark_call'?'selected':'')?>>mark_call</option><option <?=($r['recommended_contact_method']==='jessica_followup'?'selected':'')?>>jessica_followup</option></select>
<textarea name="mark_notes" rows="2" placeholder="Mark notes"><?=h($r['mark_notes'])?></textarea>
<button class="btn">Save Review</button>
</form></td>
</tr>
<?php endforeach; endif;?>
</table></section>
</main></body></html>