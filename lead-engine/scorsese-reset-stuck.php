<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function sb($method,$table,$payload=null,$query=''){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query;
  $ch=curl_init($url);$headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
// Return Scorsese work accidentally pre-claimed by dispatcher back to queued so the local worker can claim it.
$patch=['status'=>'queued','updated_at'=>date('c')];
$q='?agent=eq.Scorsese&status=in.(working,running,claimed)&command_type=in.(production_edit,director_video,wan_video,create_video,director_image,thumbnail)';
[$http,$res]=sb('PATCH','goliath_agent_commands',$patch,$q);
if($http<200 || $http>=300){
  // fallback table name used by older builds
  [$http,$res]=sb('PATCH','local_ai_tasks',$patch,'?status=in.(working,running,claimed)&metadata->>agent=eq.Scorsese');
}
echo json_encode(['success'=>$http>=200&&$http<300,'version'=>'58.9','reset'=>$res,'next'=>'Run scorsese-cron, then run the local Scorsese worker.'],JSON_PRETTY_PRINT);
