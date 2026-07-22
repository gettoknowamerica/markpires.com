<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb113d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id && $action==='save'){
    $r=sb113d('PATCH','builder_intro_outreach?id=eq.'.rawurlencode($id),[
      'intro_subject'=>$_POST['intro_subject']??'',
      'intro_body'=>$_POST['intro_body']??'',
      'sms_body'=>$_POST['sms_body']??'',
      'updated_at'=>date('c')
    ]);
    $msg=$r['ok']?'Draft saved.':'Save failed.';
  }
  if($id && in_array($action,['approved','skipped','draft'],true)){
    $r=sb113d('PATCH','builder_intro_outreach?id=eq.'.rawurlencode($id),['status'=>$action,'updated_at'=>date('c')]);
    $msg=$r['ok']?'Status updated.':'Update failed.';
  }
}
$status=$_GET['status']??'draft';
$rows=sb113d('GET',$status==='all'?'builder_intro_outreach?select=*&order=created_at.desc&limit=200':'builder_intro_outreach?select=*&status=eq.'.rawurlencode($status).'&order=match_score.desc&limit=200')['data'];
$all=sb113d('GET','builder_intro_outreach?select=status,email_sent,sms_sent&limit=1000')['data'];
$stats=['draft'=>0,'approved'=>0,'sent'=>0,'error'=>0,'total'=>count($all)];
foreach($all as $r){$s=$r['status']??'draft';if(isset($stats[$s]))$stats[$s]++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Builder Intro Outreach V11.3</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}textarea,input{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px 0;font-family:inherit}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Builder Intro Outreach V11.3</div><div>Review, approve, and send builder/developer intro messages</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/build-builder-intros.php?key=<?=h($cronKey)?>">Build Intro Drafts</a><a class="btn light" target="_blank" href="/lead-engine/send-builder-intros.php?key=<?=h($cronKey)?>&limit=5">Send Approved Email</a><a class="btn light" target="_blank" href="/lead-engine/send-builder-intros.php?key=<?=h($cronKey)?>&limit=5&sms=1">Send Approved Email + SMS</a><a class="btn light" href="/dashboard/builder-matchmaker.php">Matchmaker</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['draft'])?></div>Draft</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['sent'])?></div>Sent</div><div class="kpi"><div class="n"><?=h($stats['error'])?></div>Errors</div></section>
<p><a class="btn light" href="?status=draft">Draft</a><a class="btn light" href="?status=approved">Approved</a><a class="btn light" href="?status=sent">Sent</a><a class="btn light" href="?status=all">All</a></p>
<section class="panel"><h2>Intro Drafts</h2><table><tr><th>Builder</th><th>Opportunity</th><th>Draft</th><th>Actions</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['builder_name'])?></strong><div class="muted"><?=h($r['company'])?><br><?=h($r['builder_email'])?><br><?=h($r['builder_phone'])?><br>Score <?=h($r['match_score'])?></div></td><td><?=h($r['opportunity_address'])?><div class="muted"><?=h($r['opportunity_town'])?><br><?=h($r['opportunity_type'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input name="intro_subject" value="<?=h($r['intro_subject'])?>"><textarea name="intro_body" rows="7"><?=h($r['intro_body'])?></textarea><textarea name="sms_body" rows="3"><?=h($r['sms_body'])?></textarea><button class="btn gold" name="action" value="save">Save</button></form></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn gold" name="action" value="approved">Approve</button><button class="btn light" name="action" value="draft">Back to Draft</button><button class="btn" name="action" value="skipped">Skip</button></form><div class="muted">Status: <?=h($r['status'])?><br>Email: <?=h($r['email_sent']?'yes':'no')?><br>SMS: <?=h($r['sms_sent']?'yes':'no')?></div></td></tr><?php endforeach;?></table></section>
</main></body></html>