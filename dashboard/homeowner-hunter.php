<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbh10($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$status=$_POST['status']??'';
  if($id && in_array($status,['approved','queued','called','future_seller','hot','dead','skipped'],true)){
    $r=sbh10('PATCH','hunter_queue?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>date('c')]);
    $msg=$r['ok']?'Hunter item updated.':'Update failed: '.$r['body'];
  }
  if($id && $status==='send_to_mission'){
    $q=sbh10('GET','hunter_queue?select=*&id=eq.'.rawurlencode($id).'&limit=1');
    $row=$q['data'][0]??null;
    if($row){
      $payload=[['related_type'=>'hunter_queue','related_id'=>$row['id'],'homeowner_id'=>$row['homeowner_id'],'name'=>$row['owner_name'],'phone'=>$row['phone'],'email'=>$row['email'],'address'=>$row['address'],'town'=>$row['town'],'source'=>'Homeowner Hunter','lead_type'=>'hunter_homeowner','base_score'=>(int)$row['base_score'],'adaptive_score'=>(int)$row['adaptive_score'],'priority_score'=>(int)$row['hunter_score'],'mission_type'=>'HOMEOWNER_PROSPECT','suggested_action'=>'Jessica should call using the cold homeowner hunter script after DNC check.','confidence'=>(int)$row['hunter_score']>=100?'high':'medium','reason'=>$row['reason'],'assigned_to'=>'jessica','status'=>'pending','call_by'=>$row['call_by'],'raw_payload'=>$row,'created_at'=>date('c'),'updated_at'=>date('c')]];
      $r=sbh10('POST','jessica_priority_queue',$payload);
      if($r['ok']){sbh10('PATCH','hunter_queue?id=eq.'.rawurlencode($id),['status'=>'queued','updated_at'=>date('c')]);$msg='Sent to Jessica Mission Control.';} else $msg='Mission send failed: '.$r['body'];
    }
  }
}
$status=$_GET['status']??'review';
$ep=$status==='all'?'hunter_queue?select=*&order=hunter_score.desc&limit=300':'hunter_queue?select=*&status=eq.'.rawurlencode($status).'&order=hunter_score.desc&limit=300';
$rows=sbh10('GET',$ep)['data'];
$all=sbh10('GET','hunter_queue?select=status,hunter_score,town&limit=1000')['data'];
$counts=['review'=>0,'approved'=>0,'queued'=>0,'called'=>0,'future_seller'=>0,'hot'=>0,'dead'=>0];$towns=[];$hot=0;
foreach($all as $r){$s=$r['status']??'review';if(isset($counts[$s]))$counts[$s]++;if((int)($r['hunter_score']??0)>=100)$hot++;$t=$r['town']?:'Unknown';$towns[$t]=($towns[$t]??0)+1;}
arsort($towns);$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Homeowner Hunter V10.1</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.badge{border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.review{background:#e9f2ff;color:#174ea6}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .35fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Homeowner Hunter V10.1</div><div>Jessica starts selecting who to call · <a style="color:#fff" href="/dashboard/jessica-mission-control.php">Mission Control</a></div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?><p><a class="btn gold" target="_blank" href="/lead-engine/build-hunter-queue.php?key=<?=h($cronKey)?>">Build Hunter Queue</a><a class="btn light" href="?status=review">Review</a><a class="btn light" href="?status=queued">Queued</a><a class="btn light" href="?status=called">Called</a><a class="btn light" href="?status=all">All</a></p><section class="grid"><div class="kpi"><div class="n"><?=h($counts['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($counts['queued'])?></div>Queued</div><div class="kpi"><div class="n"><?=h($counts['called'])?></div>Called</div><div class="kpi"><div class="n"><?=h($counts['future_seller'])?></div>Future Sellers</div><div class="kpi"><div class="n"><?=h($hot)?></div>100+ Score</div></section><div class="layout"><section class="panel"><h2>Hunter Queue</h2><table><tr><th>Score</th><th>Homeowner</th><th>Property Signal</th><th>Reason</th><th>Actions</th></tr>
<?php foreach($rows as $r):$score=(int)$r['hunter_score'];$cls=$score>=100?'hot':($score>=85?'high':'review');?><tr><td><span class="badge <?=h($cls)?>"><?=h($score)?></span><div class="muted"><?=h($r['priority'])?><br><?=h($r['status'])?></div></td><td><strong><?=h($r['owner_name'])?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['address'])?><br><?=h($r['town'])?></div></td><td>Years: <?=h($r['years_owned'])?><br>Equity: $<?=h(number_format((float)$r['estimated_equity']))?><br><?=h($r['property_type'])?></td><td><?=h($r['reason'])?></td><td><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn gold" name="status" value="send_to_mission">Send to Jessica</button></form><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="status" value="approved">Approve</button></form><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="status" value="called">Called</button></form><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="status" value="skipped">Skip</button></form></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Town Hunter Count</h2><table><tr><th>Town</th><th>Targets</th></tr><?php foreach(array_slice($towns,0,20,true) as $town=>$count):?><tr><td><?=h($town)?></td><td><?=h($count)?></td></tr><?php endforeach;?></table></section></div></main></body></html>