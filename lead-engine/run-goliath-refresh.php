<?php
declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$key=(string)($_GET['key']??'');
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals((string)AFTER_HOURS_CRON_KEY,$key)){
 http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;
}
$mode=(string)($_GET['mode']??'full');
$host='https://'.($_SERVER['HTTP_HOST']??'www.markpires.com');
$k=rawurlencode($key);

function rg1162_hit(string $name,string $url):array{
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>55,CURLOPT_FOLLOWLOCATION=>true]);
 $body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
 $json=json_decode((string)$body,true);
 return ['name'=>$name,'http'=>$http,'ok'=>$http>=200&&$http<300,'error'=>$error,'result'=>is_array($json)?$json:substr((string)$body,0,800)];
}

$jobs=[];
if($mode==='full'||$mode==='expired')$jobs[]=['Owner Research / Numbers',"$host/lead-engine/build-owner-research-queue.php?key=$k&limit=250&mode=replace"];
if($mode==='full'||$mode==='followup'){
 $jobs[]=['Lead Drip Follow-Up',"$host/lead-engine/cron-drip.php?key=$k"];
 $jobs[]=['After-Hours Follow-Up',"$host/lead-engine/process-after-hours-callbacks.php?key=$k"];
}
if($mode==='full'||$mode==='content'){
 $jobs[]=['Media Director',"$host/lead-engine/build-media-director.php?key=$k"];
 $jobs[]=['Shorts Factory',"$host/lead-engine/build-shorts-factory.php?key=$k"];
 $jobs[]=['Goliath Social Queue',"$host/lead-engine/build-blotato-distribution-director.php?key=$k"];
}
$jobs[]=['Always Working Governor',"$host/lead-engine/goliath-v116-2-always-working-governor.php?key=$k&target=1&max_waves=8"];
$jobs[]=['Verified Sequential Engine',"$host/lead-engine/goliath-v115-1-sequential-engine.php?key=$k"];

$results=[];foreach($jobs as [$name,$url])$results[]=rg1162_hit($name,$url);
echo json_encode([
 'success'=>true,'version'=>'V116.2 Refresh + Governor','mode'=>$mode,
 'message'=>'Refresh complete. The Always Working Governor seeds the V112 production ring and the sequential engine dispatches eligible stages.',
 'cron_recommendation'=>"/usr/bin/curl -s \"$host/lead-engine/run-goliath-refresh.php?key=YOUR_KEY&mode=full\" >/dev/null 2>&1",
 'results'=>$results,'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>