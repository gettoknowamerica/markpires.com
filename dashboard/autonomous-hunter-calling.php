<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb104d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id && $action==='approve'){
    $r=sb104d('PATCH','hunter_queue?id=eq.'.rawurlencode($id),['approved_by_mark'=>true,'approved_at'=>date('c'),'status'=>'approved','call_mode'=>'approved_for_hunter','updated_at'=>date('c')]);
    $msg=$r['ok']?'Approved for Jessica hunter calling.':'Approve failed.';
  }
  if($id && $action==='unapprove'){
    $r=sb104d('PATCH','hunter_queue?id=eq.'.rawurlencode($id),['approved_by_mark'=>false,'status'=>'review','call_mode'=>'manual_review','updated_at'=>date('c')]);
    $msg=$r['ok']?'Removed approval.':'Update failed.';
  }
}
$approved=sb104d('GET','hunter_queue?select=*&status=in.(approved,queued)&approved_by_mark=eq.true&order=hunter_score.desc&limit=100')['data'];
$review=sb104d('GET','hunter_queue?select=*&status=eq.review&order=hunter_score.desc&limit=100')['data'];
$runs=sb104d('GET','hunter_call_runs?select=*&order=created_at.desc&limit=20')['data'];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$retellReady=defined('RETELL_API_KEY')&&RETELL_API_KEY&&defined('RETELL_FROM_NUMBER')&&RETELL_FROM_NUMBER;
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Autonomous Hunter Calling V10.4</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.ok{background:#e6f7ec;color:#14783c;padding:12px;border-radius:12px}.bad{background:#ffeaea;color:#9b1c1c;padding:12px;border-radius:12px}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Autonomous Hunter Calling V10.4</div><div>Approved homeowner targets → DNC safety → Retell outbound call → learning loop</div></div><main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?>
<div class="<?=$retellReady?'ok':'bad'?>">Retell outbound config: <?=$retellReady?'READY':'MISSING RETELL_API_KEY or RETELL_FROM_NUMBER'?></div>
<p><a class="btn gold" target="_blank" href="/lead-engine/run-hunter-calls.php?key=<?=h($cronKey)?>&max=5">Run 5 Approved Hunter Calls</a> <a class="btn light" href="/dashboard/homeowner-hunter.php">Hunter Queue</a> <a class="btn light" href="/dashboard/hunter-learning.php">Hunter Learning</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h(count($review))?></div>Need Approval</div><div class="kpi"><div class="n"><?=h(count($approved))?></div>Approved</div><div class="kpi"><div class="n"><?=h(count($runs))?></div>Recent Runs</div></section>
<div class="layout"><section class="panel"><h2>Review / Approve Targets</h2><table><tr><th>Score</th><th>Homeowner</th><th>Reason</th><th>Action</th></tr><?php foreach($review as $r):?><tr><td><strong><?=h($r['hunter_score'])?></strong></td><td><?=h($r['owner_name'])?><div class="muted"><?=h($r['phone'])?><br><?=h($r['address'])?><br><?=h($r['town'])?></div></td><td><?=h($r['reason'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn gold" name="action" value="approve">Approve</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Approved For Jessica</h2><table><tr><th>Score</th><th>Homeowner</th><th>Status</th><th>Action</th></tr><?php foreach($approved as $r):?><tr><td><strong><?=h($r['hunter_score'])?></strong></td><td><?=h($r['owner_name'])?><div class="muted"><?=h($r['phone'])?><br><?=h($r['town'])?></div></td><td><?=h($r['status'])?><br><span class="muted"><?=h($r['call_mode'])?></span></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="action" value="unapprove">Remove</button></form></td></tr><?php endforeach;?></table></section></div>
<section class="panel"><h2>Recent Hunter Call Runs</h2><table><tr><th>Time</th><th>Attempted</th><th>Called</th><th>Skipped</th><th>Errors</th></tr><?php foreach($runs as $run):?><tr><td><?=h($run['created_at'])?></td><td><?=h($run['attempted'])?></td><td><?=h($run['called'])?></td><td><?=h($run['skipped'])?></td><td><?=h($run['errors'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>