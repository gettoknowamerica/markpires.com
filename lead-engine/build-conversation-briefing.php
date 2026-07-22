<?php
/**
 * V12.15 Conversation Learning Briefing
 * Upload: /public_html/lead-engine/build-conversation-briefing.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
function sb153($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS=json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
$calls=sb153('GET','conversation_intelligence_calls?select=*&order=call_date.desc&limit=1000')['data'];
$total=count($calls);$appts=0;$follow=0;$objections=[];$scripts=[];
foreach($calls as $c){
  if(!empty($c['appointment_set']))$appts++;
  if(!empty($c['follow_up_needed']))$follow++;
  $o=$c['objection_type']?:'none';$objections[$o]=($objections[$o]??0)+1;
  $s=$c['script_variant']?:'unknown';
  if(!isset($scripts[$s]))$scripts[$s]=['name'=>$s,'calls'=>0,'appts'=>0];
  $scripts[$s]['calls']++;
  if(!empty($c['appointment_set']))$scripts[$s]['appts']++;
}
arsort($objections);
$best='';$worst='';
foreach($scripts as $s){
  $rate=$s['calls']?($s['appts']/$s['calls']):0;
  if(!$best || $rate > ($scripts[$best]['appts']/max(1,$scripts[$best]['calls'])))$best=$s['name'];
  if(!$worst || $rate < ($scripts[$worst]['appts']/max(1,$scripts[$worst]['calls'])))$worst=$s['name'];
}
$rate=$total?round(($appts/$total)*100,2):0;
$recs=[];
if($total===0)$recs[]='No calls analyzed yet. Use Conversation Intelligence intake for each Retell/Jessica call.';
if(($objections['just_looking']??0)>0)$recs[]='Common objection: just looking. Use a softer educational close and offer no-pressure value/town review.';
if(($objections['already_has_agent']??0)>0)$recs[]='Some leads have agents. Respect relationship and only offer requested local insight.';
if($rate<10 && $total>5)$recs[]='Appointment conversion is low. Shorten opener and ask fewer questions before offering Mark follow-up.';
if($follow>0)$recs[]=$follow.' follow-ups need scheduling. Prioritize follow-up speed.';
$brief="Conversation Intelligence Briefing — ".date('Y-m-d')."\\n\\n";
$brief.="Calls analyzed: {$total}\\nAppointments set: {$appts}\\nConversion rate: {$rate}%\\nFollow-ups needed: {$follow}\\nMost common objection: ".(array_key_first($objections)?:'none')."\\nBest script: {$best}\\n\\nRecommendations:\\n";
foreach($recs as $i=>$r){$brief.=($i+1).". {$r}\\n";}
$payload=[[
  'briefing_date'=>date('Y-m-d'),
  'calls_analyzed'=>$total,
  'appointments_set'=>$appts,
  'followups_needed'=>$follow,
  'most_common_objection'=>array_key_first($objections)?:'none',
  'best_script_variant'=>$best,
  'worst_script_variant'=>$worst,
  'conversion_rate'=>$rate,
  'recommendations'=>$recs,
  'briefing_text'=>$brief,
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$res=sb153('POST','conversation_learning_briefings',$payload);
if(!$res['ok'] && str_contains($res['body'],'duplicate key')){
  $res=sb153('PATCH','conversation_learning_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$payload[0]);
}
echo json_encode(['success'=>$res['ok'],'briefing'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
?>