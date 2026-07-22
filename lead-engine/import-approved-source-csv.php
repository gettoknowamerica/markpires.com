<?php
/**
 * V12.2 CSV Safe Source Importer
 * Upload: /public_html/lead-engine/import-approved-source-csv.php
 *
 * Upload CSV by placing it at:
 * /public_html/lead-engine/imports/approved-source.csv
 *
 * Run:
 * /lead-engine/import-approved-source-csv.php?key=YOUR_KEY&file=approved-source.csv
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb122($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function score122($row){
  $score=50;
  if(($row['consent_status']??'')==='opt_in')$score+=30;
  if(($row['dnc_status']??'')==='clear')$score+=15;
  if(!empty($row['phone']))$score+=10;
  if(!empty($row['address']))$score+=10;
  if(in_array(($row['lead_type']??''),['seller','builder','developer'],true))$score+=8;
  return min(100,$score);
}

$file=basename($_GET['file']??'approved-source.csv');
$path=__DIR__.'/imports/'.$file;
if(!file_exists($path)){
  echo json_encode(['success'=>false,'error'=>'CSV not found','expected_path'=>$path]); exit;
}

$fh=fopen($path,'r');
$headers=fgetcsv($fh);
if(!$headers){echo json_encode(['success'=>false,'error'=>'CSV missing header']);exit;}
$headers=array_map('trim',$headers);

$rows=[];$errors=[];$count=0;
while(($data=fgetcsv($fh))!==false){
  $row=[];
  foreach($headers as $i=>$h){$row[$h]=$data[$i]??'';}
  $sourceType=$row['source_type'] ?: 'csv_import';
  $consent=$row['consent_status'] ?: 'unknown';
  $dnc=$row['dnc_status'] ?: 'unchecked';

  $callEligible=($dnc==='clear' && in_array($consent,['opt_in','business_contact'],true) && !empty($row['phone']));
  $smsEligible=($dnc==='clear' && $consent==='opt_in' && !empty($row['phone']));
  $emailEligible=(!empty($row['email']) && in_array($consent,['opt_in','business_contact','unknown'],true));

  $payload=[[
    'source_name'=>$row['source_name'] ?: $file,
    'source_type'=>$sourceType,
    'lead_type'=>$row['lead_type'] ?? '',
    'name'=>$row['name'] ?? '',
    'phone'=>$row['phone'] ?? '',
    'email'=>$row['email'] ?? '',
    'address'=>$row['address'] ?? '',
    'town'=>$row['town'] ?? '',
    'state'=>$row['state'] ?: 'CT',
    'market'=>$row['market'] ?? '',
    'consent_status'=>$consent,
    'dnc_status'=>$dnc,
    'approval_status'=>$callEligible ? 'approved' : 'review',
    'lead_score'=>score122($row),
    'call_eligible'=>$callEligible,
    'sms_eligible'=>$smsEligible,
    'email_eligible'=>$emailEligible,
    'notes'=>($row['notes'] ?? '').' Imported by V12.2 safe source importer.',
    'raw_payload'=>$row,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $r=sb122('POST','compliant_lead_imports',$payload);
  if($r['ok']){$rows[]=$payload[0];$count++;}
  else $errors[]=['row'=>$row,'http'=>$r['http'],'body'=>$r['body']];
}
fclose($fh);

echo json_encode([
  'success'=>empty($errors),
  'imported'=>$count,
  'errors'=>$errors,
  'message'=>'Imported to compliant_lead_imports. Only opt-in/business_contact + DNC clear rows become call eligible.'
],JSON_PRETTY_PRINT);
?>