<?php
/**
 * V11.4 Builder Pipeline Builder
 * Upload: /public_html/lead-engine/build-builder-pipeline.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb114($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$outreach=sb114('GET','builder_intro_outreach?select=*&status=in.(sent,approved)&routed_to_pipeline=neq.true&order=match_score.desc&limit=200')['data'];
$matches=sb114('GET','builder_opportunity_matches?select=*&status=in.(introduced,approved,contacted)&routed_to_pipeline=neq.true&order=match_score.desc&limit=200')['data'];

$created=[];$errors=[];$seen=[];

function add_pipeline114($source,$r,&$created,&$errors,&$seen){
  $matchId=$r['match_id']??($r['id']??'');
  if(!$matchId||isset($seen[$matchId]))return;
  $seen[$matchId]=true;

  $score=(int)($r['match_score']??0);
  $prob=$score>=85?35:($score>=65?25:15);
  $stage=($source==='outreach' && ($r['status']??'')==='sent')?'intro_sent':'new';

  $payload=[[
    'opportunity_id'=>$r['opportunity_id']??null,
    'match_id'=>$matchId,
    'outreach_id'=>$source==='outreach'?($r['id']??null):null,
    'builder_contact_id'=>$r['builder_contact_id']??null,
    'builder_name'=>$r['builder_name']??'',
    'company'=>$r['company']??'',
    'phone'=>$r['builder_phone']??($r['phone']??''),
    'email'=>$r['builder_email']??($r['email']??''),
    'opportunity_address'=>$r['opportunity_address']??'',
    'opportunity_town'=>$r['opportunity_town']??'',
    'opportunity_type'=>$r['opportunity_type']??'',
    'pipeline_stage'=>$stage,
    'deal_probability'=>$prob,
    'next_step'=>$stage==='intro_sent'?'Wait for response, then follow up in 3 business days.':'Review and decide intro/follow-up.',
    'next_followup_at'=>date('c',strtotime('+3 weekdays 10:00')),
    'last_contact_at'=>$stage==='intro_sent'?date('c'):null,
    'notes'=>'Created from builder '.$source.' workflow.',
    'raw_payload'=>$r,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb114('POST','builder_pipeline',$payload);
  if($res['ok']){
    $created[]=['source'=>$source,'builder'=>$payload[0]['builder_name'],'property'=>$payload[0]['opportunity_address']];
    if($source==='outreach')sb114('PATCH','builder_intro_outreach?id=eq.'.rawurlencode($r['id']),['routed_to_pipeline'=>true,'updated_at'=>date('c')]);
    if(!empty($matchId))sb114('PATCH','builder_opportunity_matches?id=eq.'.rawurlencode($matchId),['routed_to_pipeline'=>true,'updated_at'=>date('c')]);
  }else $errors[]=['source'=>$source,'http'=>$res['http'],'body'=>$res['body']];
}

foreach($outreach as $r){if(is_array($r))add_pipeline114('outreach',$r,$created,$errors,$seen);}
foreach($matches as $r){if(is_array($r))add_pipeline114('match',$r,$created,$errors,$seen);}

echo json_encode(['success'=>empty($errors),'created_count'=>count($created),'created'=>$created,'errors'=>$errors],JSON_PRETTY_PRINT);
?>