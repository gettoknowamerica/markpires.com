<?php
/**
 * V11.8 Create Google Calendar Event For Appointment
 * /lead-engine/calendar-create-appointment.php?key=YOUR_KEY&id=APPOINTMENT_UUID
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/google-calendar-client.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb118($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>30
  ]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'data'=>is_array($d)?$d:[]];
}

$id=$_GET['id']??'';
if(!$id){echo json_encode(['success'=>false,'error'=>'Missing id']);exit;}

$r=sb118('GET','appointment_requests?select=*&id=eq.'.rawurlencode($id).'&limit=1');
$appt=$r['data'][0]??null;
if(!$appt){echo json_encode(['success'=>false,'error'=>'Appointment not found','lookup'=>$r]);exit;}

$start=$appt['confirmed_start'] ?? '';
$end=$appt['confirmed_end'] ?? '';
if(!$start || !$end){echo json_encode(['success'=>false,'error'=>'Appointment missing confirmed_start or confirmed_end']);exit;}

$title='Jessica Appointment: '.($appt['name'] ?: 'Lead').' — '.($appt['appointment_type'] ?: 'Consultation');
$description="Jessica scheduled appointment\n\nName: ".($appt['name']??'')."\nPhone: ".($appt['phone']??'')."\nEmail: ".($appt['email']??'')."\nTown: ".($appt['town']??'')."\nNotes: ".($appt['notes']??'')."\nSummary: ".($appt['jessica_summary']??'');
$location=$appt['address'] ?: $appt['town'];

$params=[
  'title'=>$title,
  'start'=>$start,
  'end'=>$end,
  'description'=>$description,
  'location'=>$location,
  'guest_email'=>$appt['email'] ?? ''
];

$res=mp_calendar_request('create_event',$params);
mp_calendar_log($id,'create_event',$params,$res);

if($res['ok']){
  $eventId=$res['data']['event_id']??'';
  sb118('PATCH','appointment_requests?id=eq.'.rawurlencode($id),[
    'google_event_id'=>$eventId,
    'calendar_status'=>'created',
    'calendar_response'=>$res,
    'updated_at'=>date('c')
  ]);
} else {
  sb118('PATCH','appointment_requests?id=eq.'.rawurlencode($id),[
    'calendar_status'=>'error',
    'calendar_response'=>$res,
    'updated_at'=>date('c')
  ]);
}

echo json_encode(['success'=>$res['ok'],'appointment_id'=>$id,'calendar'=>$res],JSON_PRETTY_PRINT);
?>