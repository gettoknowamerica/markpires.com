<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>60]);
 if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
 $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
 return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['task_id'])){
 $r=sb('PATCH','goliath_daily_tasks?id=eq.'.rawurlencode($_POST['task_id']),['status'=>$_POST['status']??'done','updated_at'=>date('c')]);
 $msg=$r['ok']?'Task updated.':'Update failed.';
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$plans=sb('GET','goliath_daily_plans?select=*&order=created_at.desc&limit=5');
$plan=$plans['data'][0]??null;
$tasks=$plan?sb('GET','goliath_daily_tasks?select=*&plan_id=eq.'.rawurlencode($plan['id']).'&order=priority.desc,created_at.asc&limit=50'):['data'=>[]];
$opps=sb('GET','jessica_opportunity_engine?select=*&order=revenue_score.desc,confidence_score.desc&limit=10');
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V21 Goliath Executive Assistant</title><style>
body{margin:0;background:#f5f3ef;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e;font-size:46px;margin:0}.wrap{max-width:1900px;margin:auto;padding:20px}.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.panel{background:white;border-radius:18px;box-shadow:0 4px 18px #0001;overflow:hidden;margin-bottom:16px}.inner{padding:18px}.btn{background:#c8a96e;color:#111;text-decoration:none;border:0;border-radius:10px;padding:10px 14px;font-weight:900;display:inline-block;margin:4px;cursor:pointer}.dark{background:#111827;color:white}.summary{white-space:pre-wrap;background:#111827;color:white;border-radius:14px;padding:18px;line-height:1.45}.score{font-size:38px;color:#c8a96e;font-weight:900}.task{display:grid;grid-template-columns:70px 1fr 170px;gap:12px;align-items:start;border-bottom:1px solid #eee;padding:14px}.tag{display:inline-block;background:#111827;color:white;border-radius:99px;padding:4px 8px;font-size:11px}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}th{background:#faf9f6;color:#777;font-size:11px;text-transform:uppercase}@media(max-width:1000px){.grid{grid-template-columns:1fr}.task{grid-template-columns:1fr}}</style></head><body>
<section class="hero"><h1>V21 Goliath Executive Assistant</h1><p>Daily revenue plan, priorities, calls, content, ads, and top opportunity.</p></section>
<main class="wrap">
<?php if($msg):?><p><strong><?=h($msg)?></strong></p><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-executive-assistant.php?key=<?=h($key)?>">Run Today’s Revenue Plan</a><a class="btn" href="/dashboard/opportunity-engine.php">Opportunity Engine</a><a class="btn" href="/dashboard/mls-expired-manager.php">MLS Expired Manager</a><a class="btn" href="/dashboard/jessica-drive-mode.php">Ask Jessica</a></p>
<div class="grid"><section>
<div class="panel"><div class="inner"><h2><?=h($plan['title']??'No executive plan yet')?></h2><div class="summary"><?=h($plan['executive_summary']??'Click Run Today’s Revenue Plan.')?></div></div></div>
<div class="panel"><div class="inner"><h2>Priority Actions</h2></div>
<?php foreach(($tasks['data']??[]) as $t):?><div class="task"><div class="score"><?=h($t['priority'])?></div><div><strong><?=h($t['task_title'])?></strong><br><?=h($t['task_detail'])?><br><span class="tag"><?=h($t['task_type'])?></span> <span class="tag"><?=h($t['status'])?></span></div><form method="post"><input type="hidden" name="task_id" value="<?=h($t['id'])?>"><select name="status"><option>new</option><option <?=($t['status']==='doing'?'selected':'')?>>doing</option><option <?=($t['status']==='done'?'selected':'')?>>done</option><option <?=($t['status']==='blocked'?'selected':'')?>>blocked</option></select><button class="btn">Save</button></form></div><?php endforeach;?>
</div></section><aside>
<div class="panel"><div class="inner"><h2>Top Opportunity</h2><div class="score"><?=h($plan['top_opportunity_score']??0)?></div><strong><?=h($plan['top_opportunity_title']??'None yet')?></strong><p><?=h($plan['revenue_focus']??'Generate one attributable appointment from Goliath.')?></p></div></div>
<div class="panel"><div class="inner"><h2>Top Signals</h2><p><strong>Expired:</strong><br><?=h($plan['top_expired_summary']??'')?></p><p><strong>Lead:</strong><br><?=h($plan['top_lead_summary']??'')?></p><p><strong>Street:</strong><br><?=h($plan['top_street_summary']??'')?></p><p><strong>Content:</strong><br><?=h($plan['top_content_action']??'')?></p><p><strong>Ad:</strong><br><?=h($plan['top_ad_action']??'')?></p></div></div>
</aside></div>
<section class="panel"><div class="inner"><h2>Top 10 Revenue Opportunities</h2></div><table><tr><th>Score</th><th>Opportunity</th><th>Why</th><th>Action</th></tr><?php foreach(($opps['data']??[]) as $o):?><tr><td><div class="score"><?=h($o['revenue_score'])?></div></td><td><strong><?=h($o['title'])?></strong><br><?=h($o['opportunity_type'])?><br><?=h($o['address'])?></td><td><?=h($o['why_now'])?></td><td><?=h($o['recommended_action'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>