<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function sb91($m,$ep,$p=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);$e=curl_error($ch);curl_close($ch);$d=json_decode($b,true);return['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'error'=>$e,'data'=>is_array($d)?$d:[]];}
$key=$_GET['key']??($_POST['key']??'');
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$id=$_POST['appointment_id']??$_GET['appointment_id']??'';$start=$_POST['start_iso']??$_GET['start_iso']??'';$duration=(int)($_POST['duration_minutes']??$_GET['duration_minutes']??45);
if(!$id||!$start){echo json_encode(['success'=>false,'error'=>'appointment_id and start_iso required']);exit;}
if(!defined('GOOGLE_CALENDAR_BRIDGE_URL')||!defined('GOOGLE_CALENDAR_BRIDGE_SECRET')){echo json_encode(['success'=>false,'error'=>'Google Calendar bridge not configured']);exit;}
$apptRes=sb91('GET','appointment_requests?select=*&id=eq.'.rawurlencode($id).'&limit=1');$appt=$apptRes['data'][0]??null;
if(!$appt){echo json_encode(['success'=>false,'error'=>'Appointment request not found']);exit;}
$payload=['secret'=>GOOGLE_CALENDAR_BRIDGE_SECRET,'action'=>'create_event','start_iso'=>$start,'duration_minutes'=>$duration,'name'=>$appt['name']??'','phone'=>$appt['phone']??'','email'=>$appt['email']??'','address'=>$appt['address']??'','town'=>$appt['town']??'','appointment_type'=>$appt['appointment_type']??'consultation'];
$ch=curl_init(GOOGLE_CALENDAR_BRIDGE_URL);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$cal=json_decode($body,true);
$ok=$http>=200&&$http<300&&is_array($cal)&&!empty($cal['success']);
if($ok){sb91('PATCH','appointment_requests?id=eq.'.rawurlencode($id),['confirmed_start'=>$cal['start_iso']??$start,'confirmed_end'=>$cal['end_iso']??date('c',strtotime($start.' +'.$duration.' minutes')),'google_event_id'=>$cal['event_id']??'','calendar_status'=>'created','calendar_response'=>$cal,'status'=>'confirmed','updated_at'=>date('c')]);}
sb91('POST','appointment_calendar_logs',[['appointment_request_id'=>$id,'action'=>'create_event','ok'=>$ok,'request_payload'=>$payload,'response_payload'=>['http'=>$http,'error'=>$err,'body'=>$body,'calendar'=>$cal],'created_at'=>date('c')]]);
echo json_encode(['success'=>$ok,'http'=>$http,'error'=>$err,'calendar'=>$cal,'raw'=>$ok?null:$body],JSON_PRETTY_PRINT);
?>