<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';

function pr1183_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(pr1183_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$path=__DIR__.'/goliath-plugin-registry-v118-3.json';
$data=is_file($path)?json_decode((string)file_get_contents($path),true):null;
if(!is_array($data)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'registry_missing']);exit;}
$exec=ucfirst(strtolower(trim((string)($_GET['executive']??''))));
$keys=$data['executive_mappings'][$exec]??[];
$indexed=[];foreach($data['plugins'] as $plugin)$indexed[$plugin['key']]=$plugin;
$selected=[];foreach($keys as $keyName)if(isset($indexed[$keyName]))$selected[]=$indexed[$keyName];
echo json_encode(['ok'=>true,'version'=>'V118.3 Plugin Registry','executive'=>$exec,'law'=>$data['law'],'plugins'=>$selected?:$data['plugins']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>