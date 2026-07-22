<?php
/**
 * V10.6 Guarded Hunter Runner
 * Upload: /public_html/lead-engine/run-hunter-cron.php
 *
 * Run:
 * /lead-engine/run-hunter-cron.php?key=YOUR_KEY
 *
 * This wraps run-hunter-calls.php with guardrails:
 * - hunter_enabled must be true
 * - local call window enforced
 * - daily max enforced
 * - per-run max enforced
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb106($method,$endpoint,$payload=null){
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
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function get_guardrails106(){
  $rows=sb106('GET','hunter_guardrails?select=*')['data'];
  $g=[];
  foreach($rows as $r){$g[$r['guardrail_key']]=$r['guardrail_value'];}
  return $g;
}
function bool106($v){return in_array(strtolower((string)$v),['true','1','yes','on'],true);}
function blocked106($reason,$g){
  sb106('POST','hunter_call_runs',[[
    'run_type'=>'guarded_cron',
    'max_calls'=>(int)($g['max_calls_per_run']??0),
    'attempted'=>0,
    'called'=>0,
    'skipped'=>0,
    'errors'=>0,
    'status'=>'blocked',
    'blocked_reason'=>$reason,
    'guardrail_status'=>$g,
    'results'=>[],
    'created_at'=>date('c')
  ]]);
  echo json_encode(['success'=>false,'blocked'=>true,'reason'=>$reason,'guardrails'=>$g],JSON_PRETTY_PRINT);
  exit;
}

$g=get_guardrails106();
$tz=$g['timezone']??'America/New_York';
date_default_timezone_set($tz);

if(!bool106($g['hunter_enabled']??'false')) blocked106('hunter_enabled is false',$g);

$hour=(int)date('G');
$start=(int)($g['allowed_start_hour']??10);
$end=(int)($g['allowed_end_hour']??17);
if($hour<$start || $hour>=$end) blocked106('outside allowed call window',$g);

$today=date('Y-m-d').'T00:00:00';
$runs=sb106('GET','hunter_call_runs?select=called&created_at=gte.'.rawurlencode($today).'&status=eq.complete&limit=1000')['data'];
$calledToday=0;
foreach($runs as $r){$calledToday+=(int)($r['called']??0);}
$maxDay=(int)($g['max_calls_per_day']??15);
if($calledToday>=$maxDay) blocked106('daily call limit reached',$g);

$maxRun=max(1,(int)($g['max_calls_per_run']??3));
$remaining=max(0,$maxDay-$calledToday);
$max=min($maxRun,$remaining);
if($max<=0) blocked106('no remaining calls available today',$g);

$host=$_SERVER['HTTP_HOST']??'markpires.com';
$url='https://'.$host.'/lead-engine/run-hunter-calls.php?key='.rawurlencode(AFTER_HOURS_CRON_KEY).'&max='.$max;
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>60]);
$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
$data=json_decode($body,true);

echo json_encode([
  'success'=>$http>=200&&$http<300&&is_array($data)&&!empty($data['success']),
  'guardrails'=>$g,
  'called_today_before_run'=>$calledToday,
  'max_this_run'=>$max,
  'http'=>$http,
  'error'=>$err,
  'run'=>is_array($data)?$data:null,
  'raw'=>is_array($data)?null:$body
],JSON_PRETTY_PRINT);
?>