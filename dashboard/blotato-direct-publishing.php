<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb163d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='settings'){
    foreach($_POST['settings']??[] as $k=>$v){
      sb163d('PATCH','blotato_distribution_settings?setting_key=eq.'.rawurlencode($k),['setting_value'=>$v,'updated_at'=>date('c')]);
    }
    $msg='Settings updated.';
  }
  if($action==='account'){
    sb163d('POST','blotato_accounts',[[
      'account_name'=>$_POST['account_name']??'',
      'platform'=>$_POST['platform']??'',
      'account_handle'=>$_POST['account_handle']??'',
      'blotato_account_id'=>$_POST['blotato_account_id']??'',
      'status'=>$_POST['status']??'active',
      'notes'=>$_POST['notes']??'',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
    $msg='Account added.';
  }
}
$settings=sb163d('GET','blotato_distribution_settings?select=*&order=setting_key.asc&limit=100');
$accounts=sb163d('GET','blotato_accounts?select=*&order=platform.asc&limit=100');
$logs=sb163d('GET','blotato_publish_log?select=*&order=created_at.desc&limit=100');
$queue=sb163d('GET','blotato_distribution_queue?select=*&approval_status=eq.approved&distribution_status=in.(queued,scheduled)&order=distribution_score.desc&limit=50');
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V16.3 Blotato Direct Publishing</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer;text-decoration:none;display:inline-block}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}input,select,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:12px;border-radius:10px;max-height:240px;overflow:auto}@media(max-width:1000px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V16.3 Blotato Direct Publishing</div><div>Credential/settings layer and safe publisher. Dry run by default.</div></div><main class="wrap"><?php if($msg):?><div class="panel"><div class="inner"><?=h($msg)?></div></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/run-blotato-publisher.php?key=<?=h($cronKey)?>">Run Publisher</a><a class="btn light" href="/dashboard/blotato-distribution-director.php">Distribution Queue</a><a class="btn light" href="/dashboard/creative-review-studio.php">Creative Review</a></p>
<div class="grid"><section class="panel"><h2>Blotato Settings</h2><div class="inner"><form method="post"><input type="hidden" name="action" value="settings"><?php foreach($settings as $s):?><label class="muted"><?=h($s['setting_key'])?><?=!empty($s['is_secret'])?' (secret)':''?></label><input name="settings[<?=h($s['setting_key'])?>]" value="<?=h($s['is_secret']?'':$s['setting_value'])?>" placeholder="<?=h($s['is_secret']?'Paste new value to update':'')?>"><div class="muted"><?=h($s['notes'])?></div><?php endforeach;?><button class="btn">Save Settings</button></form></div></section><section class="panel"><h2>Add Social Account</h2><div class="inner"><form method="post"><input type="hidden" name="action" value="account"><input name="account_name" placeholder="Account name"><select name="platform"><option>Instagram</option><option>Facebook</option><option>LinkedIn</option><option>TikTok</option><option>YouTube Shorts</option><option>Google Business Profile</option></select><input name="account_handle" placeholder="@handle"><input name="blotato_account_id" placeholder="Blotato account ID"><select name="status"><option value="active">active</option><option value="inactive">inactive</option></select><textarea name="notes" placeholder="Notes"></textarea><button class="btn">Add Account</button></form></div></section></div>
<section class="panel"><h2>Connected Accounts</h2><table><tr><th>Platform</th><th>Name</th><th>Handle</th><th>Blotato ID</th><th>Status</th></tr><?php foreach($accounts as $a):?><tr><td><?=h($a['platform'])?></td><td><?=h($a['account_name'])?></td><td><?=h($a['account_handle'])?></td><td><?=h($a['blotato_account_id'])?></td><td><?=h($a['status'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Approved Queue Ready For Publishing</h2><table><tr><th>Score</th><th>Title</th><th>Platforms</th><th>Caption</th></tr><?php foreach($queue as $q):?><tr><td><?=h($q['distribution_score'])?></td><td><?=h($q['distribution_title'])?></td><td><?=h(implode(', ',is_array($q['platforms']??null)?$q['platforms']:[]))?></td><td><?=h(substr($q['caption']??'',0,220))?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Publish Log</h2><table><tr><th>Date</th><th>Platform</th><th>Status</th><th>HTTP</th><th>Error / Response</th></tr><?php foreach($logs as $l):?><tr><td><?=h($l['created_at'])?></td><td><?=h($l['platform'])?></td><td><?=h($l['publish_status'])?></td><td><?=h($l['http_status'])?></td><td><pre><?=h($l['error_message'] ?: json_encode($l['response_payload'],JSON_PRETTY_PRINT))?></pre></td></tr><?php endforeach;?></table></section>
</main></body></html>