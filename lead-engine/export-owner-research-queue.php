<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo 'Invalid key';exit;}
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/owner_research_queue?select=*&order=priority_score.desc&limit=5000');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>60]);
$b=curl_exec($ch);curl_close($ch);$rows=json_decode($b,true);if(!is_array($rows))$rows=[];
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="owner_research_queue.csv"');
$out=fopen('php://output','w');
fputcsv($out,['score','owner_name','address','town','phone_1','phone_2','email_1','status','mark_review','why_now','notes','google_query','assessor_query','people_search_query']);
foreach($rows as $r){
  fputcsv($out,[$r['priority_score']??'',$r['owner_name']??'',$r['address']??'',$r['town']??'',$r['phone_1']??'',$r['phone_2']??'',$r['email_1']??'',$r['queue_status']??'',$r['mark_review_status']??'',$r['why_now']??'',$r['notes']??'',$r['google_query']??'',$r['assessor_query']??'',$r['people_search_query']??'']);
}
fclose($out);
?>