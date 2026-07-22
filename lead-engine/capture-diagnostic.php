<?php
/**
 * V22.0 Capture Diagnostic
 * Upload: /public_html/lead-engine/capture-diagnostic.php
 * Run: /lead-engine/capture-diagnostic.php?key=YOUR_CRON_KEY
 * It posts a safe fake lead into your real capture.php and returns all service flags.
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$host='https://'.(defined('SITE_DOMAIN')?SITE_DOMAIN:'markpires.com');
$payload=[
  'type'=>'diagnostic_test',
  'tag'=>'v22_capture_diagnostic',
  'name'=>'Goliath Test Lead',
  'email'=>defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com',
  'phone'=>'2032472655',
  'address'=>'1508 Post Road, Fairfield CT',
  'town'=>'Fairfield',
  'timeline'=>'ASAP',
  'goal'=>'System diagnostic only',
  'message'=>'V22 diagnostic test. Safe to ignore. This verifies capture.php -> Supabase -> HubSpot -> Resend -> Retell routing.',
  'estimated_value'=>'750000',
  'source'=>'markpires.com',
  'page_url'=>$host.'/lead-engine/capture-diagnostic.php'
];
$ch=curl_init($host.'/lead-engine/capture.php');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>45]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
$decoded=json_decode($body,true);
echo json_encode(['success'=>$http>=200&&$http<300&&is_array($decoded)&&($decoded['success']??false),'http'=>$http,'curl_error'=>$err,'capture_response'=>is_array($decoded)?$decoded:$body],JSON_PRETTY_PRINT);
