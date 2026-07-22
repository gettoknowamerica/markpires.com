<?php
/**
 * Future Seller Pipeline Builder V5 Restore
 * Upload to: /public_html/lead-engine/build-future-sellers.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb_fs($method,$endpoint,$payload=null){
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
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function norm_phone_fs($phone){
  $d=preg_replace('/\D+/','',(string)$phone);
  if(strlen($d)===11 && substr($d,0,1)==='1') $d=substr($d,1);
  return $d;
}

function bucket_fs($date){
  if(!$date) return 'no_date';
  $days=floor((strtotime($date)-time())/86400);
  if($days<=30) return '0_30';
  if($days<=90) return '31_90';
  if($days<=180) return '91_180';
  return '180_plus';
}

function infer_followup_from_text($text){
  $b=strtolower((string)$text);
  if(str_contains($b,'spring')) return date('c',strtotime('next March 15 9:00'));
  if(str_contains($b,'summer')) return date('c',strtotime('next June 15 9:00'));
  if(str_contains($b,'fall')||str_contains($b,'autumn')) return date('c',strtotime('next September 15 9:00'));
  if(str_contains($b,'winter')) return date('c',strtotime('next January 15 9:00'));
  if(str_contains($b,'6 month')) return date('c',strtotime('+6 months 9:00'));
  if(str_contains($b,'3 month')) return date('c',strtotime('+3 months 9:00'));
  if(str_contains($b,'next year')) return date('c',strtotime('+9 months 9:00'));
  if(str_contains($b,'few months')) return date('c',strtotime('+90 days 9:00'));
  return null;
}

function exists_fs($phone,$relatedType,$relatedId){
  if($relatedId){
    $res=sb_fs('GET','future_seller_pipeline?select=id&related_type=eq.'.rawurlencode($relatedType).'&related_id=eq.'.rawurlencode($relatedId).'&limit=1');
    if(!empty($res['data'])) return true;
  }
  if($phone){
    $res=sb_fs('GET','future_seller_pipeline?select=id&phone=eq.'.rawurlencode($phone).'&status=in.(active,queued,contacted)&limit=1');
    if(!empty($res['data'])) return true;
  }
  return false;
}

$created=[]; $skipped=[];

$outcomes=sb_fs('GET','cold_call_outcomes?select=*&or=(future_seller.eq.true,outcome.eq.future_seller)&order=created_at.desc&limit=300')['data'];

foreach($outcomes as $o){
  if(!is_array($o)) continue;
  $phone=norm_phone_fs($o['phone']??'');
  $rid=(string)($o['id']??'');

  if(exists_fs($phone,'cold_call_outcome',$rid)){
    $skipped[]=['name'=>$o['owner_name']??'', 'reason'=>'exists'];
    continue;
  }

  $text=($o['timeline']??'').' '.($o['notes']??'').' '.($o['jessica_summary']??'').' '.($o['transcript']??'');
  $follow=$o['next_followup_at'] ?: infer_followup_from_text($text) ?: date('c',strtotime('+90 days 9:00'));
  $score=(int)($o['lead_score']??0);

  $payload=[[
    'related_type'=>'cold_call_outcome',
    'related_id'=>$rid,
    'homeowner_id'=>$o['homeowner_id']??null,
    'call_id'=>$o['call_id']??'',
    'name'=>$o['owner_name']??'',
    'phone'=>$phone,
    'email'=>$o['email']??'',
    'address'=>$o['address']??'',
    'town'=>$o['town']??'',
    'source'=>$o['source']??'jessica_cold_call',
    'expected_timeline'=>$o['timeline']?:'Future seller',
    'next_followup_at'=>$follow,
    'followup_bucket'=>bucket_fs($follow),
    'lead_score'=>$score,
    'priority'=>$score>=90?'hot':($score>=75?'high':'active'),
    'status'=>'active',
    'recommended_action'=>$score>=90?'Call personally before Jessica follow-up.':'Queue structured follow-up.',
    'motivation'=>$o['motivation']??'',
    'notes'=>$o['notes']??'',
    'jessica_summary'=>$o['jessica_summary']??'',
    'raw_payload'=>$o,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb_fs('POST','future_seller_pipeline',$payload);
  $created[]=['name'=>$o['owner_name']??'','ok'=>$res['ok'],'http'=>$res['http']];
}

echo json_encode([
  'success'=>true,
  'summary'=>[
    'created'=>count(array_filter($created,fn($x)=>!empty($x['ok']))),
    'attempted'=>count($created),
    'skipped'=>count($skipped)
  ],
  'created'=>$created,
  'skipped'=>array_slice($skipped,0,50)
],JSON_PRETTY_PRINT);
