<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb144cd($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['open','completed','archived'],true)){
    sb144cd('PATCH','executive_command_actions?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>date('c'),'completed_at'=>$status==='completed'?date('c'):null]);
    $msg='Action updated.';
  }
}
$sessions=sb144cd('GET','executive_command_sessions?select=*&order=created_at.desc&limit=20');
$actions=sb144cd('GET','executive_command_actions?select=*&status=eq.open&order=priority_score.desc,created_at.desc&limit=100');
$briefs=sb144cd('GET','executive_command_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$latest=$sessions[0]??[];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V14.4B Executive Command</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .36fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.score{font-size:22px;font-weight:900;color:#c8a96e}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">V14.4B Executive Command Mode</div><div>Secret phrase: timetomakethedonuts — Jessica becomes Mark’s growth, SEO/AEO, acquisition, and listing strategy director</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-executive-command.php?key=<?=h($cronKey)?>">Run Executive Command</a><a class="btn light" target="_blank" href="/lead-engine/build-executive-command.php?key=<?=h($cronKey)?>&focus=seo_aeo">SEO/AEO Focus</a><a class="btn light" target="_blank" href="/lead-engine/build-executive-command.php?key=<?=h($cronKey)?>&focus=seller_acquisition">Seller Acquisition Focus</a><a class="btn light" href="/dashboard/jessica-master-control.php">Master</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h(count($sessions))?></div>Sessions</div><div class="kpi"><div class="n"><?=h(count($actions))?></div>Open Actions</div><div class="kpi"><div class="n"><?=h($brief['top_market']??'—')?></div>Top Market</div><div class="kpi"><div class="n"><?=h($brief['top_lead']??'—')?></div>Top Lead</div></section>
<div class="layout"><section class="panel"><h2>Latest Strategy</h2><div style="padding:16px"><pre><?=h($latest['strategy_text']??'Run Executive Command to generate strategy.')?></pre></div></section><section class="panel"><h2>Open Executive Actions</h2><table><tr><th>Score</th><th>Action</th></tr><?php foreach($actions as $a):?><tr><td><div class="score"><?=h($a['priority_score'])?></div></td><td><strong><?=h($a['action_title'])?></strong><div class="muted"><?=h($a['action_category'])?><br><?=h($a['action_details'])?></div><form method="post"><input type="hidden" name="id" value="<?=h($a['id'])?>"><button class="btn" name="status" value="completed">Done</button><button class="btn light" name="status" value="archived">Archive</button></form></td></tr><?php endforeach;?></table><h2>Command Phrase</h2><div style="padding:16px"><pre>timetomakethedonuts</pre></div></section></div>
</main><?php goliath_ui_close(); ?></body></html>