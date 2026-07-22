<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb9d($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'data'=>is_array($d)?$d:[],'body'=>$body];
}
function norm9($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)==10)return'+1'.$d;if(strlen($d)==11&&$d[0]=='1')return'+'.$d;return$p;}
function send_sms9($to,$body){
  if(!defined('TWILIO_ACCOUNT_SID')||!defined('TWILIO_AUTH_TOKEN')||!defined('TWILIO_SMS_FROM'))return ['ok'=>false,'error'=>'Twilio SMS not configured'];
  $url='https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'/Messages.json';
  $post=http_build_query(['From'=>norm9(TWILIO_SMS_FROM),'To'=>norm9($to),'Body'=>$body]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}
function send_email9($to,$subject,$html){
  if(!defined('RESEND_API_KEY')||!defined('RESEND_FROM_EMAIL')||!$to)return ['ok'=>false,'error'=>'Email not configured'];
  $payload=['from'=>(defined('MARK_NAME')?MARK_NAME:'Mark Pires').' <'.RESEND_FROM_EMAIL.'>','to'=>[$to],'subject'=>$subject,'html'=>$html];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>15]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';$action=$_POST['action']??'';
  if($id && $action==='confirm'){
    $date=$_POST['confirmed_date']??'';$time=$_POST['confirmed_time']??'';$minutes=(int)($_POST['duration_minutes']??45);
    $start=$date&&$time?date('c',strtotime($date.' '.$time)):null;
    $end=$start?date('c',strtotime($start.' +'.$minutes.' minutes')):null;
    $r=sb9d('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['confirmed_start'=>$start,'confirmed_end'=>$end,'status'=>'confirmed','updated_at'=>date('c')]);
    $row=$r['data'][0]??[];
    if($r['ok']&&$row){
      $when=date('l, F j \\a\\t g:i A',strtotime($start));
      $sms="Confirmed: Mark Pires is scheduled with you for {$when}. Reply if anything changes.";
      $smsRes=!empty($row['phone'])?send_sms9($row['phone'],$sms):['ok'=>false,'error'=>'no phone'];
      $emailRes=!empty($row['email'])?send_email9($row['email'],'Appointment confirmed with Mark Pires','<p>Your appointment with Mark Pires is confirmed for <strong>'.$when.'</strong>.</p><p>If anything changes, reply to this email or text Mark directly.</p>'):['ok'=>false,'error'=>'no email'];
      sb9d('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['confirmation_sms_sent'=>$smsRes['ok'],'confirmation_email_sent'=>$emailRes['ok'],'provider_response'=>['sms'=>$smsRes,'email'=>$emailRes],'updated_at'=>date('c')]);
      $msg='Appointment confirmed and confirmation attempted.';
    } else $msg='Confirm failed: '.$r['body'];
  } elseif($id && in_array($action,['offered','mark_confirmed','reschedule_needed','cancelled','completed'],true)){
    $r=sb9d('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['status'=>$action,'updated_at'=>date('c')]);
    $msg=$r['ok']?'Appointment updated.':'Update failed.';
  }
}

$status=$_GET['status']??'requested';
$rows=sb9d('GET',$status==='all'?'appointment_requests?select=*&order=created_at.desc&limit=300':'appointment_requests?select=*&status=eq.'.rawurlencode($status).'&order=created_at.desc&limit=300')['data'];
$requested=0;$confirmed=0;$today=0;$week=0;
$all=sb9d('GET','appointment_requests?select=*&order=created_at.desc&limit=500')['data'];
foreach($all as $r){if(($r['status']??'')==='requested')$requested++;if(($r['status']??'')==='confirmed')$confirmed++;if(!empty($r['confirmed_start'])){if(date('Y-m-d',strtotime($r['confirmed_start']))===date('Y-m-d'))$today++;if(strtotime($r['confirmed_start'])<=strtotime('+7 days'))$week++;}}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Appointment Engine V9</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}.muted{color:#777;font-size:13px}input{padding:8px;border:1px solid #ddd;border-radius:8px;margin:2px;max-width:150px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Appointment Engine V9</div><div>Jessica appointment requests · Mark confirmation · SMS/email confirmations · <a style="color:#fff" href="/dashboard/jessica-mission-control.php">Mission Control</a></div></div><main class="wrap">
<?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn light" href="?status=requested">Requested</a><a class="btn light" href="?status=confirmed">Confirmed</a><a class="btn light" href="?status=all">All</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($requested)?></div>Requested</div><div class="kpi"><div class="n"><?=h($confirmed)?></div>Confirmed</div><div class="kpi"><div class="n"><?=h($today)?></div>Today</div><div class="kpi"><div class="n"><?=h($week)?></div>Next 7 Days</div></section>
<section class="panel"><h2>Appointment Queue</h2><table><tr><th>Lead</th><th>Request</th><th>Confirmed Time</th><th>Notes</th><th>Actions</th></tr>
<?php foreach($rows as $r):?><tr><td><strong><?=h($r['name']?:'Unknown')?></strong><div class="muted"><?=h($r['phone'])?><br><?=h($r['email'])?><br><?=h($r['town'])?> · <?=h($r['address'])?></div></td><td><?=h($r['appointment_type'])?><div class="muted"><?=h($r['requested_window'])?><br><?=h($r['preferred_date'])?> <?=h($r['preferred_time'])?><br>Status: <?=h($r['status'])?></div></td><td><?=h($r['confirmed_start'])?><div class="muted">Buffer: <?=h($r['travel_buffer_minutes'])?> min</div></td><td><?=h($r['jessica_summary']?:$r['notes'])?></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input type="date" name="confirmed_date"><input type="time" name="confirmed_time"><input type="number" name="duration_minutes" value="45"><button class="btn gold" name="action" value="confirm">Confirm + Notify</button></form><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn light" name="action" value="offered">Offered</button><button class="btn light" name="action" value="reschedule_needed">Reschedule</button><button class="btn" name="action" value="completed">Completed</button><button class="btn light" name="action" value="cancelled">Cancel</button></form></td></tr><?php endforeach;?>
</table></section></main></body></html>
