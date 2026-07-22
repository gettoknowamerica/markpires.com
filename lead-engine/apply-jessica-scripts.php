<?php
/**
 * V10.5 Apply Script Prompts
 * Upload: /public_html/lead-engine/apply-jessica-scripts.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb105($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function script_prompt105($s){
  return trim(
"JESSICA SCRIPT KEY: ".($s['script_key']??'')."

VOICE STYLE:
".($s['tone_notes']??'')."

OPENING LINE:
".($s['opening_line']??'')."

CORE SCRIPT:
".($s['core_script']??'')."

VOICEMAIL SCRIPT:
".($s['voicemail_script']??'')."

SMS FOLLOW-UP:
".($s['sms_followup']??'')."

OBJECTION HANDLERS:
".json_encode($s['objection_handlers']??[], JSON_PRETTY_PRINT)."

COMPLIANCE NOTES:
".($s['compliance_notes']??'')."

RULES:
- Start relaxed and smooth. Do not rush the first sentence.
- Never pressure.
- If they ask to be removed or say do not call, acknowledge and end politely.
- If they show selling interest, collect timeline, address, motivation, and best follow-up time.
- If they want Mark, say you will send it to Mark's team immediately."
  );
}

$scripts=sb105('GET','jessica_script_library?select=*&is_active=eq.true&limit=200')['data'];
$map=[];
foreach($scripts as $s){ if(!empty($s['script_key'])) $map[$s['script_key']]=$s; }

$hunter=sb105('GET','hunter_queue?select=*&status=in.(review,approved,queued)&limit=500')['data'];
$mission=sb105('GET','jessica_priority_queue?select=*&status=in.(pending,queued)&limit=500')['data'];

$updatedHunter=0;$updatedMission=0;$skipped=[];

foreach($hunter as $h){
  $key=$h['script_key']??($h['suggested_script']??'cold_homeowner');
  if(empty($map[$key])){$skipped[]=['type'=>'hunter','id'=>$h['id']??'','reason'=>'script missing '.$key];continue;}
  $prompt=script_prompt105($map[$key]);
  $r=sb105('PATCH','hunter_queue?id=eq.'.rawurlencode($h['id']),[
    'script_prompt'=>$prompt,
    'updated_at'=>date('c')
  ]);
  if($r['ok'])$updatedHunter++;
}

foreach($mission as $m){
  $key=$m['script_key']??'';
  if(!$key){
    $leadType=strtolower((string)($m['lead_type']??''));
    $missionType=strtolower((string)($m['mission_type']??''));
    $source=strtolower((string)($m['source']??''));
    if(str_contains($source,'hunter')||str_contains($leadType,'hunter')) $key='cold_homeowner';
    elseif(str_contains($missionType,'appointment')) $key='warm_lead';
    elseif(str_contains($source,'home valuation')) $key='warm_lead';
    elseif(str_contains($leadType,'future')) $key='warm_lead';
    else $key='warm_lead';
  }
  if(empty($map[$key])){$skipped[]=['type'=>'mission','id'=>$m['id']??'','reason'=>'script missing '.$key];continue;}
  $prompt=script_prompt105($map[$key]);
  $r=sb105('PATCH','jessica_priority_queue?id=eq.'.rawurlencode($m['id']),[
    'script_key'=>$key,
    'script_prompt'=>$prompt,
    'updated_at'=>date('c')
  ]);
  if($r['ok'])$updatedMission++;
}

echo json_encode([
  'success'=>true,
  'scripts_loaded'=>count($scripts),
  'hunter_updated'=>$updatedHunter,
  'mission_updated'=>$updatedMission,
  'skipped'=>array_slice($skipped,0,100)
],JSON_PRETTY_PRINT);
?>