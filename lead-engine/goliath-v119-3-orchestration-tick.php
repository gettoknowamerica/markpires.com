<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';

function t1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(t1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function call1193(string $path,string $key):array{
 $url='https://'.($_SERVER['HTTP_HOST']??'www.markpires.com').$path.(str_contains($path,'?')?'&':'?').'key='.rawurlencode($key);
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>90]);$body=(string)curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $json=json_decode($body,true);return ['http'=>$http,'ok'=>$http>=200&&$http<300&&is_array($json)&&($json['ok']??false),'result'=>$json?:$body,'error'=>$err];
}
$results=[
 'enrichment_dispatch'=>call1193('/lead-engine/goliath-v119-3-enrichment-dispatch.php?limit=25',$key),
 'enrichment_apply'=>call1193('/lead-engine/goliath-v119-3-enrichment-apply.php?limit=100',$key),
 'drip_seed'=>call1193('/lead-engine/cron-drip.php?send=0&limit=25',$key)
];
echo json_encode(['ok'=>true,'version'=>'V119.3 Revenue Orchestration Tick','results'=>$results,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>