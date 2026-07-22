<?php
require_once __DIR__ . '/lead-brain-core.php';
$raw=file_get_contents('php://input');$in=json_decode($raw,true); if(!is_array($in)) $in=$_POST;
$key=$in['key']??($_GET['key']??''); $good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good) gb_json(['success'=>false,'error'=>'Bad key'],403);
$lead=$in['lead_id']??'';$stage=$in['stage']??'';$status=$in['status']??'complete';
if(!$lead||!$stage) gb_json(['success'=>false,'error'=>'lead_id and stage required'],400);
$payload=['status'=>$status,'detail'=>$in['detail']??('Stage marked '.$status),'updated_at'=>date('c')];
if($status==='running') $payload['started_at']=date('c');
if($status==='complete') $payload['completed_at']=date('c');
if(isset($in['evidence'])) $payload['evidence']=$in['evidence'];
if(isset($in['artifact_url'])) $payload['artifact_url']=$in['artifact_url'];
$r=gb_supabase('PATCH','goliath_lead_journey?lead_id=eq.'.rawurlencode($lead).'&stage=eq.'.rawurlencode($stage),$payload);
if(!$r['ok']) gb_json(['success'=>false,'response'=>$r],500);
gb_json(['success'=>true,'updated'=>$r['body']]);
