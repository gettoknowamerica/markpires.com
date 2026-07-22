<?php
/**
 * V12.1 Compliant Import Queue Builder
 * Upload: /public_html/lead-engine/build-compliant-import-queue.php
 *
 * Converts V12 discovery opportunities into review-ready import tasks.
 * Does not scrape private data and does not call unapproved contacts.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb121($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$opps=sb121('GET','discovery_opportunity_queue?select=*&status=eq.new&order=priority_score.desc&limit=500')['data'];
$created=[];$errors=[];

foreach($opps as $o){
  if(!is_array($o))continue;
  $payload=[[
    'source_name'=>'V12 Discovery Intelligence',
    'source_type'=>'research_plan',
    'lead_type'=>$o['opportunity_type']??'',
    'town'=>$o['town']??'',
    'market'=>$o['market']??'',
    'consent_status'=>'unknown',
    'dnc_status'=>'unchecked',
    'approval_status'=>'review',
    'lead_score'=>(int)($o['priority_score']??0),
    'call_eligible'=>false,
    'sms_eligible'=>false,
    'email_eligible'=>false,
    'notes'=>'Research target only. Import actual lead data from approved/opt-in/vendor/public-compliant source, then review DNC/consent before calling.',
    'raw_payload'=>$o,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb121('POST','compliant_lead_imports',$payload);
  if($r['ok']){
    $created[]=$o['town'].' '.$o['opportunity_type'];
    sb121('PATCH','discovery_opportunity_queue?id=eq.'.rawurlencode($o['id']),['status'=>'import_review_created','updated_at'=>date('c')]);
  } else $errors[]=['id'=>$o['id']??'', 'http'=>$r['http'], 'body'=>$r['body']];
}

echo json_encode([
  'success'=>empty($errors),
  'created_count'=>count($created),
  'created'=>$created,
  'errors'=>$errors,
  'message'=>'Review queue created. Calls remain blocked until approved/DNC-cleared.'
],JSON_PRETTY_PRINT);
?>