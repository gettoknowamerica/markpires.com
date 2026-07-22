<?php
/**
 * V13.6 Voice Intake Webhook
 * Upload: /public_html/lead-engine/voice-intelligence-intake.php
 *
 * Retell webhook / forwarded call target:
 * https://markpires.com/lead-engine/voice-intelligence-intake.php?key=timetomakethedonuts
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb136i($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function clean_phone136($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }

  $raw=file_get_contents('php://input');
  $json=json_decode($raw,true);
  if(!is_array($json)) $json=[];

  $get=function($keys,$default='') use ($json){
    foreach((array)$keys as $k){
      if(isset($_GET[$k]) && $_GET[$k] !== '') return $_GET[$k];
      if(isset($_POST[$k]) && $_POST[$k] !== '') return $_POST[$k];
      if(isset($json[$k]) && $json[$k] !== '') return $json[$k];
      if(isset($json['call'][$k]) && $json['call'][$k] !== '') return $json['call'][$k];
      if(isset($json['data'][$k]) && $json['data'][$k] !== '') return $json['data'][$k];
    }
    return $default;
  };

  $transcript=(string)$get(['transcript','call_transcript'],'');
  $summary=(string)$get(['summary','call_summary'],'');
  $phone=clean_phone136($get(['caller_phone','phone','from_number','from'],''));
  $name=(string)$get(['caller_name','name'],'');
  $email=(string)$get(['caller_email','email'],'');
  $recording=(string)$get(['recording_url','recording'],'');
  $retell=(string)$get(['retell_call_id','call_id','id'],'');
  $direction=(string)$get(['direction'],'inbound');
  $status=(string)$get(['call_status','status'],'completed');

  $text=strtolower($transcript.' '.$summary.' '.$name.' '.$email.' '.$phone);
  $callType='forwarded_call';
  if(str_contains($text,'buy')||str_contains($text,'sell')||str_contains($text,'home value')||str_contains($text,'valuation')||str_contains($text,'listing')) $callType='inbound_lead';
  if(str_contains($text,'vendor')||str_contains($text,'marketing')||str_contains($text,'seo company')) $callType='vendor';
  if(str_contains($text,'spam')||str_contains($text,'warranty')||str_contains($text,'solar offer')) $callType='spam';
  if($direction==='outbound') $callType='outbound_call';

  $appointment=(str_contains($text,'appointment')||str_contains($text,'schedule')||str_contains($text,'meet')||str_contains($text,'call me back'));
  $callback=($appointment||str_contains($text,'call back')||str_contains($text,'callback')||str_contains($text,'urgent'));
  $leadRelated=in_array($callType,['inbound_lead','outbound_call'],true);
  $urgency=str_contains($text,'urgent')||str_contains($text,'today')||str_contains($text,'asap')?'urgent':($callback?'high':'normal');
  $score=20;
  if($leadRelated)$score+=35;
  if($appointment)$score+=35;
  if(str_contains($text,'sell')||str_contains($text,'listing'))$score+=20;
  if(str_contains($text,'buy'))$score+=10;
  if($urgency==='urgent')$score+=15;
  if(in_array($callType,['spam','vendor'],true))$score=0;
  $score=max(0,min(100,$score));

  $recommended='Review call.';
  if($callType==='spam')$recommended='Archive as spam.';
  elseif($callType==='vendor')$recommended='Review only if vendor is useful.';
  elseif($appointment)$recommended='Mark should follow up and schedule/confirm appointment.';
  elseif($leadRelated)$recommended='Treat as lead and route to opportunity pipeline.';
  elseif($callback)$recommended='Mark should call back.';

  $payload=[[
    'event_date'=>date('c'),'source'=>'retell','retell_call_id'=>$retell,'call_type'=>$callType,'direction'=>$direction,
    'caller_name'=>$name,'caller_phone'=>$phone,'caller_email'=>$email,'transcript'=>$transcript,'summary'=>$summary,'recording_url'=>$recording,
    'call_status'=>$status,'urgency'=>$urgency,'lead_related'=>$leadRelated,'appointment_requested'=>$appointment,'callback_needed'=>$callback,
    'hot_lead'=>$score>=75,'lead_score'=>$score,'recommended_action'=>$recommended,'raw_payload'=>!empty($json)?$json:array_merge($_GET,$_POST),
    'status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')
  ]];

  $res=sb136i('POST','voice_intelligence_events',$payload);

  echo json_encode(['success'=>$res['ok'],'voice_event'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>