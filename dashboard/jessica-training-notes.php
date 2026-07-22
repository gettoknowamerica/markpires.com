<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['id'])){
  $payload=['status'=>$_POST['status']??'new','priority'=>(int)($_POST['priority']??50),'updated_at'=>date('c')];
  $r=sb('PATCH','jessica_training_notes?id=eq.'.rawurlencode($_POST['id']),$payload);
  $msg=$r['ok']?'Training note updated.':'Update failed: '.$r['body'];
}
$rows=sb('GET','jessica_training_notes?select=*&order=status.asc,priority.desc,created_at.desc&limit=300');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Training Notes</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:Arial}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e}.wrap{max-width:1600px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;overflow:hidden;box-shadow:0 4px 18px #0001}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}th{background:#faf9f6;color:#777;font-size:11px;text-transform:uppercase}.btn{background:#c8a96e;border:0;border-radius:8px;padding:8px 10px;font-weight:900}select,input{padding:7px;border:1px solid #ddd;border-radius:8px}</style></head><body><section class="hero"><h1>Jessica Training Notes</h1><p>Open-ended Drive Mode questions Jessica logged for future skills and better answers.</p></section><main class="wrap"><?php if($msg):?><p><strong><?=h($msg)?></strong></p><?php endif;?><section class="panel"><table><tr><th>Priority</th><th>Status</th><th>User Asked</th><th>Jessica Response</th><th>Suggested Skill</th><th>Update</th></tr><?php foreach($rows['data'] as $r):?><tr><td><?=h($r['priority'])?></td><td><?=h($r['status'])?></td><td><?=h($r['user_message'])?></td><td><?=nl2br(h($r['jessica_response']))?></td><td><?=h($r['suggested_new_skill'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input name="priority" value="<?=h($r['priority'])?>" style="width:70px"><select name="status"><option>new</option><option <?=($r['status']==='planned'?'selected':'')?>>planned</option><option <?=($r['status']==='built'?'selected':'')?>>built</option><option <?=($r['status']==='ignore'?'selected':'')?>>ignore</option></select><button class="btn">Save</button></form></td></tr><?php endforeach;?></table></section></main></body></html>