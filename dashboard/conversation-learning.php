<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb19d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS=json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$status=$_POST['status']??'';
  if($id && in_array($status,['scheduled','completed','cancelled','no_show','pending'],true)){
    sb19d('PATCH','appointment_intelligence_queue?id=eq.'.rawurlencode($id),['appointment_status'=>$status,'updated_at'=>date('c')]);
    $msg='Appointment marked '.$status.'.';
  }
}
$events=sb19d('GET','conversation_learning_events?select=*&order=appointment_intent_score.desc,created_at.desc&limit=150');
$appts=sb19d('GET','appointment_intelligence_queue?select=*&appointment_status=eq.pending&order=appointment_priority.desc,created_at.desc&limit=100');
$briefs=sb19d('GET','conversation_learning_briefings_v2?select=*&order=created_at.desc&limit=10');
$brief=$briefs[0]??[];
$stats=['events'=>count($events),'appts'=>count($appts),'followups'=>0,'highintent'=>0];
foreach($events as $e){ if(!empty($e['follow_up_needed']))$stats['followups']++; if(($e['appointment_intent_score']??0)>=75)$stats['highintent']++; }
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conversation Learning V12.19</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1600px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">Conversation Learning V12.19</div><div>Objections, outcomes, follow-ups, appointments, and script learning</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-conversation-learning.php?key=<?=h($cronKey)?>">Build Learning</a><a class="btn light" href="/dashboard/executive-inbox.php">Executive Inbox</a><a class="btn light" href="/dashboard/conversation-intelligence.php">Conversation Intel</a><a class="btn light" href="/dashboard/queue-intelligence.php">Queue</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['events'])?></div>Analyzed</div><div class="kpi"><div class="n"><?=h($stats['appts'])?></div>Pending Appts</div><div class="kpi"><div class="n"><?=h($stats['followups'])?></div>Follow-ups</div><div class="kpi"><div class="n"><?=h($stats['highintent'])?></div>High Intent</div></section>
<div class="layout"><section class="panel"><h2>Pending Appointment Intelligence</h2><table><tr><th>Priority</th><th>Caller</th><th>Reason</th><th>Window</th><th>Status</th></tr><?php foreach($appts as $a):?><tr><td><strong><?=h($a['appointment_priority'])?></strong></td><td><?=h($a['caller_name']?:$a['caller_phone'])?><div class="muted"><?=h($a['caller_phone'])?><br><?=h($a['lead_type'])?> <?=h($a['town'])?></div></td><td><?=h($a['appointment_reason'])?><div class="muted"><?=h($a['notes'])?></div></td><td><?=h($a['recommended_time_window'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($a['id'])?>"><button class="btn" name="status" value="scheduled">Scheduled</button><button class="btn light" name="status" value="completed">Completed</button><button class="btn light" name="status" value="cancelled">Cancel</button></form></td></tr><?php endforeach;?></table><h2>Conversation Events</h2><table><tr><th>Intent</th><th>Caller</th><th>Objection</th><th>Next Action</th></tr><?php foreach($events as $e):?><tr><td>Appt <?=h($e['appointment_intent_score'])?><br>Mot <?=h($e['motivation_score'])?><br>Urg <?=h($e['urgency_score'])?></td><td><?=h($e['caller_name']?:$e['caller_phone'])?><div class="muted"><?=h($e['lead_type'])?> · <?=h($e['source'])?></div></td><td><?=h($e['objection_type'])?><div class="muted"><?=h($e['recommended_response'])?></div></td><td><?=h($e['recommended_next_action'])?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Learning Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Learning to create briefing.')?></pre></div></section></div>
</main></body></html>