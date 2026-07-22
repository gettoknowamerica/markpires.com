<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'not_authenticated']); exit; }
function ei_req($method,$endpoint,$body=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35]);
  if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
  $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($raw,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'data'=>is_array($data)?$data:$raw,'raw'=>$raw,'error'=>$err];
}
function ei_allowed_agent($a){$x=['Jessica','Scout','Scorsese','Mozart','Shakespeare','Einstein','Columbo','Prospector','Rockefeller','Pandora','Goliath']; return in_array($a,$x,true);}
$in=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$agent=trim((string)($in['executive'] ?? $in['agent'] ?? 'Goliath'));
$message=trim((string)($in['message'] ?? ''));
$item=trim((string)($in['source_item_id'] ?? ''));
if(!ei_allowed_agent($agent)) $agent='Goliath';
if($message===''){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'message_required']); exit; }

$secretWarning = preg_match('/(api[_\s-]?key|secret|token|password)\s*[:=]/i',$message);
$ack = $secretWarning
  ? "Mark, I received the direction. For safety, I will not store raw secrets in the intercom. Put keys in Hostinger environment variables, then tell me which variable name to use."
  : "Understood, Mark. I am on it. I have queued this direction and will report progress here.";

$msgPayload=[
  'executive'=>$agent,
  'source'=>'executive_intercom',
  'message'=>$message,
  'source_item_id'=>$item ?: null,
  'status'=>'acknowledged',
  'acknowledgement'=>$ack,
  'progress'=>5,
  'current_phase'=>'Direction received from Founder',
  'next_milestone'=>'Create or update the Executive work item',
  'metadata'=>['release'=>'57.0.0','ui'=>'executive_intercom']
];
$msg=ei_req('POST','goliath_executive_messages',$msgPayload);
if(!$msg['ok']){ http_response_code(500); echo json_encode(['success'=>false,'stage'=>'insert_message','error'=>$msg],JSON_PRETTY_PRINT); exit; }
$msgId=$msg['data'][0]['id'] ?? null;

$progress=ei_req('POST','goliath_executive_progress',[
  'executive'=>$agent,
  'related_type'=>'intercom',
  'related_id'=>$msgId,
  'status'=>'working',
  'progress'=>5,
  'current_phase'=>'Direction received from Founder',
  'next_milestone'=>'Local worker response / commission update',
  'metadata'=>['message_preview'=>mb_substr($message,0,240),'release'=>'57.0.0']
]);

$prompt = "You are {$agent}, a commissioned Executive in Goliath Omni.\n\nFounder direction from Mark:\n{$message}\n\nRespond as {$agent} with: 1) a brief acknowledgement, 2) what you will do next, 3) estimated progress phases, 4) any dependency or credential variable needed. Do not reveal or request raw API keys in the response; ask for environment variable names instead. Return useful work, not fluff.";
$task=ei_req('POST','local_ai_tasks',[
  'task_type'=>'executive_intercom_command',
  'model'=>'llama3.1:8b',
  'prompt'=>$prompt,
  'status'=>'queued',
  'priority'=>95,
  'progress'=>5,
  'current_phase'=>'Queued from Executive Intercom',
  'next_milestone'=>'Local AI acknowledgement',
  'metadata'=>['agent'=>$agent,'source'=>'executive_intercom','message_id'=>$msgId,'release'=>'57.0.0','source_item_id'=>$item]
]);
$taskId = ($task['ok'] && is_array($task['data'])) ? ($task['data'][0]['id'] ?? null) : null;
if($taskId && $msgId){ ei_req('PATCH','goliath_executive_messages?id=eq.'.rawurlencode($msgId),['local_task_id'=>$taskId,'updated_at'=>gmdate('c')]); }

echo json_encode(['success'=>true,'executive'=>$agent,'message_id'=>$msgId,'local_task_id'=>$taskId,'acknowledgement'=>$ack,'progress'=>['percent'=>5,'phase'=>'Direction received from Founder','next'=>'Local worker acknowledgement']], JSON_PRETTY_PRINT);
