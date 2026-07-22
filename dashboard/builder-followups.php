<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb116d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id && $action==='done'){
    $r=sb116d('PATCH','builder_followup_queue?id=eq.'.rawurlencode($id),['status'=>'done','completed_at'=>date('c'),'notes'=>$_POST['notes']??'','updated_at'=>date('c')]);
    $msg=$r['ok']?'Follow-up completed.':'Update failed.';
  } elseif($id && $action==='snooze'){
    $r=sb116d('PATCH','builder_followup_queue?id=eq.'.rawurlencode($id),['status'=>'snoozed','due_at'=>date('c',strtotime('+7 days 10:00')),'notes'=>$_POST['notes']??'','updated_at'=>date('c')]);
    $msg=$r['ok']?'Follow-up snoozed 7 days.':'Update failed.';
  } elseif($id && $action==='skip'){
    $r=sb116d('PATCH','builder_followup_queue?id=eq.'.rawurlencode($id),['status'=>'skipped','notes'=>$_POST['notes']??'','updated_at'=>date('c')]);
    $msg=$r['ok']?'Follow-up skipped.':'Update failed.';
  }
}
$view=$_GET['view']??'due';
if($view==='all')$ep='builder_followup_queue?select=*&order=due_at.asc&limit=300';
elseif($view==='upcoming')$ep='builder_followup_queue?select=*&status=eq.queued&due_at=gt.'.rawurlencode(date('c')).'&order=due_at.asc&limit=300';
else $ep='builder_followup_queue?select=*&status=eq.queued&due_at=lte.'.rawurlencode(date('c',strtotime('+24 hours'))).'&order=priority.desc,due_at.asc&limit=300';
$rows=sb116d('GET',$ep)['data'];
$all=sb116d('GET','builder_followup_queue?select=status,priority,due_at&limit=1000')['data'];
$stats=['queued'=>0,'done'=>0,'overdue'=>0,'hot'=>0,'total'=>count($all)];
foreach($all as $r){$s=$r['status']??'queued';if(isset($stats[$s]))$stats[$s]++;if(($r['priority']??'')==='hot')$stats['hot']++;if(($r['status']??'')==='queued'&&!empty($r['due_at'])&&strtotime($r['due_at'])<time())$stats['overdue']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Builder Followups V11.6</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}textarea{width:95%;padding:8px;border:1px solid #ddd;border-radius:8px}.muted{color:#777;font-size:13px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Builder Follow-Ups V11.6</div><div>Due, overdue, and upcoming builder/developer follow-ups</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/build-builder-followups.php?key=<?=h($cronKey)?>">Build Followups</a><a class="btn light" href="?view=due">Due</a><a class="btn light" href="?view=upcoming">Upcoming</a><a class="btn light" href="?view=all">All</a><a class="btn light" href="/dashboard/builder-performance.php">Builder Performance</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['queued'])?></div>Queued</div><div class="kpi"><div class="n"><?=h($stats['overdue'])?></div>Overdue</div><div class="kpi"><div class="n"><?=h($stats['hot'])?></div>Hot</div><div class="kpi"><div class="n"><?=h($stats['done'])?></div>Done</div></section>
<section class="panel"><h2>Follow-Up Queue</h2><table><tr><th>Due</th><th>Builder</th><th>Opportunity</th><th>Action</th><th>Complete</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['priority'])?></strong><div class="muted"><?=h($r['due_at'])?><br><?=h($r['status'])?></div></td><td><strong><?=h($r['builder_name'])?></strong><div class="muted"><?=h($r['company'])?><br><?=h($r['phone'])?><br><?=h($r['email'])?></div></td><td><?=h($r['opportunity_address'])?><div class="muted"><?=h($r['opportunity_town'])?><br><?=h($r['opportunity_type'])?></div></td><td><strong><?=h($r['subject'])?></strong><br><?=h($r['recommended_action'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><textarea name="notes" rows="2" placeholder="Notes"></textarea><br><button class="btn gold" name="action" value="done">Done</button><button class="btn light" name="action" value="snooze">Snooze</button><button class="btn" name="action" value="skip">Skip</button></form></td></tr><?php endforeach;?></table></section>
</main></body></html>