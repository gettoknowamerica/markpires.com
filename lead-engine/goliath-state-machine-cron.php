<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
function out($a){ echo json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit; }
$key = $_GET['key'] ?? '';
$good = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key !== $good) out(['success'=>false,'version'=>'58.11','error'=>'Invalid key']);
function sb($method,$ep,$body=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/');
  $ch=curl_init($url);
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $res=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return ['http'=>$http,'error'=>$err,'raw'=>$res,'json'=>json_decode($res,true)];
}
$rows=sb('GET','local_ai_tasks?select=*&or=(workflow_state.is.null,workflow_state.eq.queued,status.eq.queued)&order=priority.desc,created_at.asc&limit=100');
$items=is_array($rows['json'])?$rows['json']:[];
$dispatched=[]; $skipped=[];
foreach($items as $t){
  $id=$t['id']??null; if(!$id){$skipped[]=['reason'=>'missing_id']; continue;}
  $meta=$t['metadata']??[]; if(is_string($meta)) $meta=json_decode($meta,true) ?: [];
  $agent=$t['assigned_agent'] ?? ($meta['agent'] ?? null);
  if(!$agent){
    $p=$t['prompt']??'';
    foreach(['Scout','Jessica','Scorsese','Shakespeare','Columbo','Mozart','Einstein','Rockefeller','Prospector','Pandora','Goliath'] as $a){ if(stripos($p,$a)!==false){$agent=$a; break;} }
  }
  if(!$agent) $agent='Goliath';
  $patch=[
    'workflow_state'=>'dispatched',
    'status'=>'dispatched',
    'assigned_agent'=>$agent,
    'claimed_by'=>null,
    'progress'=>max(0,(int)($t['progress']??0)),
    'current_phase'=>'Dispatched to '.$agent,
    'next_milestone'=>'Specialist worker claim',
    'dispatched_at'=>gmdate('c'),
    'last_heartbeat_at'=>gmdate('c')
  ];
  $r=sb('PATCH','local_ai_tasks?id=eq.'.rawurlencode($id),$patch);
  if($r['http']>=200 && $r['http']<300) $dispatched[]=['id'=>$id,'agent'=>$agent,'type'=>$t['task_type']??null];
  else $skipped[]=['id'=>$id,'agent'=>$agent,'http'=>$r['http'],'raw'=>substr((string)$r['raw'],0,200)];
}
out(['success'=>true,'version'=>'58.11','mode'=>'state_machine_dispatcher','seen'=>count($items),'dispatched_count'=>count($dispatched),'dispatched'=>$dispatched,'skipped'=>$skipped,'next'=>'Run local executive state worker. It claims dispatched tasks only.']);
