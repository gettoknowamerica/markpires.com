<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'data'=>is_array($d)?$d:[],'body'=>$b,'http'=>$h];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['form_type']??'')==='budget'){
    $id=$_POST['id']??'';
    $patch=[
      'total_daily_budget'=>(float)($_POST['total_daily_budget']??25),
      'total_monthly_budget'=>(float)($_POST['total_monthly_budget']??500),
      'max_daily_campaign_budget'=>(float)($_POST['max_daily_campaign_budget']??25),
      'max_budget_increase_percent'=>(int)($_POST['max_budget_increase_percent']??20),
      'require_human_approval'=>!empty($_POST['require_human_approval']),
      'allow_auto_pause'=>!empty($_POST['allow_auto_pause']),
      'allow_auto_scale'=>!empty($_POST['allow_auto_scale']),
      'notes'=>$_POST['notes']??'',
      'updated_at'=>date('c')
    ];
    sb('PATCH','advertising_budget_controls?id=eq.'.rawurlencode($id),$patch);
    $msg='Budget controls updated.';
  } elseif(($_POST['form_type']??'')==='account'){
    $id=$_POST['id']??'';
    sb('PATCH','advertising_accounts?id=eq.'.rawurlencode($id),[
      'account_name'=>$_POST['account_name']??'',
      'account_id'=>$_POST['account_id']??'',
      'status'=>$_POST['status']??'needs_connection',
      'api_key_last4'=>$_POST['api_key_last4']??'',
      'notes'=>$_POST['notes']??'',
      'updated_at'=>date('c')
    ]);
    $msg='Account updated.';
  } elseif(($_POST['form_type']??'')==='campaign'){
    $id=$_POST['id']??'';
    $patch=[
      'campaign_name'=>$_POST['campaign_name']??'',
      'provider'=>$_POST['provider']??'meta',
      'campaign_type'=>$_POST['campaign_type']??'seller_lead',
      'town'=>$_POST['town']??'',
      'status'=>$_POST['status']??'draft',
      'daily_budget'=>(float)($_POST['daily_budget']??10),
      'total_budget'=>(float)($_POST['total_budget']??100),
      'target_audience'=>$_POST['target_audience']??'',
      'offer'=>$_POST['offer']??'',
      'landing_page_url'=>$_POST['landing_page_url']??'',
      'primary_text'=>$_POST['primary_text']??'',
      'headline'=>$_POST['headline']??'',
      'cta'=>$_POST['cta']??'',
      'approval_status'=>$_POST['approval_status']??'needs_review',
      'updated_at'=>date('c')
    ];
    sb('PATCH','advertising_campaigns?id=eq.'.rawurlencode($id),$patch);
    $msg='Campaign updated.';
  } elseif(($_POST['form_type']??'')==='action'){
    $id=$_POST['id']??'';
    sb('PATCH','advertising_actions?id=eq.'.rawurlencode($id),[
      'action_status'=>$_POST['action_status']??'recommended',
      'human_decision'=>$_POST['human_decision']??'',
      'updated_at'=>date('c')
    ]);
    $msg='Action updated.';
  }
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$accounts=sb('GET','advertising_accounts?select=*&order=provider.asc')['data'];
$budget=sb('GET','advertising_budget_controls?select=*&order=created_at.desc&limit=1')['data'][0]??[];
$campaigns=sb('GET','advertising_campaigns?select=*&order=command_score.desc,created_at.desc&limit=200')['data'];
$actions=sb('GET','advertising_actions?select=*&order=created_at.desc&limit=100')['data'];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V18.3 Advertising Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:linear-gradient(135deg,#111827,#0b1020);color:white;padding:34px 24px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:44px;margin:0 0 8px}.wrap{max-width:1900px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{background:#fff;border-radius:18px;box-shadow:0 3px 16px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:18px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 12px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;text-decoration:none;display:inline-block;cursor:pointer}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}input,select,textarea{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0 8px}.score{font-size:30px;font-weight:900;color:#c8a96e}.muted{color:#777;font-size:13px}.tag{display:inline-block;background:#111827;color:white;border-radius:999px;padding:4px 8px;font-size:11px}.danger{background:#7f1d1d}.warn{background:#92400e}.ok{background:#14532d}.switches{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.switches label{background:#f5f3ef;border-radius:8px;padding:8px;font-size:12px}@media(max-width:1000px){.grid{grid-template-columns:1fr}.wrap{padding:14px}.switches{grid-template-columns:1fr}}</style></head><body>
<section class="hero"><h1>V18.3 Advertising Command Center</h1><div>Meta, Google, YouTube, budget controls, campaign approvals, and Jessica's scale / pause / optimize logic.</div></section>
<main class="wrap"><p><a class="btn" target="_blank" href="/lead-engine/build-advertising-command-center.php?key=<?=h($key)?>">Run Advertising Command</a><a class="btn light" href="/commandcenter.php">Goliath OS</a><a class="btn light" href="/dashboard/campaign-command-center.php">Campaign Command</a><a class="btn light" href="/dashboard/traffic-scaling-director.php">Traffic Scaling</a></p><?php if($msg):?><div class="panel"><div class="inner"><?=h($msg)?></div></div><?php endif;?>

<div class="grid">
<section class="panel"><h2>Budget Safety Controls</h2><div class="inner"><form method="post"><input type="hidden" name="form_type" value="budget"><input type="hidden" name="id" value="<?=h($budget['id']??'')?>"><label>Total Daily Budget</label><input name="total_daily_budget" value="<?=h($budget['total_daily_budget']??25)?>"><label>Total Monthly Budget</label><input name="total_monthly_budget" value="<?=h($budget['total_monthly_budget']??500)?>"><label>Max Daily Campaign Budget</label><input name="max_daily_campaign_budget" value="<?=h($budget['max_daily_campaign_budget']??25)?>"><label>Max Budget Increase %</label><input name="max_budget_increase_percent" value="<?=h($budget['max_budget_increase_percent']??20)?>"><div class="switches"><label><input type="checkbox" name="require_human_approval" <?=!empty($budget['require_human_approval'])?'checked':''?>> Require approval</label><label><input type="checkbox" name="allow_auto_pause" <?=!empty($budget['allow_auto_pause'])?'checked':''?>> Allow auto-pause</label><label><input type="checkbox" name="allow_auto_scale" <?=!empty($budget['allow_auto_scale'])?'checked':''?>> Allow auto-scale</label></div><textarea name="notes" rows="3"><?=h($budget['notes']??'')?></textarea><button class="btn">Save Budget Controls</button></form></div></section>

<section class="panel"><h2>Provider Connections</h2><table><tr><th>Provider</th><th>Connection</th></tr><?php foreach($accounts as $a):?><tr><td><strong><?=h($a['provider'])?></strong><br><span class="tag <?=($a['status']==='connected'?'ok':'warn')?>"><?=h($a['status'])?></span></td><td><form method="post"><input type="hidden" name="form_type" value="account"><input type="hidden" name="id" value="<?=h($a['id'])?>"><input name="account_name" value="<?=h($a['account_name'])?>" placeholder="Account name"><input name="account_id" value="<?=h($a['account_id'])?>" placeholder="Account ID"><select name="status"><option>needs_connection</option><option <?=($a['status']==='connected'?'selected':'')?>>connected</option><option <?=($a['status']==='paused'?'selected':'')?>>paused</option><option <?=($a['status']==='error'?'selected':'')?>>error</option></select><input name="api_key_last4" value="<?=h($a['api_key_last4'])?>" placeholder="Key last 4 only"><textarea name="notes" rows="2"><?=h($a['notes'])?></textarea><button class="btn">Save</button></form></td></tr><?php endforeach;?></table></section>
</div>

<section class="panel"><h2>Campaign Command Table</h2><table><tr><th>Score</th><th>Campaign</th><th>Budget / Metrics</th><th>Creative / Copy</th><th>Human Controls</th></tr><?php foreach($campaigns as $c):?><tr><td><div class="score"><?=h($c['command_score'])?></div><div class="muted">ROI <?=h($c['roi_score'])?><br>CPL $<?=h($c['cpl'])?><br>CTR <?=h($c['ctr'])?>%</div></td><td><strong><?=h($c['campaign_name'])?></strong><div class="muted"><?=h($c['provider'])?> / <?=h($c['campaign_type'])?><br><?=h($c['town'])?><br><span class="tag"><?=h($c['status'])?></span></div><p><?=h($c['jessica_recommendation'])?></p></td><td>Daily: $<?=h($c['daily_budget'])?><br>Total: $<?=h($c['total_budget'])?><br>Spend: $<?=h($c['spend'])?><br>Leads: <?=h($c['leads'])?><br>Appointments: <?=h($c['appointments'])?><br>Projected: $<?=h($c['projected_commission'])?></td><td><strong><?=h($c['headline'])?></strong><p><?=h($c['primary_text'])?></p><div class="muted"><?=h($c['creative_brief'])?></div></td><td><form method="post"><input type="hidden" name="form_type" value="campaign"><input type="hidden" name="id" value="<?=h($c['id'])?>"><input name="campaign_name" value="<?=h($c['campaign_name'])?>"><select name="provider"><option>meta</option><option <?=($c['provider']==='google_ads'?'selected':'')?>>google_ads</option><option <?=($c['provider']==='youtube'?'selected':'')?>>youtube</option><option <?=($c['provider']==='google_business_profile'?'selected':'')?>>google_business_profile</option></select><input name="campaign_type" value="<?=h($c['campaign_type'])?>"><input name="town" value="<?=h($c['town'])?>"><select name="status"><option>draft</option><option <?=($c['status']==='needs_review'?'selected':'')?>>needs_review</option><option <?=($c['status']==='approved'?'selected':'')?>>approved</option><option <?=($c['status']==='ready_to_launch'?'selected':'')?>>ready_to_launch</option><option <?=($c['status']==='live'?'selected':'')?>>live</option><option <?=($c['status']==='paused'?'selected':'')?>>paused</option></select><input name="daily_budget" value="<?=h($c['daily_budget'])?>"><input name="total_budget" value="<?=h($c['total_budget'])?>"><textarea name="target_audience" rows="2"><?=h($c['target_audience'])?></textarea><input name="offer" value="<?=h($c['offer'])?>"><input name="landing_page_url" value="<?=h($c['landing_page_url'])?>"><textarea name="primary_text" rows="3"><?=h($c['primary_text'])?></textarea><input name="headline" value="<?=h($c['headline'])?>"><input name="cta" value="<?=h($c['cta'])?>"><select name="approval_status"><option>needs_review</option><option <?=($c['approval_status']==='approved'?'selected':'')?>>approved</option><option <?=($c['approval_status']==='rejected'?'selected':'')?>>rejected</option></select><button class="btn">Save Campaign</button></form></td></tr><?php endforeach;?></table></section>

<section class="panel"><h2>Jessica Recommended Actions</h2><table><tr><th>Action</th><th>Reason</th><th>Decision</th></tr><?php foreach($actions as $a):?><tr><td><strong><?=h($a['action_type'])?></strong><br><span class="tag"><?=h($a['action_status'])?></span><div class="muted">Budget change: $<?=h($a['budget_change'])?></div></td><td><?=h($a['reason'])?></td><td><form method="post"><input type="hidden" name="form_type" value="action"><input type="hidden" name="id" value="<?=h($a['id'])?>"><select name="action_status"><option>recommended</option><option <?=($a['action_status']==='approved'?'selected':'')?>>approved</option><option <?=($a['action_status']==='executed'?'selected':'')?>>executed</option><option <?=($a['action_status']==='rejected'?'selected':'')?>>rejected</option></select><textarea name="human_decision" rows="2"><?=h($a['human_decision'])?></textarea><button class="btn">Save Decision</button></form></td></tr><?php endforeach;?></table></section>
</main></body></html>