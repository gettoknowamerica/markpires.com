<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb111d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id && in_array($action,['approved','queued','contacted','hot','dead','archived'],true)){
    $r=sb111d('PATCH','builder_developer_opportunities?id=eq.'.rawurlencode($id),['status'=>$action,'updated_at'=>date('c')]);
    $msg=$r['ok']?'Opportunity updated.':'Update failed.';
  }
  if($id && $action==='send_action'){
    $q=sb111d('GET','builder_developer_opportunities?select=*&id=eq.'.rawurlencode($id).'&limit=1');
    $o=$q['data'][0]??null;
    if($o){
      $payload=[[
        'related_type'=>'builder_developer_opportunity',
        'related_id'=>$o['id'],
        'action_type'=>'builder_opportunity_review',
        'priority'=>(int)$o['builder_score']>=100?'hot':'high',
        'name'=>$o['owner_name'],
        'phone'=>$o['phone'],
        'email'=>$o['email'],
        'address'=>$o['address'],
        'town'=>$o['town'],
        'source'=>'Builder Developer Radar',
        'recommended_action'=>'Review possible '.$o['opportunity_type'].' opportunity. '.$o['reason'],
        'status'=>'open',
        'due_at'=>date('c',strtotime('+4 hours')),
        'raw_payload'=>$o,
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $r=sb111d('POST','mark_action_queue',$payload);
      if($r['ok']){sb111d('PATCH','builder_developer_opportunities?id=eq.'.rawurlencode($id),['routed_to_action_queue'=>true,'updated_at'=>date('c')]);$msg='Sent to Mark Action Queue.';} else $msg='Action send failed: '.$r['body'];
    }
  }
}
$status=$_GET['status']??'review';
$ep=$status==='all'?'builder_developer_opportunities?select=*&order=builder_score.desc&limit=300':'builder_developer_opportunities?select=*&status=eq.'.rawurlencode($status).'&order=builder_score.desc&limit=300';
$rows=sb111d('GET',$ep)['data'];
$all=sb111d('GET','builder_developer_opportunities?select=status,builder_score,town,opportunity_type&limit=1000')['data'];
$stats=['review'=>0,'approved'=>0,'hot'=>0,'total'=>count($all)];$types=[];$towns=[];
foreach($all as $r){$s=$r['status']??'review';if(isset($stats[$s]))$stats[$s]++;if((int)($r['builder_score']??0)>=100)$stats['hot']++;$types[$r['opportunity_type']?:'unknown']=($types[$r['opportunity_type']?:'unknown']??0)+1;$towns[$r['town']?:'Unknown']=($towns[$r['town']?:'Unknown']??0)+1;}
arsort($types);arsort($towns);
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Builder Developer Radar V11.1</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.badge{border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.review{background:#e9f2ff;color:#174ea6}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .35fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Builder / Developer Radar V11.1</div><div>Land · teardown · subdivision · renovation opportunities</div></div><main class="wrap">
<?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/build-builder-radar.php?key=<?=h($cronKey)?>">Build Builder Radar</a><a class="btn light" href="?status=review">Review</a><a class="btn light" href="?status=approved">Approved</a><a class="btn light" href="?status=all">All</a><a class="btn light" href="/dashboard/command-center.php">Command Center</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['hot'])?></div>100+ Score</div></section>
<div class="layout"><section class="panel"><h2>Opportunities</h2><table><tr><th>Score</th><th>Owner / Property</th><th>Opportunity</th><th>Reason</th><th>Actions</th></tr><?php foreach($rows as $r):$score=(int)$r['builder_score'];$cls=$score>=100?'hot':($score>=85?'high':'review');?><tr><td><span class="badge <?=h($cls)?>"><?=h($score)?></span><div class="muted"><?=h($r['priority'])?><br><?=h($r['status'])?></div></td><td><strong><?=h($r['owner_name'])?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['address'])?><br><?=h($r['town'])?></div></td><td><strong><?=h($r['opportunity_type'])?></strong><div class="muted">Acres: <?=h($r['acreage'])?><br>Years: <?=h($r['years_owned'])?><br>Equity: $<?=h(number_format((float)$r['estimated_equity']))?></div></td><td><?=h($r['reason'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn gold" name="action" value="send_action">Send Action</button><button class="btn light" name="action" value="approved">Approve</button><button class="btn" name="action" value="contacted">Contacted</button><button class="btn light" name="action" value="archived">Archive</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Types</h2><table><tr><th>Type</th><th>Count</th></tr><?php foreach($types as $k=>$v):?><tr><td><?=h($k)?></td><td><?=h($v)?></td></tr><?php endforeach;?></table><h2>Towns</h2><table><tr><th>Town</th><th>Count</th></tr><?php foreach(array_slice($towns,0,20,true) as $k=>$v):?><tr><td><?=h($k)?></td><td><?=h($v)?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>