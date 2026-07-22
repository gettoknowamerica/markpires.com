<?php
/**
 * V11.10 Appointment Response Intake
 * /lead-engine/appointment-response-intake.php?key=YOUR_KEY&phone=203...&message=Option%201
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
function sb1110($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS=json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function digits1110($p){return preg_replace('/\D+/','',(string)$p);}
function parse1110($msg){
  $m=strtolower(trim((string)$msg));
  if(preg_match('/(?:option|#)?\s*([123])\b/',$m,$x))return ['intent'=>'option_selected','option'=>(int)$x[1],'confidence'=>95];
  if(preg_match('/\b(first|one)\b/',$m))return ['intent'=>'option_selected','option'=>1,'confidence'=>80];
  if(preg_match('/\b(second|two)\b/',$m))return ['intent'=>'option_selected','option'=>2,'confidence'=>80];
  if(preg_match('/\b(third|three)\b/',$m))return ['intent'=>'option_selected','option'=>3,'confidence'=>80];
  if(str_contains($m,'cancel'))return ['intent'=>'cancel','option'=>0,'confidence'=>85];
  if(str_contains($m,'reschedule')||str_contains($m,'another time'))return ['intent'=>'reschedule','option'=>0,'confidence'=>80];
  if(str_contains($m,'question')||str_contains($m,'?'))return ['intent'=>'question','option'=>0,'confidence'=>60];
  return ['intent'=>'unknown','option'=>0,'confidence'=>10];
}

$phone=$_GET['phone']??($_POST['phone']??'');
$email=$_GET['email']??($_POST['email']??'');
$message=$_GET['message']??($_POST['message']??'');
if(!$message){echo json_encode(['success'=>false,'error'=>'Missing message']);exit;}

$parsed=parse1110($message);
$q='';
if($phone){
  $d=digits1110($phone);
  $q='appointment_requests?select=*&status=eq.offered&order=created_at.desc&limit=20';
} elseif($email) {
  $q='appointment_requests?select=*&status=eq.offered&email=eq.'.rawurlencode($email).'&order=created_at.desc&limit=1';
} else {
  echo json_encode(['success'=>false,'error'=>'Missing phone or email']);exit;
}
$rows=sb1110('GET',$q)['data'];
$appt=null;
foreach($rows as $r){
  if($email && strtolower($r['email']??'')===strtolower($email)){$appt=$r;break;}
  if($phone && digits1110($r['phone']??'')===digits1110($phone)){$appt=$r;break;}
}
if(!$appt){echo json_encode(['success'=>false,'error'=>'No offered appointment found for phone/email']);exit;}

$inboxPayload=[[
  'appointment_request_id'=>$appt['id'],
  'name'=>$appt['name']??'',
  'phone'=>$phone ?: ($appt['phone']??''),
  'email'=>$email ?: ($appt['email']??''),
  'raw_message'=>$message,
  'parsed_option'=>$parsed['option'],
  'parsed_intent'=>$parsed['intent'],
  'confidence'=>$parsed['confidence'],
  'status'=>'new',
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$inbox=sb1110('POST','appointment_response_inbox',$inboxPayload);

$result=['applied'=>false];
if($parsed['intent']==='option_selected' && $parsed['option']>=1){
  $host=$_SERVER['HTTP_HOST']??'markpires.com';
  $url='https://'.$host.'/lead-engine/select-appointment-slot.php?key='.rawurlencode(AFTER_HOURS_CRON_KEY).'&id='.rawurlencode($appt['id']).'&option='.$parsed['option'];
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>30]);$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $data=json_decode($body,true);
  $result=['applied'=>is_array($data)&&!empty($data['success']),'select_response'=>is_array($data)?$data:$body,'http'=>$http];
  sb1110('PATCH','appointment_requests?id=eq.'.rawurlencode($appt['id']),['last_client_response'=>$message,'last_client_response_at'=>date('c'),'response_intake_status'=>'option_selected','updated_at'=>date('c')]);
} else {
  sb1110('PATCH','appointment_requests?id=eq.'.rawurlencode($appt['id']),['last_client_response'=>$message,'last_client_response_at'=>date('c'),'response_intake_status'=>$parsed['intent'],'automation_status'=>'needs_human_review','updated_at'=>date('c')]);
}

if(!empty($inbox['data'][0]['id'])){
  sb1110('PATCH','appointment_response_inbox?id=eq.'.rawurlencode($inbox['data'][0]['id']),['status'=>$result['applied']?'applied':'needs_review','result'=>$result,'updated_at'=>date('c')]);
}

echo json_encode(['success'=>true,'appointment_id'=>$appt['id'],'parsed'=>$parsed,'result'=>$result],JSON_PRETTY_PRINT);
?>