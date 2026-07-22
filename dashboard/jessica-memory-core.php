<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
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
if($_SERVER['REQUEST_METHOD']==='POST'){
 $payload=[[
  'memory_type'=>$_POST['memory_type']??'preference','category'=>$_POST['category']??'executive','title'=>$_POST['title']??'',
  'memory_text'=>$_POST['memory_text']??'','importance'=>(int)($_POST['importance']??70),'status'=>'active','source'=>'manual','created_at'=>date('c'),'updated_at'=>date('c')
 ]];
 if(trim($payload[0]['memory_text'])){$r=sb('POST','jessica_long_term_memory',$payload);$msg=$r['ok']?'Memory saved.':'Save failed: '.$r['body'];}
}
$mem=sb('GET','jessica_long_term_memory?select=*&status=eq.active&order=importance.desc,created_at.desc&limit=300');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Memory Core</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:Arial}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e}.wrap{max-width:1500px;margin:auto;padding:20px}.grid{display:grid;grid-template-columns:380px 1fr;gap:16px}.panel{background:white;border-radius:18px;padding:20px;box-shadow:0 4px 18px #0001}input,textarea,select{width:100%;box-sizing:border-box;padding:10px;margin:6px 0 12px;border:1px solid #ddd;border-radius:8px}.btn{background:#c8a96e;border:0;border-radius:10px;padding:12px 16px;font-weight:900}.card{border-bottom:1px solid #eee;padding:12px 0}.score{font-size:24px;color:#c8a96e;font-weight:900}@media(max-width:900px){.grid{grid-template-columns:1fr}}</style></head><body>
<section class="hero"><h1>V20.8 Jessica Memory Core</h1><p>Long-term preferences, rules, decisions, strategy, and goals.</p></section>
<main class="wrap"><?php if($msg):?><p><strong><?=h($msg)?></strong></p><?php endif;?><div class="grid"><section class="panel"><h2>Add Memory</h2><form method="post"><label>Type</label><select name="memory_type"><option>preference</option><option>decision</option><option>strategy</option><option>correction</option><option>rule</option><option>goal</option></select><label>Category</label><input name="category" value="executive"><label>Title</label><input name="title"><label>Memory</label><textarea name="memory_text" rows="6"></textarea><label>Importance</label><input name="importance" value="80"><button class="btn">Save Memory</button></form></section><section class="panel"><h2>Active Memory</h2><?php foreach($mem['data'] as $m):?><div class="card"><div class="score"><?=h($m['importance'])?></div><strong><?=h($m['title'])?></strong><br><small><?=h($m['memory_type'])?> / <?=h($m['category'])?></small><p><?=nl2br(h($m['memory_text']))?></p></div><?php endforeach;?></section></div></main></body></html>