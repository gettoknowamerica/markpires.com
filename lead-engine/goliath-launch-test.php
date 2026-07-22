<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$type=$data['type'] ?? 'mission';

function post_json($url,$payload){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}

$base=(isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$prompt='Launch test: Mark Harrison is a California relocation buyer/seller prospect interested in modern homes in Fairfield County around $1.1M. Build a personalized strategy using last 90 days MLS stats, contact enrichment, cinematic content, publishing, and Jessica follow-up.';

if($type==='mission' || $type==='drip'){
  [$http,$res]=post_json($base.'/lead-engine/goliath-mission-create.php',[
    'key'=>$key,
    'title'=>'Launch Candidate Full Mission Test',
    'prompt'=>$prompt,
    'source'=>'launch_candidate',
    'roi_estimate'=>35000,
    'lead'=>['name'=>'Mark Harrison','type'=>'Buyer/Seller','town'=>'Fairfield County','budget'=>'1100000']
  ]);
  echo json_encode(['success'=>$http>=200&&$http<300,'type'=>$type,'response'=>$res,'mission_id'=>$res['mission_id']??null],JSON_PRETTY_PRINT); exit;
}

$department='Rockefeller'; $command='executive_briefing_action'; $title='Launch briefing test';
if($type==='director_video'){ $department='Scorsese'; $command='director_video'; $title='Scorsese launch video test'; }
if($type==='briefing'){ $department='Rockefeller'; $command='launch_briefing'; $title='Rockefeller launch briefing test'; }

[$http,$res]=post_json($base.'/lead-engine/goliath-event-bus.php',[
  'key'=>$key,
  'action'=>'command',
  'command_type'=>$command,
  'department'=>$department,
  'title'=>$title,
  'prompt'=>$prompt,
  'priority'=>135,
  'source'=>'launch_candidate',
  'roi_estimate'=>25000,
  'metadata'=>['launch_test'=>true,'type'=>$type]
]);
echo json_encode(['success'=>$http>=200&&$http<300,'type'=>$type,'response'=>$res],JSON_PRETTY_PRINT);
?>