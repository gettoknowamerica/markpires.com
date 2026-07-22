<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb106d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach($_POST['guardrail']??[] as $key=>$value){
    sb106d('PATCH','hunter_guardrails?guardrail_key=eq.'.rawurlencode($key),['guardrail_value'=>$value,'updated_at'=>date('c')]);
  }
  $msg='Guardrails updated.';
}
$guardrails=sb106d('GET','hunter_guardrails?select=*&order=guardrail_key.asc')['data'];
$runs=sb106d('GET','hunter_call_runs?select=*&order=created_at.desc&limit=30')['data'];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hunter Guardrails V10.6</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1350px;margin:auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.ok{background:#e6f7ec;color:#14783c;padding:12px;border-radius:12px}input{padding:8px;border:1px solid #ddd;border-radius:8px;width:180px}.muted{color:#777;font-size:13px}@media(max-width:900px){.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Hunter Guardrails V10.6</div><div>Safety switch · call limits · call windows · cron control</div></div><main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/run-hunter-cron.php?key=<?=h($cronKey)?>">Run Guarded Hunter Now</a><a class="btn light" target="_blank" href="/lead-engine/cron-master.php?key=<?=h($cronKey)?>">Run Master Cron</a><a class="btn light" href="/dashboard/autonomous-hunter-calling.php">Autonomous Hunter</a></p>
<section class="panel"><h2>Guardrails</h2><form method="post"><table><tr><th>Key</th><th>Value</th><th>Description</th></tr><?php foreach($guardrails as $g):?><tr><td><strong><?=h($g['guardrail_key'])?></strong></td><td><input name="guardrail[<?=h($g['guardrail_key'])?>]" value="<?=h($g['guardrail_value'])?>"></td><td><?=h($g['description'])?></td></tr><?php endforeach;?></table><div style="padding:16px"><button class="btn gold">Save Guardrails</button></div></form></section>
<section class="panel"><h2>Recent Hunter Call Runs</h2><table><tr><th>Time</th><th>Status</th><th>Attempted</th><th>Called</th><th>Skipped</th><th>Errors</th><th>Blocked</th></tr><?php foreach($runs as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['status'])?></td><td><?=h($r['attempted'])?></td><td><?=h($r['called'])?></td><td><?=h($r['skipped'])?></td><td><?=h($r['errors'])?></td><td class="muted"><?=h($r['blocked_reason'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>