<?php
/**
 * V12.18 Jessica Executive Inbox Intake
 * Upload: /public_html/lead-engine/executive-call-intake.php
 *
 * Retell/manual endpoint for calls forwarded to Jessica.
 * GET test:
 * /lead-engine/executive-call-intake.php?key=YOUR_KEY&caller_phone=2035551212&caller_name=Demo%20Caller&transcript=I%20need%20Mark%20to%20call%20me%20back%20about%20selling%20my%20home
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? $_POST['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb181($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }

  function input181($k,$default=''){
    if(isset($_POST[$k])) return $_POST[$k];
    if(isset($_GET[$k])) return $_GET[$k];
    $raw=file_get_contents('php://input');
    if($raw){
      $j=json_decode($raw,true);
      if(is_array($j)){
        if(isset($j[$k])) return $j[$k];
        if(isset($j['call'][$k])) return $j['call'][$k];
        if(isset($j['data'][$k])) return $j['data'][$k];
      }
    }
    return $default;
  }

  function clean_phone181($p){
    $p=preg_replace('/[^0-9]/','',(string)$p);
    if(strlen($p)===11 && substr($p,0,1)==='1') $p=substr($p,1);
    return $p;
  }

  function classify181($text,$name=''){
    $t=strtolower((string)$text.' '.(string)$name);
    $category='unknown'; $urgency='normal'; $action=true; $lead=false; $leadType=''; $appt=false; $calendar=false;
    if(str_contains($t,'sell')||str_contains($t,'listing')||str_contains($t,'home value')||str_contains($t,'valuation')){
      $category='lead'; $lead=true; $leadType='seller'; $urgency='high';
    }
    if(str_contains($t,'buy')||str_contains($t,'relocat')||str_contains($t,'moving to connecticut')){
      $category='lead'; $lead=true; $leadType='buyer_relocation'; $urgency='high';
    }
    if(str_contains($t,'past client')||str_contains($t,'worked with mark')||str_contains($t,'you sold')){
      $category='past_client'; $urgency='high';
    }
    if(str_contains($t,'attorney')||str_contains($t,'lawyer')) $category='attorney';
    if(str_contains($t,'photographer')||str_contains($t,'title company')||str_contains($t,'lender')||str_contains($t,'inspection')||str_contains($t,'inspector')) $category='vendor';
    if(str_contains($t,'agent')||str_contains($t,'realtor')||str_contains($t,'broker')) $category='agent';
    if(str_contains($t,'urgent')||str_contains($t,'asap')||str_contains($t,'emergency')||str_contains($t,'today')) $urgency='urgent';
    if(str_contains($t,'appointment')||str_contains($t,'schedule')||str_contains($t,'meet')||str_contains($t,'call me back')) { $appt=true; $calendar=true; }
    if(str_contains($t,'spam')||str_contains($t,'warranty')||str_contains($t,'credit card')) { $category='spam'; $urgency='low'; $action=false; }
    return compact('category','urgency','action','lead','leadType','appt','calendar');
  }

  $raw=file_get_contents('php://input');
  $rawJson=$raw?json_decode($raw,true):[];

  $transcript=input181('transcript','');
  $summary=input181('summary','');
  if(!$summary && $transcript) $summary=substr($transcript,0,600);

  $name=input181('caller_name', input181('name',''));
  $phone=clean_phone181(input181('caller_phone', input181('phone','')));
  $email=strtolower(trim((string)input181('caller_email', input181('email',''))));

  $c=classify181($transcript,$name);
  $reason=input181('reason_for_call','');
  if(!$reason){
    $reason = $c['lead'] ? 'Potential real estate opportunity / callback requested.' : 'Forwarded call for Mark.';
  }

  $recommended='Review call summary.';
  if($c['category']==='lead') $recommended='Call back quickly. Potential real estate lead. If seller, offer market position review.';
  elseif($c['category']==='past_client') $recommended='Prioritize callback. Past client relationship.';
  elseif($c['category']==='vendor') $recommended='Review vendor request and return call if business-related.';
  elseif($c['category']==='spam') $recommended='Archive as spam. No action needed.';
  elseif($c['appt']) $recommended='Call back and schedule/confirm appointment.';

  $payload=[[
    'call_date'=>date('c'),
    'source'=>input181('source','retell_forwarded_call'),
    'retell_call_id'=>input181('retell_call_id', input181('call_id','')),
    'caller_name'=>$name,
    'caller_phone'=>$phone,
    'caller_email'=>$email,
    'caller_category'=>$c['category'],
    'urgency'=>$c['urgency'],
    'reason_for_call'=>$reason,
    'summary'=>$summary,
    'transcript'=>$transcript,
    'recording_url'=>input181('recording_url',''),
    'voicemail_url'=>input181('voicemail_url',''),
    'sentiment'=>input181('sentiment','neutral'),
    'action_required'=>$c['action'],
    'recommended_action'=>$recommended,
    'callback_needed'=>$c['action'] && $c['category']!=='spam',
    'callback_window'=>$c['urgency']==='urgent'?'ASAP':($c['urgency']==='high'?'Today':'When available'),
    'lead_related'=>$c['lead'],
    'lead_type'=>$c['leadType'],
    'appointment_requested'=>$c['appt'],
    'calendar_needed'=>$c['calendar'],
    'status'=>$c['category']==='spam'?'spam':'new',
    'raw_payload'=>['get'=>$_GET,'post'=>$_POST,'json'=>$rawJson,'classification'=>$c],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb181('POST','executive_call_inbox',$payload);

  echo json_encode([
    'success'=>$res['ok'],
    'classification'=>$c,
    'inserted'=>$payload[0],
    'supabase_http'=>$res['http'],
    'body'=>$res['ok']?'':$res['body']
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>