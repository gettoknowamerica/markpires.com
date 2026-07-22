<?php
/**
 * V11.9 Select Appointment Slot
 * /lead-engine/select-appointment-slot.php?key=YOUR_KEY&id=APPOINTMENT_UUID&option=1
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb119s($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}

$id=$_GET['id']??'';$option=max(1,min(3,(int)($_GET['option']??1)))-1;
$r=sb119s('GET','appointment_requests?select=*&id=eq.'.rawurlencode($id).'&limit=1');
$appt=$r['data'][0]??null;
if(!$appt){echo json_encode(['success'=>false,'error'=>'appointment not found']);exit;}
$slots=is_string($appt['offered_slots']??null)?json_decode($appt['offered_slots'],true):($appt['offered_slots']??[]);
if(empty($slots[$option])){echo json_encode(['success'=>false,'error'=>'slot option not found','slots'=>$slots]);exit;}
$slot=$slots[$option];

$patch=[
  'selected_slot'=>$slot,
  'selected_slot_start'=>$slot['start'],
  'selected_slot_end'=>$slot['end'],
  'automation_status'=>'selected_slot_ready_to_book',
  'slot_offer_status'=>'selected',
  'updated_at'=>date('c')
];
$res=sb119s('PATCH','appointment_requests?id=eq.'.rawurlencode($id),$patch);
echo json_encode(['success'=>$res['ok'],'appointment_id'=>$id,'selected'=>$slot,'supabase'=>$res],JSON_PRETTY_PRINT);
?>