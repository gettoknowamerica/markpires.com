<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
function cfgx($k,$d=null){return defined($k)?constant($k):$d;}
$key=$_GET['key']??''; if($key !== cfgx('AFTER_HOURS_CRON_KEY','timetomakethedonuts')){echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;}
function sb($method,$ep,$body=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'); $ch=curl_init($url);
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>30]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $out=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $d=json_decode($out,true);
  return ['http'=>$http,'data'=>is_array($d)?$d:[],'raw'=>$out];
}
$agents=['Goliath','Scout','Jessica','Scorsese','Shakespeare','Columbo','Mozart','Einstein','Rockefeller','Prospector','Pandora'];
$tasks=sb('GET','local_ai_tasks?select=*&status=eq.queued&order=priority.desc,created_at.asc&limit=50')['data'];
$items=[];$claimed=0;$leftForSpecialist=0;
foreach($tasks as $t){
  $m=$t['metadata']??[]; if(is_string($m)) $m=json_decode($m,true)?:[];
  $agent=$m['agent']??'';
  if(!$agent){ foreach($agents as $a){ if(stripos(($t['prompt']??'').' '.($t['task_type']??''),$a)!==false){$agent=$a;break;} } }
  if(!$agent) $agent='Goliath';
  $type=$t['task_type']??'';
  $specialist = ($agent==='Scorsese' && in_array($type,['production_edit','director_video','director_image','create_video','create_image','thumbnail'],true));
  if($specialist){
    $items[]=['id'=>$t['id']??null,'agent'=>$agent,'type'=>$type,'action'=>'left_queued_for_scorsese_specialist']; $leftForSpecialist++; continue;
  }
  $m['agent']=$agent; $m['executive_law_version']='58.10'; $m['universal_directives']=[
    'auto_claim_immediately'=>true,
    'use_best_available_tools'=>true,
    'collaborate_with_other_executives'=>true,
    'create_more_value_than_requested'=>true,
    'notify_founder_when_ready'=>true,
    'never_submit_status_report_only'=>true
  ];
  $patch=['status'=>'running','claimed_by'=>'executive-branch-core','progress'=>5,'current_phase'=>'Claimed under Executive Law','next_milestone'=>'Load charter, request tools, begin deliverable','last_heartbeat_at'=>gmdate('c'),'metadata'=>$m];
  $r=sb('PATCH','local_ai_tasks?id=eq.'.rawurlencode($t['id']),$patch);
  $ok=$r['http']>=200&&$r['http']<300; if($ok)$claimed++;
  sb('POST','goliath_events',[['department'=>$agent,'title'=>"$agent started work",'detail'=>'Commission auto-claimed by Executive Branch Core. Charter and plugin permissions attached.','confidence'=>90,'status'=>'active','metadata'=>['task_id'=>$t['id']??null,'version'=>'58.10']]]);
  $items[]=['id'=>$t['id']??null,'agent'=>$agent,'type'=>$type,'action'=>$ok?'claimed':'claim_failed','http'=>$r['http']];
}
echo json_encode(['success'=>true,'version'=>'58.10','queued_seen'=>count($tasks),'claimed'=>$claimed,'left_for_specialist'=>$leftForSpecialist,'items'=>$items,'next'=>'Run universal local worker and specialist workers.'],JSON_PRETTY_PRINT);
