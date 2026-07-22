<?php
/**
 * V12.15 Conversation Intelligence Analyzer / Intake
 * Upload: /public_html/lead-engine/conversation-intelligence.php
 *
 * Manual/Retell webhook style test:
 * /lead-engine/conversation-intelligence.php?key=YOUR_KEY&phone=203...&lead_type=seller&outcome=appointment_set&transcript=...
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??($_POST['key']??'');
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb152($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function input152($k,$default=''){
  if(isset($_POST[$k]))return $_POST[$k];
  if(isset($_GET[$k]))return $_GET[$k];
  $raw=file_get_contents('php://input');
  if($raw){
    $j=json_decode($raw,true);
    if(is_array($j)&&isset($j[$k]))return $j[$k];
  }
  return $default;
}
function analyze152($text,$outcome){
  $t=strtolower((string)$text.' '.(string)$outcome);
  $mot=40;$urg=35;$appt=30;$sent='neutral';$obj='none';$next='Review and follow up.';
  if(str_contains($t,'appointment')||str_contains($t,'meet')||str_contains($t,'schedule')||str_contains($t,'tomorrow')){$appt+=45;$urg+=25;$mot+=20;$next='Confirm appointment and add to calendar.';}
  if(str_contains($t,'selling')||str_contains($t,'sell')||str_contains($t,'moving')||str_contains($t,'relocat')){$mot+=25;}
  if(str_contains($t,'soon')||str_contains($t,'asap')||str_contains($t,'30 days')||str_contains($t,'this month')){$urg+=35;}
  if(str_contains($t,'just looking')||str_contains($t,'curious')){$obj='just_looking';$mot-=5;$next='Send helpful value/town info and nurture.';}
  if(str_contains($t,'already have')||str_contains($t,'agent')){$obj='already_has_agent';$mot-=20;$next='Respect existing relationship; only provide requested info.';}
  if(str_contains($t,'not interested')){$obj='not_interested';$mot=10;$urg=5;$appt=0;$next='Do not pursue. Mark as nurture or closed.';}
  if(str_contains($t,'busy')||str_contains($t,'call later')){$obj='busy_call_later';$next='Schedule a follow-up time.';}
  if(str_contains($t,'yes')||str_contains($t,'great')||str_contains($t,'interested')){$sent='positive';$mot+=15;}
  if(str_contains($t,'no')||str_contains($t,'stop')||str_contains($t,'annoy')){$sent='negative';$mot-=20;}
  if($outcome==='appointment_set'){$appt=100;$mot=max($mot,80);$urg=max($urg,70);$next='Appointment set. Confirm time, calendar, and send reminder.';}
  return [
    'sentiment'=>$sent,
    'motivation_score'=>max(0,min(100,$mot)),
    'urgency_score'=>max(0,min(100,$urg)),
    'appointment_intent_score'=>max(0,min(100,$appt)),
    'objection_type'=>$obj,
    'recommended_next_action'=>$next
  ];
}

$transcript=input152('transcript','');
$outcome=input152('outcome','unknown');
$leadType=input152('lead_type','');
$phone=input152('phone','');
$name=input152('name','');
$email=input152('email','');
$analysis=analyze152($transcript,$outcome);
$apptSet=($outcome==='appointment_set'||$analysis['appointment_intent_score']>=90);

$payload=[[
  'call_date'=>date('c'),
  'source'=>input152('source','retell_or_manual'),
  'retell_call_id'=>input152('retell_call_id',''),
  'lead_id'=>input152('lead_id',''),
  'appointment_request_id'=>input152('appointment_request_id',''),
  'name'=>$name,
  'phone'=>$phone,
  'email'=>$email,
  'lead_type'=>$leadType,
  'town'=>input152('town',''),
  'market'=>input152('market',''),
  'call_direction'=>input152('call_direction','outbound'),
  'call_status'=>input152('call_status','completed'),
  'transcript'=>$transcript,
  'summary'=>input152('summary', substr($transcript,0,500)),
  'sentiment'=>$analysis['sentiment'],
  'motivation_score'=>$analysis['motivation_score'],
  'urgency_score'=>$analysis['urgency_score'],
  'appointment_intent_score'=>$analysis['appointment_intent_score'],
  'objection_type'=>$analysis['objection_type'],
  'objection_detail'=>input152('objection_detail',''),
  'outcome'=>$outcome,
  'appointment_set'=>$apptSet,
  'follow_up_needed'=>in_array($outcome,['follow_up','unknown'],true) || $analysis['objection_type']==='busy_call_later',
  'follow_up_date'=>input152('follow_up_date','') ?: null,
  'recommended_next_action'=>$analysis['recommended_next_action'],
  'script_variant'=>input152('script_variant',''),
  'raw_payload'=>['get'=>$_GET,'post'=>$_POST,'analysis'=>$analysis],
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];

$res=sb152('POST','conversation_intelligence_calls',$payload);
echo json_encode(['success'=>$res['ok'],'analysis'=>$analysis,'inserted'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
?>