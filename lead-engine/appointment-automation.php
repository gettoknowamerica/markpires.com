<?php
/**
 * V11.9 Appointment Automation
 * Upload: /public_html/lead-engine/appointment-automation.php
 *
 * Run:
 * /lead-engine/appointment-automation.php?key=YOUR_KEY&limit=10
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google-calendar-client.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb119($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function send_email119($to,$subject,$html){
  if(!$to)return ['ok'=>false,'error'=>'missing recipient'];
  if(!defined('RESEND_API_KEY')||!RESEND_API_KEY)return ['ok'=>false,'error'=>'RESEND_API_KEY missing'];
  $from=(defined('RESEND_FROM_EMAIL')&&RESEND_FROM_EMAIL)?RESEND_FROM_EMAIL:'noreply@markpires.com';
  $payload=['from'=>'Mark Pires <'.$from.'>','to'=>[$to],'subject'=>$subject,'html'=>$html];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

function norm119($p){
  $d=preg_replace('/\D+/','',(string)$p);
  if(strlen($d)==10)return '+1'.$d;
  if(strlen($d)==11&&substr($d,0,1)==='1')return '+'.$d;
  return $p;
}
function send_sms119($to,$body){
  if(!$to)return ['ok'=>false,'error'=>'missing recipient'];
  if(!defined('TWILIO_ACCOUNT_SID')||!defined('TWILIO_AUTH_TOKEN')||!defined('TWILIO_SMS_FROM'))return ['ok'=>false,'error'=>'Twilio missing'];
  $url='https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'/Messages.json';
  $post=http_build_query(['From'=>norm119(TWILIO_SMS_FROM),'To'=>norm119($to),'Body'=>$body]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}

function slot_date119($appt){
  if(!empty($appt['preferred_date'])) return $appt['preferred_date'];
  return date('Y-m-d', strtotime('+1 weekday'));
}
function format_slot119($iso){
  return date('D M j, g:i A', strtotime($iso));
}

$limit=max(1,min(25,(int)($_GET['limit']??10)));
$rows=sb119('GET','appointment_requests?select=*&status=in.(requested,offered)&automation_status=in.(new,needs_slots,slots_offered,error)&order=lead_score.desc,created_at.asc&limit='.$limit)['data'];

$results=[];$booked=0;$checked=0;$skipped=0;$errors=0;

foreach($rows as $appt){
  if(!is_array($appt) || empty($appt['id']))continue;
  $id=$appt['id'];

  if(($appt['status']??'')==='offered' && !empty($appt['selected_slot_start']) && !empty($appt['selected_slot_end'])){
    $patch=[
      'confirmed_start'=>$appt['selected_slot_start'],
      'confirmed_end'=>$appt['selected_slot_end'],
      'status'=>'confirmed',
      'automation_status'=>'confirmed_ready_for_calendar',
      'last_automation_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    sb119('PATCH','appointment_requests?id=eq.'.rawurlencode($id),$patch);

    $host=$_SERVER['HTTP_HOST']??'markpires.com';
    $url='https://'.$host.'/lead-engine/calendar-create-appointment.php?key='.rawurlencode(AFTER_HOURS_CRON_KEY).'&id='.rawurlencode($id);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>45]);
    $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $data=json_decode($body,true);
    $ok=is_array($data)&&!empty($data['success']);

    if($ok){
      $booked++;
      $subject='Appointment confirmed with Mark Pires';
      $html='<p>Hi '.htmlspecialchars($appt['name']??'there').',</p><p>Your appointment with Mark Pires is confirmed for <strong>'.htmlspecialchars(format_slot119($appt['selected_slot_start'])).'</strong>.</p><p>Mark Pires<br>203-247-2655</p>';
      $email=send_email119($appt['email']??'',$subject,$html);
      $sms=send_sms119($appt['phone']??'','Your appointment with Mark Pires is confirmed for '.format_slot119($appt['selected_slot_start']).'. Reply with any changes.');
      sb119('POST','appointment_automation_messages',[[
        'appointment_request_id'=>$id,'message_type'=>'confirmation','channel'=>'email_sms','recipient'=>($appt['email']??'').' / '.($appt['phone']??''),'subject'=>$subject,'body'=>strip_tags($html),'status'=>'sent','provider_response'=>['email'=>$email,'sms'=>$sms],'created_at'=>date('c'),'updated_at'=>date('c')
      ]]);
    } else {
      $errors++;
      sb119('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['automation_status'=>'calendar_error','automation_notes'=>'Calendar create failed','updated_at'=>date('c')]);
    }
    $results[]=['id'=>$id,'mode'=>'confirm_selected_slot','calendar_ok'=>$ok,'calendar'=>$data??$body];
    continue;
  }

  $date=slot_date119($appt);
  $duration=60;
  if(str_contains(strtolower((string)($appt['appointment_type']??'')),'phone'))$duration=30;
  $availability=mp_calendar_request('availability',[
    'date'=>$date,
    'duration_minutes'=>$duration,
    'buffer_minutes'=>(int)($appt['travel_buffer_minutes']??30),
    'start_hour'=>9,
    'end_hour'=>17
  ]);
  $checked++;

  if(!$availability['ok']){
    $errors++;
    sb119('PATCH','appointment_requests?id=eq.'.rawurlencode($id),[
      'automation_status'=>'calendar_availability_error',
      'calendar_response'=>$availability,
      'automation_attempts'=>(int)($appt['automation_attempts']??0)+1,
      'last_automation_at'=>date('c'),
      'updated_at'=>date('c')
    ]);
    $results[]=['id'=>$id,'mode'=>'availability','ok'=>false,'error'=>'availability failed','response'=>$availability];
    continue;
  }

  $slots=$availability['data']['slots']??[];
  if(empty($slots)){
    $skipped++;
    sb119('PATCH','appointment_requests?id=eq.'.rawurlencode($id),[
      'automation_status'=>'no_slots_found',
      'offered_slots'=>[],
      'last_slots_checked_at'=>date('c'),
      'automation_attempts'=>(int)($appt['automation_attempts']??0)+1,
      'last_automation_at'=>date('c'),
      'updated_at'=>date('c')
    ]);
    $results[]=['id'=>$id,'mode'=>'offer_slots','ok'=>false,'reason'=>'no slots'];
    continue;
  }

  $offer=array_slice($slots,0,3);
  $links=[];
  foreach($offer as $i=>$slot){
    $n=$i+1;
    $links[]='Option '.$n.': '.format_slot119($slot['start']);
  }
  $subject='Choose a time with Mark Pires';
  $html='<p>Hi '.htmlspecialchars($appt['name']??'there').',</p><p>Jessica here with Mark Pires. Here are the next available appointment options:</p><ul>';
  foreach($links as $line)$html.='<li>'.htmlspecialchars($line).'</li>';
  $html.='</ul><p>Please reply with Option 1, 2, or 3, and Jessica will confirm it on Mark’s calendar.</p><p>Mark Pires<br>203-247-2655</p>';
  $sms='Jessica with Mark Pires. Available times: '.implode(' | ',$links).'. Reply Option 1, 2, or 3.';

  $email=send_email119($appt['email']??'',$subject,$html);
  $smsRes=send_sms119($appt['phone']??'',$sms);

  sb119('POST','appointment_automation_messages',[[
    'appointment_request_id'=>$id,
    'message_type'=>'offer_slots',
    'channel'=>'email_sms',
    'recipient'=>($appt['email']??'').' / '.($appt['phone']??''),
    'subject'=>$subject,
    'body'=>strip_tags($html).' SMS: '.$sms,
    'status'=>($email['ok']||$smsRes['ok'])?'sent':'error',
    'provider_response'=>['email'=>$email,'sms'=>$smsRes],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]]);

  sb119('PATCH','appointment_requests?id=eq.'.rawurlencode($id),[
    'status'=>'offered',
    'automation_status'=>'slots_offered',
    'offered_slots'=>$offer,
    'slot_offer_status'=>'offered',
    'last_slots_checked_at'=>date('c'),
    'automation_attempts'=>(int)($appt['automation_attempts']??0)+1,
    'last_automation_at'=>date('c'),
    'updated_at'=>date('c')
  ]);

  $results[]=['id'=>$id,'mode'=>'offer_slots','ok'=>true,'slots'=>$offer,'email_ok'=>$email['ok']??false,'sms_ok'=>$smsRes['ok']??false];
}

sb119('POST','appointment_automation_runs',[[
  'run_type'=>'manual_or_cron',
  'attempted'=>count($rows),
  'slots_checked'=>$checked,
  'booked'=>$booked,
  'skipped'=>$skipped,
  'errors'=>$errors,
  'results'=>$results,
  'created_at'=>date('c')
]]);

echo json_encode(['success'=>$errors===0,'attempted'=>count($rows),'slots_checked'=>$checked,'booked'=>$booked,'skipped'=>$skipped,'errors'=>$errors,'results'=>$results],JSON_PRETTY_PRINT);
?>