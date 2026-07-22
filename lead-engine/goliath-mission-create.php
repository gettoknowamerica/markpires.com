<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? $_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb($method,$table,$payload=null,$query=''){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query;
  $ch=curl_init($url);
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

$mission='MISSION-'.date('Ymd-His').'-'.substr(md5(random_bytes(8)),0,5);
$title=$data['title'] ?? 'Goliath Mission';
$prompt=$data['prompt'] ?? '';
$lead=$data['lead'] ?? [];
$roi=(float)($data['roi_estimate'] ?? 15000);

$flow=[
  ['Jessica','intake','Jessica qualifies lead and prepares first touch.',115],
  ['Einstein','analysis','Einstein analyzes last 90 days MLS/town/price-band statistics.',112],
  ['Scout','research','Scout enriches owner/contact/property intelligence.',110],
  ['Scorsese','creative','Scorsese creates personalized video/graphic/content angle.',108],
  ['Shakespeare','publishing','Shakespeare creates blog, email, social, SEO/AEO assets.',106],
  ['Jessica','drip','Jessica begins personalized buyer/seller drip.',104],
  ['Rockefeller','roi','Rockefeller scores ROI and next highest-value action.',102]
];

$commands=[];
foreach($flow as $step){
  [$dept,$phase,$stepTitle,$priority]=$step;
  $row=[
    'mission_id'=>$mission,
    'command_type'=>'mission_'.$phase,
    'department'=>$dept,
    'title'=>$stepTitle,
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priority,
    'source'=>$data['source'] ?? 'mission_brain',
    'brand'=>$data['brand'] ?? 'mark_pires',
    'supporting_departments'=>array_values(array_unique(array_column($flow,0))),
    'metadata'=>['mission_id'=>$mission,'phase'=>$phase,'lead'=>$lead,'roi_estimate'=>$roi]
  ];
  [$http,$res]=sb('POST','goliath_commands',[$row]);
  if($http>=200&&$http<300 && isset($res[0])) $commands[]=$res[0];
}

$event=[
  'mission_id'=>$mission,
  'department'=>'Rockefeller',
  'event_type'=>'mission_created',
  'title'=>'Rockefeller created mission: '.$title,
  'detail'=>$prompt,
  'roi_estimate'=>$roi,
  'confidence'=>92,
  'status'=>'queued',
  'phase'=>'created',
  'progress'=>5,
  'link_url'=>'/dashboard/goliath-mission.php?mission_id='.rawurlencode($mission),
  'metadata'=>['mission_id'=>$mission,'title'=>$title,'lead'=>$lead]
];
sb('POST','goliath_events',[$event]);

echo json_encode(['success'=>true,'mission_id'=>$mission,'commands'=>$commands],JSON_PRETTY_PRINT);
?>