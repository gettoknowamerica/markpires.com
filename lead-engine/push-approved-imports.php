<?php
/**
 * V12.1 Push Approved Compliant Imports
 * Upload: /public_html/lead-engine/push-approved-imports.php
 *
 * Pushes approved + call_eligible rows into hunter_queue or leads.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb121p($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS=json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$rows=sb121p('GET','compliant_lead_imports?select=*&approval_status=eq.approved&call_eligible=eq.true&dnc_status=eq.clear&limit=100')['data'];
$pushed=[];$errors=[];$skipped=[];

foreach($rows as $r){
  if(empty($r['phone'])){$skipped[]=['id'=>$r['id'],'reason'=>'missing phone'];continue;}

  if(in_array(($r['lead_type']??''),['builder','developer','investor'],true)){
    $payload=[[
      'owner_name'=>$r['name']??'',
      'phone'=>$r['phone']??'',
      'email'=>$r['email']??'',
      'address'=>$r['address']??'',
      'town'=>$r['town']??'',
      'hunter_score'=>(int)($r['lead_score']??70),
      'status'=>'review',
      'source'=>'compliant_lead_imports',
      'reason'=>'Approved compliant import: '.($r['notes']??''),
      'raw_payload'=>$r,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];
    $res=sb121p('POST','hunter_queue',$payload);
  } else {
    $payload=[[
      'name'=>$r['name']??'',
      'phone'=>$r['phone']??'',
      'email'=>$r['email']??'',
      'address'=>$r['address']??'',
      'town'=>$r['town']??'',
      'type'=>$r['lead_type']??'',
      'source'=>'compliant_lead_imports',
      'lead_score'=>(int)($r['lead_score']??70),
      'route'=>'mark_priority',
      'raw_payload'=>$r,
      'created_at'=>date('c')
    ]];
    $res=sb121p('POST','leads',$payload);
  }

  if($res['ok']){
    $pushed[]=$r['id'];
    sb121p('PATCH','compliant_lead_imports?id=eq.'.rawurlencode($r['id']),['approval_status'=>'imported','updated_at'=>date('c')]);
  } else $errors[]=['id'=>$r['id'],'http'=>$res['http'],'body'=>$res['body']];
}

echo json_encode(['success'=>empty($errors),'pushed'=>count($pushed),'skipped'=>$skipped,'errors'=>$errors],JSON_PRETTY_PRINT);
?>