<?php
/**
 * V12.15.1 Jessica Clean List Intake
 * Upload: /public_html/lead-engine/jessica-clean-list-intake.php
 *
 * Accepts GET/POST JSON:
 * {
 *   "batch_name": "Jessica Morning Clean List",
 *   "items": [{ "name":"", "phone":"", "email":"", "lead_type":"seller", "town":"Greenwich", ... }]
 * }
 *
 * Also can generate a test batch:
 * /lead-engine/jessica-clean-list-intake.php?key=YOUR_KEY&demo=1
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? $_POST['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb1511($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function clean_phone1511($p){
  $p=preg_replace('/[^0-9]/','',(string)$p);
  if(strlen($p)===11 && $p[0]==='1')$p=substr($p,1);
  return $p;
}
function norm_email1511($e){ return strtolower(trim((string)$e)); }
function cleanliness1511($x){
  $score=0;
  if(!empty($x['name']))$score+=10;
  if(!empty($x['phone']))$score+=25;
  if(!empty($x['email']))$score+=15;
  if(!empty($x['address']))$score+=15;
  if(!empty($x['town']))$score+=10;
  if(!empty($x['lead_type']))$score+=10;
  if(!empty($x['timeline']))$score+=5;
  if(!empty($x['motivation']))$score+=5;
  if(($x['dnc_status']??'')==='clear')$score+=10;
  if(in_array(($x['consent_status']??''),['opt_in','business_contact'],true))$score+=10;
  return min(100,$score);
}
function leadscore1511($x,$clean){
  $score=$clean;
  $type=strtolower((string)($x['lead_type']??''));
  $timeline=strtolower((string)($x['timeline']??''));
  $mot=strtolower((string)($x['motivation']??''));
  $val=(float)($x['estimated_value']??0);
  if(str_contains($type,'seller'))$score+=8;
  if(str_contains($type,'relocation')||str_contains($type,'buyer'))$score+=5;
  if(str_contains($type,'builder')||str_contains($type,'developer'))$score+=6;
  if($val>=600000)$score+=10;
  if($val>=1000000)$score+=8;
  if(str_contains($timeline,'now')||str_contains($timeline,'30')||str_contains($timeline,'soon'))$score+=12;
  if(str_contains($mot,'sell')||str_contains($mot,'move')||str_contains($mot,'downsize')||str_contains($mot,'relocat'))$score+=10;
  return max(0,min(100,$score));
}

$raw=file_get_contents('php://input');
$json=$raw?json_decode($raw,true):null;

if(isset($_GET['demo'])){
  $json=[
    'batch_name'=>'Jessica Demo Clean List',
    'items'=>[
      ['name'=>'Demo Seller','phone'=>'2035551212','email'=>'demo@example.com','lead_type'=>'seller','town'=>'Greenwich','estimated_value'=>1200000,'timeline'=>'soon','motivation'=>'may sell','consent_status'=>'opt_in','dnc_status'=>'clear','notes'=>'Demo only'],
      ['name'=>'Demo Relocation','phone'=>'2035552323','email'=>'move@example.com','lead_type'=>'relocation','town'=>'Darien','estimated_value'=>900000,'timeline'=>'60 days','motivation'=>'moving from NYC','consent_status'=>'opt_in','dnc_status'=>'clear','notes'=>'Demo only'],
      ['name'=>'Research Only','lead_type'=>'seller','town'=>'Westport','estimated_value'=>1500000,'timeline'=>'unknown','motivation'=>'research target','consent_status'=>'unknown','dnc_status'=>'unchecked','notes'=>'No phone/email']
    ]
  ];
}

if(!$json || !is_array($json)){
  $json=[
    'batch_name'=>$_POST['batch_name']??$_GET['batch_name']??'Jessica Clean List',
    'items'=>[]
  ];
  if(!empty($_POST['items']))$json['items']=json_decode($_POST['items'],true) ?: [];
}

$items=$json['items']??[];
if(!is_array($items))$items=[];

$batchPayload=[[
  'batch_name'=>$json['batch_name']??'Jessica Clean List',
  'source'=>$json['source']??'jessica',
  'total_submitted'=>count($items),
  'notes'=>$json['notes']??'Clean list submitted by Jessica/system.',
  'raw_payload'=>$json,
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$batchRes=sb1511('POST','jessica_clean_list_batches',$batchPayload);
$batchId=$batchRes['data'][0]['id']??null;
if(!$batchRes['ok']||!$batchId){ echo json_encode(['success'=>false,'error'=>'batch insert failed','details'=>$batchRes],JSON_PRETTY_PRINT); exit; }

$accepted=0;$skipped=0;$dupes=0;$rejected=0;$pushed=0;$results=[];

foreach($items as $item){
  if(!is_array($item))continue;
  $phone=clean_phone1511($item['phone']??'');
  $email=norm_email1511($item['email']??'');
  $item['phone']=$phone;
  $item['email']=$email;

  $duplicateOf='';
  if($phone){
    $d=sb1511('GET','compliant_lead_imports?select=id,phone&phone=eq.'.rawurlencode($phone).'&limit=1');
    if(!empty($d['data'][0]['id']))$duplicateOf=$d['data'][0]['id'];
  }
  if(!$duplicateOf && $email){
    $d=sb1511('GET','compliant_lead_imports?select=id,email&email=eq.'.rawurlencode($email).'&limit=1');
    if(!empty($d['data'][0]['id']))$duplicateOf=$d['data'][0]['id'];
  }

  $clean=cleanliness1511($item);
  $leadScore=leadscore1511($item,$clean);
  $decision='review';
  $reason='Needs Mark/compliance review.';
  if($duplicateOf){$decision='duplicate';$reason='Duplicate existing compliant import.';$dupes++;}
  elseif(!$phone && !$email){$decision='research_only';$reason='No usable phone/email; keep as research only.';$skipped++;}
  elseif($clean<45){$decision='needs_cleanup';$reason='Insufficient clean data.';$skipped++;}
  elseif(in_array(($item['consent_status']??'unknown'),['opt_in','business_contact'],true) && ($item['dnc_status']??'unchecked')==='clear'){
    $decision='accepted';$reason='Clean and eligible for approved import review.';$accepted++;
  } else {$decision='review';$reason='Clean enough to review, but not call eligible until consent/DNC approval.';$accepted++;}

  $row=[[
    'batch_id'=>$batchId,
    'source'=>$json['source']??'jessica',
    'source_ref'=>$item['source_ref']??'',
    'lead_type'=>$item['lead_type']??'',
    'name'=>$item['name']??'',
    'phone'=>$phone,
    'email'=>$email,
    'address'=>$item['address']??'',
    'town'=>$item['town']??'',
    'state'=>$item['state']??'CT',
    'market'=>$item['market']??'',
    'estimated_value'=>(float)($item['estimated_value']??0),
    'timeline'=>$item['timeline']??'',
    'motivation'=>$item['motivation']??'',
    'notes'=>$item['notes']??'',
    'consent_status'=>$item['consent_status']??'unknown',
    'dnc_status'=>$item['dnc_status']??'unchecked',
    'approval_status'=>$item['approval_status']??'review',
    'cleanliness_score'=>$clean,
    'lead_score'=>$leadScore,
    'duplicate_of'=>$duplicateOf,
    'decision'=>$decision,
    'decision_reason'=>$reason,
    'raw_payload'=>$item,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $ir=sb1511('POST','jessica_clean_list_items',$row);

  if(in_array($decision,['accepted','review'],true) && !$duplicateOf){
    $callEligible=($item['dnc_status']??'')==='clear' && in_array(($item['consent_status']??''),['opt_in','business_contact'],true) && !empty($phone);
    $ci=[[
      'source_name'=>$json['batch_name']??'Jessica Clean List',
      'source_type'=>'jessica_clean_list',
      'lead_type'=>$item['lead_type']??'',
      'name'=>$item['name']??'',
      'phone'=>$phone,
      'email'=>$email,
      'address'=>$item['address']??'',
      'town'=>$item['town']??'',
      'state'=>$item['state']??'CT',
      'market'=>$item['market']??'',
      'consent_status'=>$item['consent_status']??'unknown',
      'dnc_status'=>$item['dnc_status']??'unchecked',
      'approval_status'=>$callEligible?'approved':'review',
      'lead_score'=>$leadScore,
      'call_eligible'=>$callEligible,
      'sms_eligible'=>$callEligible && ($item['consent_status']??'')==='opt_in',
      'email_eligible'=>!empty($email),
      'notes'=>'Imported from Jessica Clean List. '.$reason.' '.($item['notes']??''),
      'raw_payload'=>['clean_list_item'=>$row[0]],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];
    $cr=sb1511('POST','compliant_lead_imports',$ci);
    if($cr['ok']){
      $pushed++;
      $compliantId=$cr['data'][0]['id']??'';
      if($ir['ok'] && !empty($ir['data'][0]['id'])){
        sb1511('PATCH','jessica_clean_list_items?id=eq.'.rawurlencode($ir['data'][0]['id']),[
          'pushed_to_compliant_imports'=>true,
          'compliant_import_id'=>$compliantId,
          'updated_at'=>date('c')
        ]);
      }
    } else {
      $results[]=['item'=>$item,'decision'=>$decision,'push_error'=>$cr['body']];
    }
  }

  $results[]=['item'=>$item['name']??$phone??$email,'decision'=>$decision,'score'=>$leadScore,'reason'=>$reason];
}

sb1511('PATCH','jessica_clean_list_batches?id=eq.'.rawurlencode($batchId),[
  'accepted'=>$accepted,
  'skipped'=>$skipped,
  'duplicates'=>$dupes,
  'rejected'=>$rejected,
  'batch_status'=>'processed',
  'updated_at'=>date('c')
]);

echo json_encode([
  'success'=>true,
  'batch_id'=>$batchId,
  'submitted'=>count($items),
  'accepted'=>$accepted,
  'skipped'=>$skipped,
  'duplicates'=>$dupes,
  'pushed_to_compliant_imports'=>$pushed,
  'results'=>$results
],JSON_PRETTY_PRINT);
?>