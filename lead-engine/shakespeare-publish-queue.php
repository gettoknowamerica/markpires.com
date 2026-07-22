<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>25
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}

$action=$data['action'] ?? 'queue';

if($action==='save_account'){
  $row=[
    'platform'=>$data['platform'] ?? '',
    'account_name'=>$data['account_name'] ?? '',
    'profile_url'=>$data['profile_url'] ?? '',
    'access_token'=>$data['access_token'] ?? '',
    'refresh_token'=>$data['refresh_token'] ?? '',
    'api_key'=>$data['api_key'] ?? '',
    'api_secret'=>$data['api_secret'] ?? '',
    'status'=>$data['status'] ?? 'manual_ready',
    'notes'=>$data['notes'] ?? '',
    'metadata'=>$data['metadata'] ?? new stdClass()
  ];
  [$http,$res]=sb('POST','goliath_social_accounts',[$row]);
  echo json_encode(['success'=>$http>=200&&$http<300,'account'=>$res[0]??$res],JSON_PRETTY_PRINT); exit;
}

$platforms=$data['platforms'] ?? [];
if(!is_array($platforms)) $platforms=[$platforms];
$title=$data['title'] ?? 'Goliath Post';
$media=$data['media_url'] ?? '';
$caption=$data['caption'] ?? '';
$hashtags=$data['hashtags'] ?? '';
$brand=$data['brand'] ?? 'mark_pires';

$rows=[];
$baseTime=time()+1800;
foreach($platforms as $i=>$p){
  if(!$p) continue;
  $rows[]=[
    'title'=>$title,
    'platform'=>$p,
    'media_url'=>$media,
    'caption'=>$caption,
    'hashtags'=>$hashtags,
    'scheduled_at'=>date('c',$baseTime+($i*900)),
    'status'=>'queued',
    'approval_status'=>$data['approval_status'] ?? 'approved',
    'brand'=>$brand,
    'mission_id'=>$data['mission_id'] ?? null,
    'command_id'=>$data['command_id'] ?? null,
    'metadata'=>['source'=>$data['source'] ?? 'publishing_hub','best_time_auto'=>true]
  ];
}
[$http,$res]=sb('POST','goliath_publish_queue',$rows);

$event=[
  'department'=>'Shakespeare',
  'event_type'=>'publishing_queued',
  'title'=>'Shakespeare queued '.count($rows).' posts',
  'detail'=>$title,
  'roi_estimate'=>4000,
  'confidence'=>90,
  'status'=>'queued',
  'link_url'=>'/dashboard/goliath-publishing-hub.php',
  'metadata'=>['platforms'=>$platforms,'media_url'=>$media]
];
sb('POST','goliath_events',[$event]);

echo json_encode(['success'=>$http>=200&&$http<300,'queued'=>$res],JSON_PRETTY_PRINT);
?>