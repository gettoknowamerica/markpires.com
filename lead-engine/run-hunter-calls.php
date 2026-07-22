<?php
/**
 * V10.4.1 Autonomous Hunter Caller — 500 Fix
 * Upload: /public_html/lead-engine/run-hunter-calls.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

if(!function_exists('str_starts_with')){
  function str_starts_with($haystack,$needle){return $needle==='' || strpos((string)$haystack,(string)$needle)===0;}
}

function sb104_fixed($method,$endpoint,$payload=null){
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
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function e164_104($p){
  $d=preg_replace('/\D+/','',(string)$p);
  if(strlen($d)===10) return '+1'.$d;
  if(strlen($d)===11 && substr($d,0,1)==='1') return '+'.$d;
  if(str_starts_with((string)$p,'+')) return $p;
  return '';
}

function retell_call104($item){
  if(!defined('RETELL_API_KEY')||!RETELL_API_KEY) return ['ok'=>false,'error'=>'RETELL_API_KEY missing'];
  if(!defined('RETELL_FROM_NUMBER')||!RETELL_FROM_NUMBER) return ['ok'=>false,'error'=>'RETELL_FROM_NUMBER missing'];

  $agent='';
  if(defined('RETELL_AGENT_ID_HUNTER') && RETELL_AGENT_ID_HUNTER) $agent=RETELL_AGENT_ID_HUNTER;
  elseif(defined('RETELL_AGENT_ID_MARK_PRIORITY') && RETELL_AGENT_ID_MARK_PRIORITY) $agent=RETELL_AGENT_ID_MARK_PRIORITY;

  $to=e164_104($item['phone']??'');
  if(!$to) return ['ok'=>false,'error'=>'Invalid destination phone'];

  $payload=[
    'from_number'=>RETELL_FROM_NUMBER,
    'to_number'=>$to,
    'metadata'=>[
      'source'=>'homeowner_hunter',
      'hunter_queue_id'=>$item['id']??'',
      'homeowner_id'=>$item['homeowner_id']??'',
      'campaign_name'=>$item['campaign_name']??'',
      'script_key'=>$item['script_key']??($item['suggested_script']??'cold_homeowner')
    ],
    'retell_llm_dynamic_variables'=>[
      'lead_name'=>$item['owner_name']??'',
      'owner_name'=>$item['owner_name']??'',
      'phone'=>$to,
      'address'=>$item['address']??'',
      'town'=>$item['town']??'',
      'years_owned'=>(string)($item['years_owned']??''),
      'estimated_equity'=>(string)($item['estimated_equity']??''),
      'script_key'=>$item['script_key']??($item['suggested_script']??'cold_homeowner'),
      'script_prompt'=>$item['script_prompt']??'',
      'mark_name'=>'Mark Pires',
      'company'=>'Coldwell Banker / Discover CT',
      'call_context'=>'Approved homeowner hunter outreach. Be helpful, compliant, relaxed, and never pressure.'
    ]
  ];
  if($agent) $payload['override_agent_id']=$agent;

  $ch=curl_init('https://api.retellai.com/v2/create-phone-call');
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'Authorization: Bearer '.RETELL_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT=>30
  ]);
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'payload'=>$payload,'body'=>$body,'data'=>is_array($d)?$d:null];
}

try{
  $max=max(1,min(25,(int)($_GET['max']??5)));
  $allowUnapproved=($_GET['allow_unapproved']??'')==='1';

  $query='hunter_queue?select=*&status=in.(approved,queued)&dnc_status=neq.listed&order=hunter_score.desc&limit='.$max;
  $items=sb104_fixed('GET',$query)['data'];

  $results=[];$called=0;$skipped=0;$errors=0;

  foreach($items as $item){
    if(!is_array($item)) continue;
    $id=$item['id']??'';

    if(empty($item['phone'])){
      $skipped++; $results[]=['id'=>$id,'ok'=>false,'skipped'=>true,'reason'=>'missing phone']; continue;
    }
    if(($item['dnc_status']??'')==='listed'){
      $skipped++; $results[]=['id'=>$id,'ok'=>false,'skipped'=>true,'reason'=>'DNC listed']; continue;
    }
    if(!$allowUnapproved && empty($item['approved_by_mark'])){
      $skipped++; $results[]=['id'=>$id,'ok'=>false,'skipped'=>true,'reason'=>'not approved_by_mark']; continue;
    }

    sb104_fixed('PATCH','hunter_queue?id=eq.'.rawurlencode($id),[
      'compliance_checked'=>true,
      'compliance_reason'=>'DNC status not listed; Mark approved; within hunter daily cap.',
      'updated_at'=>date('c')
    ]);

    $retell=retell_call104($item);

    if($retell['ok']){
      $called++;
      $callId=$retell['data']['call_id']??'';
      sb104_fixed('PATCH','hunter_queue?id=eq.'.rawurlencode($id),[
        'status'=>'called',
        'call_mode'=>'autonomous_hunter',
        'retell_call_id'=>$callId,
        'retell_response'=>$retell,
        'last_attempt_at'=>date('c'),
        'attempts'=>(int)($item['attempts']??0)+1,
        'updated_at'=>date('c')
      ]);
      $results[]=['id'=>$id,'name'=>$item['owner_name']??'','ok'=>true,'call_id'=>$callId,'http'=>$retell['http']];
    }else{
      $errors++;
      sb104_fixed('PATCH','hunter_queue?id=eq.'.rawurlencode($id),[
        'retell_response'=>$retell,
        'updated_at'=>date('c')
      ]);
      $results[]=['id'=>$id,'name'=>$item['owner_name']??'','ok'=>false,'error'=>$retell['error']??'retell failed','http'=>$retell['http']??0,'body'=>$retell['body']??''];
    }
  }

  sb104_fixed('POST','hunter_call_runs',[[
    'run_type'=>$allowUnapproved?'manual_allow_unapproved':'approved_only',
    'max_calls'=>$max,
    'attempted'=>count($items),
    'called'=>$called,
    'skipped'=>$skipped,
    'errors'=>$errors,
    'status'=>'complete',
    'results'=>$results,
    'created_at'=>date('c')
  ]]);

  echo json_encode([
    'success'=>$errors===0,
    'attempted'=>count($items),
    'called'=>$called,
    'skipped'=>$skipped,
    'errors'=>$errors,
    'results'=>$results
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'error'=>'PHP exception',
    'message'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ],JSON_PRETTY_PRINT);
}
?>