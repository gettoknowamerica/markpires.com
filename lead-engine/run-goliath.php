<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function call_local($path,$key){$url='https://markpires.com'.$path.(str_contains($path,'?')?'&':'?').'key='.urlencode($key);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>240]);$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$json=json_decode($body,true);return ['http'=>$http,'json'=>is_array($json)?$json:null,'body'=>$body];}
$key=$_GET['key']??'';if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$match=call_local('/lead-engine/build-failed-match-engine.php',$key);
$opp=call_local('/lead-engine/build-opportunity-engine.php',$key);
$exec=call_local('/lead-engine/build-executive-assistant.php',$key);
echo json_encode(['success'=>true,'run'=>'GOLIATH','steps'=>['failed_match_engine'=>$match['json']?:$match['body'],'opportunity_engine'=>$opp['json']?:$opp['body'],'executive_assistant'=>$exec['json']?:$exec['body']]],JSON_PRETTY_PRINT);
?>