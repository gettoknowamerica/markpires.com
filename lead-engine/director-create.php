<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? $_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

$type=$data['type'] ?? $data['command_type'] ?? 'director_video';
$prompt=trim($data['prompt'] ?? '');
if(!$prompt){ echo json_encode(['success'=>false,'error'=>'Missing prompt']); exit; }

$row=[
  'command_type'=>$type,
  'department'=>'Director',
  'title'=>$data['title'] ?? ($type==='director_image'?'Director Create Image':'Director Create Video'),
  'prompt'=>$prompt,
  'status'=>'queued',
  'priority'=>(int)($data['priority'] ?? 120),
  'source'=>$data['source'] ?? 'director_console',
  'brand'=>$data['brand'] ?? 'mark_pires',
  'metadata'=>$data['metadata'] ?? new stdClass()
];

$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_commands');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode([$row]),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
$body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
$cmd=json_decode($body,true)[0] ?? null;

$event=[
  'department'=>'Director',
  'event_type'=>'director_command',
  'title'=>$row['title'],
  'detail'=>$prompt,
  'roi_estimate'=>(float)($data['roi_estimate'] ?? 5000),
  'confidence'=>90,
  'status'=>'queued',
  'link_url'=>'/dashboard/goliath-mission-control.php',
  'command_id'=>$cmd['id'] ?? null,
  'metadata'=>['command_type'=>$type]
];
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/goliath_events');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode([$event]),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
curl_exec($ch); curl_close($ch);

echo json_encode(['success'=>$http>=200&&$http<300,'command'=>$cmd,'http'=>$http],JSON_PRETTY_PRINT);
?>