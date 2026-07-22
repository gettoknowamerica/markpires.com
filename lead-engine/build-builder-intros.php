<?php
/**
 * V11.3 Builder Intro Draft Builder
 * Upload: /public_html/lead-engine/build-builder-intros.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb113($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function first_name113($name){
  $parts=preg_split('/\s+/',trim((string)$name));
  return $parts[0] ?? '';
}

$matches=sb113('GET','builder_opportunity_matches?select=*&status=in.(review,approved)&outreach_created=neq.true&order=match_score.desc&limit=100')['data'];
$created=[];$skipped=[];$errors=[];

foreach($matches as $m){
  if(!is_array($m) || empty($m['id'])) continue;

  $raw=is_array($m['raw_payload']??null)?$m['raw_payload']:[];
  $opp=is_array($raw['opportunity']??null)?$raw['opportunity']:[];
  $builder=is_array($raw['builder']??null)?$raw['builder']:[];

  $builderName=$m['builder_name']??($builder['name']??'');
  $builderFirst=first_name113($builderName) ?: 'there';
  $company=$m['company']??($builder['company']??'');
  $address=$m['opportunity_address']??($opp['address']??'');
  $town=$m['opportunity_town']??($opp['town']??'');
  $type=$m['opportunity_type']??($opp['opportunity_type']??'opportunity');
  $score=(int)($m['match_score']??0);
  $reason=$m['reason']??'';
  $ownerName=$opp['owner_name']??'';
  $ownerPhone=$opp['phone']??'';

  $subject="Possible {$town} {$type} opportunity";
  $body="<p>Hi ".htmlspecialchars($builderFirst).",</p>"
    ."<p>Mark Pires here. I came across a possible <strong>".htmlspecialchars($type)."</strong> opportunity in <strong>".htmlspecialchars($town)."</strong> that may fit what you look for.</p>"
    ."<p><strong>Property:</strong> ".htmlspecialchars($address)."<br>"
    ."<strong>Why it matched:</strong> ".htmlspecialchars($reason)."</p>"
    ."<p>I am not blasting this out. I am reviewing a short list of builder/developer opportunities and wanted to see if this type of property is worth a closer look for you.</p>"
    ."<p>If yes, reply here or call/text me at 203-247-2655.</p>"
    ."<p>Mark Pires<br>Coldwell Banker Realty<br>Discover CT</p>";

  $sms="Hi {$builderFirst}, Mark Pires here. I found a possible {$town} {$type} opportunity that may fit your profile. Worth a look? Reply here or call/text 203-247-2655.";

  $payload=[[
    'match_id'=>$m['id'],
    'opportunity_id'=>$m['opportunity_id']??null,
    'builder_contact_id'=>$m['builder_contact_id']??null,
    'builder_name'=>$builderName,
    'company'=>$company,
    'builder_email'=>$m['email']??($builder['email']??''),
    'builder_phone'=>$m['phone']??($builder['phone']??''),
    'opportunity_address'=>$address,
    'opportunity_town'=>$town,
    'opportunity_type'=>$type,
    'owner_name'=>$ownerName,
    'owner_phone'=>$ownerPhone,
    'match_score'=>$score,
    'intro_subject'=>$subject,
    'intro_body'=>$body,
    'sms_body'=>$sms,
    'status'=>'draft',
    'raw_payload'=>$m,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $r=sb113('POST','builder_intro_outreach',$payload);
  if($r['ok']){
    sb113('PATCH','builder_opportunity_matches?id=eq.'.rawurlencode($m['id']),['outreach_created'=>true,'updated_at'=>date('c')]);
    $created[]=['match_id'=>$m['id'],'builder'=>$builderName,'opportunity'=>$address];
  } else {
    $errors[]=['match_id'=>$m['id'],'http'=>$r['http'],'body'=>$r['body']];
  }
}

echo json_encode(['success'=>empty($errors),'created_count'=>count($created),'created'=>$created,'skipped'=>$skipped,'errors'=>$errors],JSON_PRETTY_PRINT);
?>