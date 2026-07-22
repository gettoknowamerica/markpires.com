<?php
/**
 * V9 Create Appointment Request
 * Upload: /public_html/lead-engine/create-appointment-request.php
 *
 * Can be called by dashboard, Retell webhook, or future calendar bridge.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb9($method,$endpoint,$payload=null){
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
    CURLOPT_TIMEOUT=>25
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}
function clean9($v){return trim((string)$v);}

$raw=file_get_contents('php://input');
$data=json_decode($raw,true);
if(!is_array($data)) $data=$_POST;

$key=$_GET['key'] ?? ($data['key'] ?? '');
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && $key && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$payload=[[
  'related_type'=>clean9($data['related_type']??''),
  'related_id'=>clean9($data['related_id']??''),
  'lead_id'=>clean9($data['lead_id']??''),
  'jessica_priority_id'=>clean9($data['jessica_priority_id']??''),
  'name'=>clean9($data['name']??''),
  'phone'=>clean9($data['phone']??''),
  'email'=>clean9($data['email']??''),
  'address'=>clean9($data['address']??''),
  'town'=>clean9($data['town']??''),
  'appointment_type'=>clean9($data['appointment_type']??'consultation'),
  'requested_window'=>clean9($data['requested_window']??''),
  'preferred_date'=>clean9($data['preferred_date']??'') ?: null,
  'preferred_time'=>clean9($data['preferred_time']??''),
  'status'=>'requested',
  'source'=>clean9($data['source']??'jessica'),
  'lead_score'=>(int)($data['lead_score']??0),
  'notes'=>clean9($data['notes']??''),
  'jessica_summary'=>clean9($data['jessica_summary']??''),
  'raw_payload'=>$data,
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];

$res=sb9('POST','appointment_requests',$payload);
echo json_encode(['success'=>$res['ok'],'http'=>$res['http'],'data'=>$res['data'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
