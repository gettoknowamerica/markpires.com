<?php
/**
 * Cold Call Outcome Processor V4
 * Upload to: /public_html/lead-engine/process-call-outcomes.php
 *
 * URL:
 * https://markpires.com/lead-engine/process-call-outcomes.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb_v4($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>30
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function norm_phone_v4($phone){
  $d=preg_replace('/\D+/','',(string)$phone);
  if(strlen($d)===11 && substr($d,0,1)==='1') $d=substr($d,1);
  return $d;
}

function outcome_from_call($call){
  $blob=strtolower(json_encode($call));
  $outcome='nurture';
  $confidence=50;
  $followup=null;

  if(str_contains($blob,'do not call') || str_contains($blob,'take me off') || str_contains($blob,'remove me')){
    return ['outcome'=>'dnc_request','confidence'=>95,'followup'=>null];
  }
  if(str_contains($blob,'wrong number') || str_contains($blob,'not my house')){
    return ['outcome'=>'wrong_number','confidence'=>90,'followup'=>null];
  }
  if(str_contains($blob,'already listed') || str_contains($blob,'listed with') || str_contains($blob,'have an agent')){
    return ['outcome'=>'already_listed','confidence'=>85,'followup'=>date('c',strtotime('+30 days'))];
  }
  if(!empty($call['appointment_requested']) || str_contains($blob,'appointment') || str_contains($blob,'come out') || str_contains($blob,'meet')){
    return ['outcome'=>'appointment','confidence'=>95,'followup'=>date('c')];
  }
  if(str_contains($blob,'interested') || str_contains($blob,'thinking of selling') || str_contains($blob,'what is it worth')){
    return ['outcome'=>'interested','confidence'=>85,'followup'=>date('c',strtotime('+1 day 9:00'))];
  }
  if(str_contains($blob,'spring') || str_contains($blob,'next year') || str_contains($blob,'few months') || str_contains($blob,'not right now') || str_contains($blob,'later')){
    return ['outcome'=>'future_seller','confidence'=>80,'followup'=>date('c',strtotime('+90 days 9:00'))];
  }
  if(str_contains($blob,'not interested') || str_contains($blob,'never selling') || str_contains($blob,'stop calling')){
    return ['outcome'=>'not_interested','confidence'=>80,'followup'=>null];
  }

  return ['outcome'=>$outcome,'confidence'=>$confidence,'followup'=>date('c',strtotime('+180 days 9:00'))];
}

$limit=max(10,min(300,(int)($_GET['limit'] ?? 100)));
$calls=sb_v4('GET','call_intelligence?select=*&order=created_at.desc&limit='.$limit)['data'];

$created=[];$skipped=[];

foreach($calls as $call){
  $source=strtolower((string)($call['source'] ?? ($call['metadata']['source'] ?? '')));
  $metadata=$call['metadata'] ?? [];
  if(!is_array($metadata)) $metadata=[];

  $isCold = str_contains($source,'batch') || str_contains($source,'homeowner') || str_contains(strtolower(json_encode($metadata)),'homeowner');
  if(!$isCold){
    $skipped[]=['call_id'=>$call['call_id'] ?? '', 'reason'=>'not cold homeowner'];
    continue;
  }

  $callId=$call['call_id'] ?? '';
  if($callId){
    $exists=sb_v4('GET','cold_call_outcomes?select=id&call_id=eq.'.rawurlencode($callId).'&limit=1');
    if(!empty($exists['data'])){
      $skipped[]=['call_id'=>$callId,'reason'=>'exists'];
      continue;
    }
  }

  $o=outcome_from_call($call);
  $phone=norm_phone_v4($call['phone'] ?? $call['to_number'] ?? $call['from_number'] ?? ($metadata['phone'] ?? ''));
  $homeowner=null;
  if($phone){
    $home=sb_v4('GET','homeowner_intelligence?select=*&phone=eq.'.rawurlencode($phone).'&limit=1');
    if(!empty($home['data'][0])) $homeowner=$home['data'][0];
  }

  $payload=[
    'call_id'=>$callId,
    'homeowner_id'=>$homeowner['id'] ?? null,
    'phone'=>$phone,
    'owner_name'=>$call['name'] ?? ($metadata['name'] ?? ($homeowner['owner_name'] ?? '')),
    'email'=>$call['email'] ?? ($metadata['email'] ?? ($homeowner['email'] ?? '')),
    'address'=>$call['address'] ?? ($metadata['address'] ?? ($homeowner['address'] ?? '')),
    'town'=>$call['town'] ?? ($metadata['town'] ?? ($homeowner['town'] ?? '')),
    'source'=>'jessica_cold_call',
    'outcome'=>$o['outcome'],
    'outcome_confidence'=>$o['confidence'],
    'interested'=>in_array($o['outcome'],['interested','appointment'],true),
    'future_seller'=>$o['outcome']==='future_seller',
    'appointment_requested'=>$o['outcome']==='appointment',
    'dnc_request'=>$o['outcome']==='dnc_request',
    'wrong_number'=>$o['outcome']==='wrong_number',
    'next_followup_at'=>$o['followup'],
    'motivation'=>$call['motivation'] ?? '',
    'timeline'=>$metadata['timeline'] ?? '',
    'notes'=>'Auto-classified from Jessica call.',
    'jessica_summary'=>$call['summary'] ?? '',
    'transcript'=>$call['transcript'] ?? '',
    'sentiment'=>$call['sentiment'] ?? '',
    'lead_score'=>(int)($call['lead_score'] ?? 0),
    'raw_payload'=>$call,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];

  $res=sb_v4('POST','cold_call_outcomes',[$payload]);

  if($homeowner && !empty($homeowner['id'])){
    $hiPatch=[
      'last_outcome'=>$o['outcome'],
      'last_outcome_at'=>date('c'),
      'next_followup_at'=>$o['followup'],
      'call_attempts'=>((int)($homeowner['call_attempts'] ?? 0))+1,
      'learning_notes'=>trim(($homeowner['learning_notes'] ?? '')."\n".date('Y-m-d')." outcome: ".$o['outcome']),
      'updated_at'=>date('c')
    ];

    if($o['outcome']==='dnc_request'){
      $hiPatch['dnc_status']='listed';
      $hiPatch['dnc_reason']='Requested removal during Jessica call';
      $hiPatch['status']='dnc_request';
    } elseif($o['outcome']==='wrong_number'){
      $hiPatch['status']='wrong_number';
    } elseif($o['outcome']==='future_seller'){
      $hiPatch['status']='future_seller';
      $hiPatch['priority']='high';
    } elseif(in_array($o['outcome'],['interested','appointment'],true)){
      $hiPatch['status']='interested';
      $hiPatch['priority']='hot';
    }

    sb_v4('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($homeowner['id']),$hiPatch);
  }

  $created[]=['call_id'=>$callId,'outcome'=>$o['outcome'],'ok'=>$res['ok'],'http'=>$res['http']];
}

echo json_encode([
  'success'=>true,
  'summary'=>['created'=>count($created),'skipped'=>count($skipped)],
  'created'=>$created,
  'skipped'=>array_slice($skipped,0,50)
],JSON_PRETTY_PRINT);
