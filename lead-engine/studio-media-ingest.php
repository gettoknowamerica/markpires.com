<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_POST['key'] ?? $_GET['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

if(empty($_FILES['media'])){
  echo json_encode(['success'=>false,'error'=>'No media uploaded']);
  exit;
}

$allowed=['mp4','mov','webm','m4v','png','jpg','jpeg','webp','gif'];
$orig=$_FILES['media']['name'] ?? 'goliath-output.mp4';
$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
if(!in_array($ext,$allowed,true)){
  echo json_encode(['success'=>false,'error'=>'File type not allowed','ext'=>$ext]);
  exit;
}

$baseDir=realpath(__DIR__.'/..').'/dashboard/assets/generated';
$sub=date('Y/m/d');
$dir=$baseDir.'/'.$sub;
if(!is_dir($dir)) @mkdir($dir,0775,true);

$safe=preg_replace('/[^a-zA-Z0-9._-]+/','-',pathinfo($orig,PATHINFO_FILENAME));
$name='goliath-'.date('His').'-'.substr(md5(random_bytes(8)),0,8).'-'.$safe.'.'.$ext;
$dest=$dir.'/'.$name;

if(!move_uploaded_file($_FILES['media']['tmp_name'],$dest)){
  echo json_encode(['success'=>false,'error'=>'Could not save file']);
  exit;
}

$url='/dashboard/assets/generated/'.$sub.'/'.$name;
$title=$_POST['title'] ?? 'Goliath Creation';
$brand=$_POST['brand'] ?? 'mark_pires';
$kind=$_POST['kind'] ?? (in_array($ext,['png','jpg','jpeg','webp','gif'])?'image':'video');
$command_id=$_POST['command_id'] ?? null;
$mission_id=$_POST['mission_id'] ?? null;
$prompt=$_POST['prompt'] ?? '';

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
    CURLOPT_TIMEOUT=>30
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}

/*
  First try full insert with metadata.
  If Supabase schema cache/table is missing metadata, fallback to minimal insert.
*/
$metadata=[
  'kind'=>$kind,
  'command_id'=>$command_id,
  'mission_id'=>$mission_id,
  'prompt'=>$prompt,
  'created_by'=>'Scorsese',
  'original_filename'=>$orig
];

$full=[
  'title'=>$title,
  'source_url'=>$url,
  'brand_pillar'=>$brand,
  'status'=>'review_ready',
  'metadata'=>$metadata
];

[$mh,$mr]=sb('POST','media_projects',[$full]);

if($mh>=400){
  $minimal=[
    'title'=>$title,
    'source_url'=>$url
  ];
  [$mh2,$mr2]=sb('POST','media_projects',[$minimal]);
  $mh=$mh2;
  $mr=$mr2;
}

$event=[
  'mission_id'=>$mission_id,
  'command_id'=>$command_id,
  'department'=>'Scorsese',
  'event_type'=>'media_ready',
  'title'=>'Scorsese delivered a review-ready video',
  'detail'=>$title.' is ready to watch in Goliath.',
  'roi_estimate'=>9000,
  'confidence'=>96,
  'status'=>'review_ready',
  'phase'=>'review',
  'progress'=>100,
  'link_url'=>'/dashboard/goliath-completed-media.php',
  'metadata'=>['media_url'=>$url,'kind'=>$kind,'title'=>$title]
];
sb('POST','goliath_events',[$event]);

echo json_encode([
  'success'=>($mh>=200 && $mh<300),
  'url'=>$url,
  'title'=>$title,
  'kind'=>$kind,
  'media_insert_http'=>$mh,
  'media'=>$mr[0] ?? $mr,
  'note'=>'If media_insert_http is 400, run goliath-v40-3-media-projects-schema.sql in Supabase and retry.'
],JSON_PRETTY_PRINT);
?>