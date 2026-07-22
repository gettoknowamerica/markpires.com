<?php
/**
 * V12.5 Cron Health Check
 * Upload: /public_html/lead-engine/cron-health-check.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode([
    'success' => false,
    'error' => 'Invalid key',
    'hint' => 'Use AFTER_HOURS_CRON_KEY from /lead-engine/config.php',
    'received_key_length' => strlen($key),
    'expected_key_length' => defined('AFTER_HOURS_CRON_KEY') ? strlen(AFTER_HOURS_CRON_KEY) : 0
  ], JSON_PRETTY_PRINT);
  exit;
}

function sb125($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
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
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$payload=[[
  'job_name'=>'Cron Health Check',
  'job_url'=>'cron-health-check.php',
  'ok'=>true,
  'http_status'=>200,
  'response_summary'=>'Cron key accepted and system reachable.',
  'raw_response'=>['server_time'=>date('c'),'key_length'=>strlen(AFTER_HOURS_CRON_KEY)],
  'created_at'=>date('c')
]];

$log=sb125('POST','cron_run_audit',$payload);

echo json_encode([
  'success'=>true,
  'message'=>'Cron key is correct. Hostinger cron can reach the site.',
  'server_time'=>date('c'),
  'key_length'=>strlen(AFTER_HOURS_CRON_KEY),
  'audit_logged'=>$log['ok']
], JSON_PRETTY_PRINT);
?>