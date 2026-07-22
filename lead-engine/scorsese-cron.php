<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json');
function out($a){echo json_encode($a,JSON_PRETTY_PRINT);exit;}
$key=$_GET['key']??''; $real=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$real) out(['success'=>false,'error'=>'Invalid key']);
function sb($method,$ep,$body=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/');
  $ch=curl_init($url); $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $txt=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $json=json_decode($txt,true); return [$http,$json,$txt];
}
function getv($r,$keys,$def=''){foreach($keys as $k){if(isset($r[$k])&&$r[$k]!==''&&$r[$k]!==null)return $r[$k];}return $def;}
$created=[];$skipped=[];$errors=[];
list($h,$projects,$raw)=sb('GET','scorsese_media_projects?select=*&status=in.(stored,queued,new,prompt_ready)&order=created_at.asc&limit=25');
if(!is_array($projects)) out(['success'=>false,'version'=>'58.7','error'=>'Could not read scorsese_media_projects','http'=>$h,'raw'=>$raw]);
foreach($projects as $p){
  $pid=$p['id']??''; if(!$pid) continue;
  $type=strtolower(getv($p,['project_type','template','production_type'],''));
  $media=getv($p,['media_url','file_url','source_url','url'],'');
  $prompt=getv($p,['prompt','director_notes','notes','description'],'');
  $title=getv($p,['title','project_name','name'],'Scorsese Production');
  list($th,$existing)=sb('GET','local_ai_tasks?select=id,status,task_type,command_type&or=(metadata->>project_id.eq.'.rawurlencode($pid).',metadata->>scorsese_project_id.eq.'.rawurlencode($pid).')&status=in.(queued,running)&limit=1');
  if(is_array($existing)&&count($existing)>0){$skipped[]=['project_id'=>$pid,'reason'=>'already_queued']; continue;}
  $isPromptOnly = (!$media) || strpos($type,'prompt')!==false || strpos($type,'from nothing')!==false;
  if($isPromptOnly){
    if(!$prompt){$skipped[]=['project_id'=>$pid,'reason'=>'prompt_video_missing_prompt']; continue;}
    $body=[
      'task_type'=>'director_video','command_type'=>'director_video','model'=>'scorsese-local','prompt'=>$prompt,'status'=>'queued','priority'=>125,
      'metadata'=>['agent'=>'Scorsese','project_id'=>$pid,'title'=>$title,'version'=>'58.7','mode'=>'prompt_video','brand'=>getv($p,['brand'],'LegacySaved'),'template'=>getv($p,['template','project_type'],'Video Prompt')]
    ];
  } else {
    $clip=['url'=>$media,'name'=>basename(parse_url($media,PHP_URL_PATH)?:'upload.mp4')];
    $body=[
      'task_type'=>'production_edit','command_type'=>'production_edit','model'=>'scorsese-local','prompt'=>$prompt?:('Create the strongest possible production from '.$title),'status'=>'queued','priority'=>120,
      'metadata'=>['agent'=>'Scorsese','project_id'=>$pid,'title'=>$title,'project_name'=>$title,'version'=>'58.7','mode'=>'uploaded_media','clips'=>[$clip],'brand'=>getv($p,['brand'],'Mark Pires'),'template'=>getv($p,['template','project_type'],'Uploaded Media')]
    ];
  }
  list($ih,$ins,$itxt)=sb('POST','local_ai_tasks',$body);
  if($ih>=200&&$ih<300){$created[]=['project_id'=>$pid,'task_type'=>$body['task_type'],'task_id'=>$ins[0]['id']??null]; sb('PATCH','scorsese_media_projects?id=eq.'.rawurlencode($pid),['status'=>'queued','progress'=>5]);}
  else {$errors[]=['project_id'=>$pid,'http'=>$ih,'response'=>$itxt];}
}
out(['success'=>true,'version'=>'58.7','projects_checked'=>count($projects),'created_count'=>count($created),'created'=>$created,'skipped'=>$skipped,'errors'=>$errors,'next'=>'Run both local Scorsese workers. Uploaded media uses production_edit; prompt-only uses director_video.']);
