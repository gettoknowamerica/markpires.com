<?php
/**
 * V22.0 Resend Diagnostic
 * Upload: /public_html/lead-engine/resend-diagnostic.php
 * Run: /lead-engine/resend-diagnostic.php?key=YOUR_CRON_KEY
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function v22_send_resend($to,$subject,$html){
  if(!defined('RESEND_API_KEY')||!RESEND_API_KEY) return ['ok'=>false,'error'=>'RESEND_API_KEY missing'];
  $from=defined('RESEND_FROM_EMAIL')?RESEND_FROM_EMAIL:'mark@markpires.com';
  $mark=defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com';
  $payload=[
    'from'=>(defined('MARK_NAME')?MARK_NAME:'Mark Pires').' <'.$from.'>',
    'to'=>[$to],
    'reply_to'=>[$mark],
    'subject'=>$subject,
    'html'=>$html
  ];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $decoded=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'payload_safe'=>['from'=>$payload['from'],'to'=>$payload['to'],'reply_to'=>$payload['reply_to'],'subject'=>$payload['subject']],'body'=>is_array($decoded)?$decoded:$body];
}

$to=$_GET['to']??(defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com');
$res=v22_send_resend($to,'V22 Resend Diagnostic — MarkPires.com','<h2>V22 Resend Diagnostic</h2><p>If you received this, Resend can send from the current MarkPires.com config.</p><p>Time: '.htmlspecialchars(date('c')).'</p>');

echo json_encode([
  'success'=>$res['ok'],
  'resend'=>$res,
  'config_safe'=>[
    'mark_email'=>defined('MARK_EMAIL')?MARK_EMAIL:null,
    'resend_from_email'=>defined('RESEND_FROM_EMAIL')?RESEND_FROM_EMAIL:null,
    'resend_key_present'=>defined('RESEND_API_KEY')&&!!RESEND_API_KEY,
    'site_domain'=>defined('SITE_DOMAIN')?SITE_DOMAIN:null
  ],
  'next_step'=>$res['ok']?'Resend is working. If forms do not email, the page is bypassing capture.php.':'Read resend.body for domain/key/from-address error.'
],JSON_PRETTY_PRINT);
