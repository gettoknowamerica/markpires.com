<?php
/**
 * V11.3 Send Approved Builder Intros
 * Upload: /public_html/lead-engine/send-builder-intros.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb113s($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function email113($to,$subject,$html){
  if(!defined('RESEND_API_KEY')||!RESEND_API_KEY||!$to)return ['ok'=>false,'error'=>'email not configured or missing'];
  $from=(defined('RESEND_FROM_EMAIL')&&RESEND_FROM_EMAIL)?RESEND_FROM_EMAIL:'noreply@markpires.com';
  $payload=['from'=>'Mark Pires <'.$from.'>','to'=>[$to],'subject'=>$subject,'html'=>$html];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}
function norm113($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)==10)return '+1'.$d;if(strlen($d)==11&&substr($d,0,1)==='1')return '+'.$d;return $p;}
function sms113($to,$body){
  if(!defined('TWILIO_ACCOUNT_SID')||!defined('TWILIO_AUTH_TOKEN')||!defined('TWILIO_SMS_FROM')||!$to)return ['ok'=>false,'error'=>'sms not configured or missing'];
  $url='https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'/Messages.json';
  $post=http_build_query(['From'=>norm113(TWILIO_SMS_FROM),'To'=>norm113($to),'Body'=>$body]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}

$limit=max(1,min(25,(int)($_GET['limit']??5)));
$sendSms=($_GET['sms']??'')==='1';
$rows=sb113s('GET','builder_intro_outreach?select=*&status=eq.approved&order=match_score.desc&limit='.$limit)['data'];
$results=[];

foreach($rows as $r){
  if(!is_array($r))continue;
  $email=email113($r['builder_email']??'',$r['intro_subject']??'Builder opportunity',$r['intro_body']??'');
  $sms=['ok'=>false,'skipped'=>true];
  if($sendSms)$sms=sms113($r['builder_phone']??'',$r['sms_body']??'');

  $ok=!empty($email['ok']) || !empty($sms['ok']);
  $status=$ok?'sent':'error';

  sb113s('PATCH','builder_intro_outreach?id=eq.'.rawurlencode($r['id']),[
    'status'=>$status,
    'email_sent'=>!empty($email['ok']),
    'sms_sent'=>!empty($sms['ok']),
    'provider_response'=>['email'=>$email,'sms'=>$sms],
    'updated_at'=>date('c')
  ]);

  if(!empty($r['match_id'])){
    sb113s('PATCH','builder_opportunity_matches?id=eq.'.rawurlencode($r['match_id']),[
      'outreach_sent'=>$ok,
      'intro_sent'=>$ok,
      'status'=>$ok?'introduced':'approved',
      'updated_at'=>date('c')
    ]);
  }

  $results[]=['id'=>$r['id'],'builder'=>$r['builder_name'],'ok'=>$ok,'email'=>$email['ok']??false,'sms'=>$sms['ok']??false];
}

echo json_encode(['success'=>true,'sent_checked'=>count($rows),'results'=>$results],JSON_PRETTY_PRINT);
?>