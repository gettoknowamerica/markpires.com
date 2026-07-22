<?php
/**
 * V13.7 Jessica Master Control Runner
 * Upload: /public_html/lead-engine/run-master-control.php
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

  function sb137($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }

  function call_local137($path,$key){
    $url='https://markpires.com'.$path.'?key='.rawurlencode($key);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>55]);
    $body=curl_exec($ch);
    $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    $data=json_decode($body,true);
    return ['url'=>$url,'ok'=>$http>=200&&$http<300 && is_array($data) && (($data['success']??true)!==false),'http'=>$http,'error'=>$err,'data'=>is_array($data)?$data:null,'body'=>is_array($data)?null:substr((string)$body,0,1000)];
  }

  $mode=$_GET['mode']??'morning';

  $steps=[
    ['Source Hunter','/lead-engine/build-source-hunter.php'],
    ['Contact Acquisition','/lead-engine/build-contact-acquisition.php'],
    ['Approved Contact Pipeline','/lead-engine/build-approved-contact-pipeline.php'],
    ['Queue Intelligence','/lead-engine/build-queue-intelligence.php'],
    ['Conversation Learning','/lead-engine/build-conversation-learning.php'],
    ['Voice Intelligence','/lead-engine/build-voice-intelligence.php'],
    ['Revenue Forecast','/lead-engine/build-revenue-forecast.php'],
    ['Listing Intelligence','/lead-engine/build-listing-intelligence.php'],
    ['Opportunity Pipeline','/lead-engine/build-opportunity-pipeline.php'],
    ['Creative Review','/lead-engine/build-creative-review.php'],
    ['Asset Vault','/lead-engine/build-asset-vault.php'],
    ['Morning Executive Brief','/lead-engine/build-morning-executive-brief.php']
  ];

  if($mode==='light'){
    $steps=[
      ['Approved Contact Pipeline','/lead-engine/build-approved-contact-pipeline.php'],
      ['Queue Intelligence','/lead-engine/build-queue-intelligence.php'],
      ['Voice Intelligence','/lead-engine/build-voice-intelligence.php'],
      ['Opportunity Pipeline','/lead-engine/build-opportunity-pipeline.php'],
      ['Listing Intelligence','/lead-engine/build-listing-intelligence.php']
    ];
  }

  $results=[]; $ok=0; $fail=0;
  foreach($steps as $s){
    $r=call_local137($s[1],$key);
    $results[]=['step'=>$s[0],'path'=>$s[1],'ok'=>$r['ok'],'http'=>$r['http'],'summary'=>$r['data']??$r['body'],'error'=>$r['error']];
    if($r['ok'])$ok++; else $fail++;
    usleep(250000);
  }

  $brief="V13.7 JESSICA MASTER CONTROL\\n========================================\\n\\n";
  $brief.="Mode:             {$mode}\\n";
  $brief.="Steps Attempted:  ".count($steps)."\\n";
  $brief.="Steps OK:         {$ok}\\n";
  $brief.="Steps Failed:     {$fail}\\n\\n";
  $brief.="RUN RESULTS\\n----------------------------------------\\n";
  foreach($results as $i=>$r){
    $brief.=($i+1).". ".$r['step']." — ".($r['ok']?'OK':'FAILED')." — HTTP ".$r['http']."\\n";
  }
  $brief.="\\nNEXT CHECK\\n----------------------------------------\\n";
  $brief.="Open Master Control, Opportunity Pipeline, Listing Intelligence, and Voice Intelligence.\\n";

  $payload=[[
    'run_date'=>date('Y-m-d'),'run_name'=>'master_control_'.$mode,'ok'=>$fail===0,'steps_attempted'=>count($steps),'steps_ok'=>$ok,'steps_failed'=>$fail,
    'results'=>$results,'briefing_text'=>$brief,'created_at'=>date('c')
  ]];
  $res=sb137('POST','jessica_master_runs',$payload);

  echo json_encode(['success'=>$fail===0,'mode'=>$mode,'steps_attempted'=>count($steps),'steps_ok'=>$ok,'steps_failed'=>$fail,'briefing'=>$brief,'results'=>$results,'supabase_http'=>$res['http']],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>