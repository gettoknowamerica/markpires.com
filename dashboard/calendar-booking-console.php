<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb92($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function slots92($days=7,$duration=45,$buffer=30,$max=6){
  if(!defined('GOOGLE_CALENDAR_BRIDGE_URL')||!GOOGLE_CALENDAR_BRIDGE_URL||!defined('GOOGLE_CALENDAR_BRIDGE_SECRET')||!GOOGLE_CALENDAR_BRIDGE_SECRET)return['success'=>false,'error'=>'Google Calendar bridge not configured'];
  $params=['secret'=>GOOGLE_CALENDAR_BRIDGE_SECRET,'action'=>'availability','days'=>$days,'duration_minutes'=>$duration,'buffer_minutes'=>$buffer,'max_slots'=>$max];
  $ch=curl_init(GOOGLE_CALENDAR_BRIDGE_URL.'?'.http_build_query($params));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>25]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$d=json_decode($body,true);
  return is_array($d)?$d:['success'=>false,'http'=>$http,'error'=>$err,'raw'=>$body];
}
function book92($id,$start,$duration,$key){
  $host=$_SERVER['HTTP_HOST']??'markpires.com';
  $url='https://'.$host.'/lead-engine/book-calendar-appointment.php';
  $post=http_build_query(['key'=>$key,'appointment_id'=>$id,'start_iso'=>$start,'duration_minutes'=>$duration]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>35]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($body,true);
  return['ok'=>$http>=200&&$http<300&&is_array($d)&&!empty($d['success']),'http'=>$http,'data'=>is_array($d)?$d:null,'raw'=>is_array($d)?null:$body];
}
$msg='';$error='';$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';$id=$_POST['id']??'';
  if($action==='refresh_slots'&&$id){
    $duration=(int)($_POST['duration_minutes']??45);
    $s=slots92(10,$duration,30,6);
    if(!empty($s['success'])){
      sb92('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['offered_slots'=>$s['slots']??[],'last_slots_checked_at'=>date('c'),'slot_offer_status'=>'slots_ready','updated_at'=>date('c')]);
      $msg='Slots refreshed.';
    }else $error='Slot refresh failed: '.json_encode($s);
  }
  if($action==='book_slot'&&$id){
    $start=$_POST['start_iso']??'';$duration=(int)($_POST['duration_minutes']??45);
    $r=book92($id,$start,$duration,$cronKey);
    if($r['ok']){
      sb92('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['selected_slot'=>['start_iso'=>$start,'duration_minutes'=>$duration],'slot_offer_status'=>'booked','updated_at'=>date('c')]);
      $msg='Appointment booked on Google Calendar.';
    }else $error='Booking failed: '.json_encode($r);
  }
  if($action==='mark_offered'&&$id){
    $r=sb92('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['status'=>'offered','slot_offer_status'=>'offered_to_client','updated_at'=>date('c')]);
    $msg=$r['ok']?'Marked as offered.':'Update failed.';
  }
}
$status=$_GET['status']??'requested';
$ep=$status==='all'?'appointment_requests?select=*&order=created_at.desc&limit=200':'appointment_requests?select=*&status=eq.'.rawurlencode($status).'&order=created_at.desc&limit=200';
$rows=sb92('GET',$ep)['data'];
$ready=defined('GOOGLE_CALENDAR_BRIDGE_URL')&&GOOGLE_CALENDAR_BRIDGE_URL&&defined('GOOGLE_CALENDAR_BRIDGE_SECRET')&&GOOGLE_CALENDAR_BRIDGE_SECRET;
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendar Booking Console V9.2</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin:16px 0;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.muted{color:#777;font-size:13px}.ok{background:#e6f7ec;color:#14783c;padding:12px;border-radius:12px}.bad{background:#ffeaea;color:#9b1c1c;padding:12px;border-radius:12px}.slot{background:#faf9f6;border:1px solid #eee;border-radius:12px;padding:10px;margin:6px 0}input{padding:8px;border:1px solid #ddd;border-radius:8px;margin:2px;max-width:140px}@media(max-width:900px){.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Calendar Booking Console V9.2</div><div>Availability → Slot Offer → Google Calendar Booking → Confirmation</div></div><main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?><?php if($error):?><div class="bad"><?=h($error)?></div><?php endif;?>
<div class="<?=$ready?'ok':'bad'?>">Google Calendar bridge: <?=$ready?'READY':'NOT CONFIGURED'?></div>
<p><a class="btn light" href="?status=requested">Requested</a><a class="btn light" href="?status=offered">Offered</a><a class="btn light" href="?status=confirmed">Confirmed</a><a class="btn light" href="?status=all">All</a><a class="btn gold" href="/dashboard/calendar-bridge.php">Bridge Setup</a><a class="btn light" href="/dashboard/appointment-engine.php">Appointment Engine</a></p>
<section class="panel"><h2>Appointment Requests</h2><table><tr><th>Lead</th><th>Request</th><th>Available Slots</th><th>Actions</th></tr>
<?php foreach($rows as $r):$slots=$r['offered_slots']??[];if(is_string($slots))$slots=json_decode($slots,true);if(!is_array($slots))$slots=[];?>
<tr><td><strong><?=h($r['name']?:'Unknown')?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['town'])?> · <?=h($r['address'])?></div></td><td><strong><?=h($r['appointment_type'])?></strong><div class="muted">Requested: <?=h($r['requested_window'])?><br>Preferred: <?=h($r['preferred_date'])?> <?=h($r['preferred_time'])?><br>Status: <?=h($r['status'])?><br>Slot status: <?=h($r['slot_offer_status'])?><br>Confirmed: <?=h($r['confirmed_start'])?></div></td><td>
<?php if(empty($slots)):?><div class="muted">No slots loaded yet.</div><?php else:foreach($slots as $s):?><div class="slot"><strong><?=h($s['label']??'')?></strong><br><span class="muted"><?=h($s['start_iso']??'')?></span><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input type="hidden" name="start_iso" value="<?=h($s['start_iso']??'')?>"><input type="hidden" name="duration_minutes" value="<?=h($s['duration_minutes']??45)?>"><button class="btn gold" name="action" value="book_slot">Book This Slot</button></form></div><?php endforeach;endif;?>
</td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input type="number" name="duration_minutes" value="45"><button class="btn gold" name="action" value="refresh_slots">Refresh Slots</button></form><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="action" value="mark_offered">Mark Offered</button></form></td></tr>
<?php endforeach;?></table></section></main></body></html>