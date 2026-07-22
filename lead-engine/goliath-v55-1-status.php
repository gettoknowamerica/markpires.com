<?php
ini_set('display_errors',0); error_reporting(E_ALL); require_once __DIR__.'/config.php'; header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function rq($ep){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);$b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];}
$tasks=rq('local_ai_tasks?select=id,status,metadata,created_at,updated_at&task_type=eq.v55_deliverable_commission&order=created_at.desc&limit=100');
$deliverables=rq('goliath_deliverables?select=id,department,title,deliverable_type,status,score,ready_for_founder,created_at&order=created_at.desc&limit=50');
$summary=[]; foreach($tasks as $t){$s=$t['status']??'unknown';$summary[$s]=($summary[$s]??0)+1;}
echo json_encode(['success'=>true,'version'=>'55.1','task_status'=>$summary,'recent_tasks'=>$tasks,'recent_deliverables'=>$deliverables],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
