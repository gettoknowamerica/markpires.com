<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$mode=$_GET['mode']??'hourly';
$base=(isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'www.markpires.com').'/lead-engine/';
function callx($url){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>55]);$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$j=json_decode($body,true);return ['url'=>$url,'http'=>$http,'ok'=>$http>=200&&$http<300,'error'=>$err,'result'=>is_array($j)?$j:$body];}
$jobs=[];
if($mode==='nightly') $jobs[]='run-goliath-executive-council.php?key='.rawurlencode($key).'&type=nightly';
else $jobs[]='run-goliath-autonomous-cycle.php?key='.rawurlencode($key).'&mode=hourly';
$results=[]; foreach($jobs as $j){$results[]=callx($base.$j);}
echo json_encode(['ok'=>true,'version'=>'V78 Cron Hub','mode'=>$mode,'results'=>$results,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>