<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function sb155d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $stage=$_POST['stage']??'';
  if($id && in_array($stage,['launch_today','generate_creative','improve_first','distribute','watch','pause'],true)){
    sb155d('PATCH','campaign_command_center?id=eq.'.rawurlencode($id),['command_stage'=>$stage,'updated_at'=>date('c')]);
    $msg='Campaign command updated.';
  }
}
$rows=sb155d('GET','campaign_command_center?select=*&status=eq.active&order=command_score.desc,created_at.desc&limit=300');
$briefs=sb155d('GET','campaign_command_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'launch'=>0,'creative'=>0,'improve'=>0,'watch'=>0,'blotato'=>0,'budget'=>0];
foreach($rows as $r){
  if(($r['command_stage']??'')==='launch_today'){$stats['launch']++;$stats['budget']+=(float)($r['recommended_budget']??0);}
  if(($r['command_stage']??'')==='generate_creative')$stats['creative']++;
  if(($r['command_stage']??'')==='improve_first')$stats['improve']++;
  if(($r['command_stage']??'')==='watch')$stats['watch']++;
  if(!empty($r['blotato_ready']))$stats['blotato']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V15.5 Campaign Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(7,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:26px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .34fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.score{font-size:30px;font-weight:900;color:#c8a96e}.launch_today{color:#087b30;font-weight:900}.generate_creative{color:#8a5a00;font-weight:900}.improve_first{color:#9a0000;font-weight:900}.badge{background:#111;color:#fff;border-radius:999px;padding:3px 7px;font-size:11px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V15.5 Campaign Command Center</div><div>Jessica's final V15 command layer: launch, improve, generate creative, distribute, or watch each campaign</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-campaign-command-center.php?key=<?=h($cronKey)?>">Build Campaign Command</a><a class="btn light" href="/dashboard/ad-launch-director.php">Ad Launch</a><a class="btn light" href="/dashboard/traffic-scaling-director.php">Traffic</a><a class="btn light" href="/dashboard/creative-intelligence-director.php">Creative</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['launch'])?></div>Launch</div><div class="kpi"><div class="n"><?=h($stats['creative'])?></div>Need Creative</div><div class="kpi"><div class="n"><?=h($stats['improve'])?></div>Improve</div><div class="kpi"><div class="n"><?=h($stats['watch'])?></div>Watch</div><div class="kpi"><div class="n"><?=h($stats['blotato'])?></div>Blotato Ready</div><div class="kpi"><div class="n"><?=money($stats['budget'])?></div>/day</div></section>
<div class="layout"><section class="panel"><h2>Today's Campaign Commands</h2><table><tr><th>Score</th><th>Campaign</th><th>Readiness</th><th>Command</th><th>Controls</th></tr><?php foreach($rows as $r):?><tr><td><div class="score"><?=h($r['command_score'])?></div><div class="<?=h($r['command_stage'])?>"><?=h($r['command_stage'])?></div><div class="muted"><?=money($r['recommended_budget'])?>/day</div></td><td><strong><?=h($r['campaign_name'])?></strong> <?php if(!empty($r['blotato_ready'])):?><span class="badge">BLOTATO</span><?php endif;?><div class="muted"><?=h($r['brand_pillar'])?> / <?=h($r['campaign_type'])?><br><?=h($r['target_town'])?></div></td><td class="muted">Copy: <?=!empty($r['copy_ready'])?'yes':'no'?><br>Landing: <?=!empty($r['landing_ready'])?'yes':'no'?><br>Distribution: <?=!empty($r['distribution_ready'])?'yes':'no'?><br>Image Needed: <?=!empty($r['image_needed'])?'yes':'no'?></td><td><strong><?=h($r['recommended_daily_action'])?></strong><div class="muted">Creative: <?=h($r['recommended_creative_request'])?><br><br>Distribution: <?=h($r['recommended_distribution_plan'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="stage" value="launch_today">Launch</button><button class="btn light" name="stage" value="generate_creative">Creative</button><button class="btn light" name="stage" value="improve_first">Improve</button><button class="btn light" name="stage" value="watch">Watch</button><button class="btn light" name="stage" value="pause">Pause</button></form></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Jessica Campaign Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run Build Campaign Command to create briefing.')?></pre></div></section></div>
</main></body></html>