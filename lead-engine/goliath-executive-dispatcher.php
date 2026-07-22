<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? ($_POST['key'] ?? '');
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function sb($method,$table,$payload=null,$query=''){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query;
  $ch=curl_init($url);$headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
function eventx($title,$detail,$agent='Goliath',$progress=10){
  sb('POST','goliath_events',[['department'=>$agent,'event_type'=>'dispatcher','title'=>$title,'detail'=>$detail,'status'=>'active','phase'=>'dispatch','progress'=>$progress,'confidence'=>92,'roi_estimate'=>0,'metadata'=>['universal_dispatcher'=>true,'created'=>date('c')]]]);
}
// IMPORTANT: dispatcher NO LONGER pre-claims specialist tasks. Local workers own claiming.
// It only broadcasts universal operating law and logs that queued work is waiting.
[$h1,$cmds]=sb('GET','goliath_agent_commands',null,'?select=*&status=eq.queued&order=priority.desc,created_at.asc&limit=25');
if(!is_array($cmds)) $cmds=[];
$seen=[];
foreach($cmds as $c){
  $agent=$c['agent'] ?? ($c['department'] ?? 'Goliath');
  $type=$c['command_type'] ?? ($c['task_type'] ?? 'commission');
  $seen[]=['id'=>$c['id']??null,'agent'=>$agent,'type'=>$type,'action'=>'left_queued_for_worker'];
  eventx($agent.' commission waiting','Queued '.$type.' remains available for the correct local worker. Universal tools may be requested through plugin registry.',$agent,15);
}
echo json_encode(['success'=>true,'version'=>'58.9','mode'=>'non_claiming_dispatcher','queued_seen'=>count($seen),'items'=>$seen,'next'=>'Specialist workers claim queued tasks directly.'],JSON_PRETTY_PRINT);
