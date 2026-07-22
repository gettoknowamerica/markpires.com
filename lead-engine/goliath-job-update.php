<?php
/** Local worker callback: marks a Goliath job complete/failed and logs Mission Control event. */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function out($a,$c=200){http_response_code($c);echo json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;}
$key=$_GET['key']??($_POST['key']??''); $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$expected)out(['success'=>false,'error'=>'Unauthorized'],403);
$raw=file_get_contents('php://input'); $in=json_decode($raw,true); if(!is_array($in))$in=$_POST;
$jobId=$in['goliath_job_id']??$in['job_id']??''; $status=$in['status']??'completed'; $agent=$in['agent']??'Goliath'; $summary=$in['summary']??'Job completed.'; $result=$in['result']??$in;
if(!$jobId)out(['success'=>false,'error'=>'Missing goliath_job_id'],400);
function reqx($method,$endpoint,$body=null){$url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');$h=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>30]);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_SLASHES));$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return ['ok'=>$http>=200&&$http<300,'http'=>$http,'raw'=>$raw,'data'=>json_decode($raw,true)?:[]];}
$patch=reqx('PATCH','goliath_agent_jobs?id=eq.'.rawurlencode($jobId),['status'=>$status,'result'=>$result,'completed_at'=>date('c'),'updated_at'=>date('c')]);
$ev=reqx('POST','goliath_events',['department'=>$agent,'title'=>$status==='failed'?'Mission Failed':'Mission Complete','detail'=>$summary,'status'=>'active','confidence'=>$status==='failed'?40:95,'roi_estimate'=>0,'metadata'=>['agent'=>$agent,'goliath_job_id'=>$jobId,'result'=>$result]]);
out(['success'=>$patch['ok'],'job_update'=>$patch,'event'=>$ev]);
