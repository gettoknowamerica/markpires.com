<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$message=trim($data['message'] ?? $data['text'] ?? $data['prompt'] ?? '');
$conversation_id=$data['conversation_id'] ?? ('ask_'.date('Ymd_His'));
$source=$data['source'] ?? 'ask_goliath';
if($message===''){
  echo json_encode(['success'=>true,'answer'=>'I am here, Mark. Conversation Mode is ready. Talk to me like a normal business partner.','response'=>'I am here, Mark. Conversation Mode is ready. Talk to me like a normal business partner.','conversation_id'=>$conversation_id,'trace'=>[['label'=>'Ready','detail'=>'No message received yet.']]],JSON_PRETTY_PRINT);exit;
}

function g_sb($method,$table,$payload=null,$query=''){
  if(!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY') || !SUPABASE_URL || !SUPABASE_SERVICE_ROLE_KEY) return [0,null];
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>22]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
function g_pick_intent($t){
  $l=strtolower($t);
  if(preg_match('/\b(scout|phone numbers?|emails?|expired|foreclosure|fsbo|owner|contact info|dnc)\b/',$l)) return ['Scout','Contact intelligence'];
  if(preg_match('/\b(jessica|email|follow up|appointment|schedule|calendar)\b/',$l)) return ['Jessica','Executive assistant follow-up'];
  if(preg_match('/\b(columbo|youtube|archive|old content|gold|repurpose|lost episode|mark inspires|discover connecticut)\b/',$l)) return ['Columbo','Archive gold mining'];
  if(preg_match('/\b(scorsese|video|short|reel|edit|render|comfy|thumbnail)\b/',$l)) return ['Scorsese','Creative production'];
  if(preg_match('/\b(shakespeare|blog|caption|article|seo|aeo|email copy|post)\b/',$l)) return ['Shakespeare','Writing and publishing'];
  if(preg_match('/\b(einstein|research|data|questions|search intent|market|analysis)\b/',$l)) return ['Einstein','Research and intelligence'];
  if(preg_match('/\b(prospector|campaign|opportunity|lead source|new leads)\b/',$l)) return ['Prospector','Opportunity expansion'];
  if(preg_match('/\b(rockefeller|roi|money|priority|revenue|rank|best move)\b/',$l)) return ['Rockefeller','ROI prioritization'];
  return ['Goliath','Open conversation'];
}
function g_context_snapshot(){
  $parts=[];
  [$h1,$events]=g_sb('GET','goliath_events',null,'?select=department,status,created_at,title&order=created_at.desc&limit=8');
  if(is_array($events) && count($events)){
    $parts[]='Recent Goliath events: '.implode('; ',array_map(function($e){return ($e['department']??'Goliath').': '.($e['title']??'activity');},array_slice($events,0,5)));
  }
  [$h2,$tasks]=g_sb('GET','local_ai_tasks',null,'?select=status,metadata,created_at&order=created_at.desc&limit=12');
  if(is_array($tasks)){
    $pending=0;$running=0;$done=0;foreach($tasks as $t){$s=$t['status']??'';if($s==='queued'||$s==='pending')$pending++;elseif($s==='running')$running++;elseif($s==='completed')$done++;}
    $parts[]="Worker snapshot: {$pending} pending, {$running} running, {$done} recently completed.";
  }
  return implode("\n",$parts);
}
function g_call_webhook($message,$conversation_id,$context){
  $urls=[];
  foreach(['GOLIATH_ASK_WEBHOOK_URL','N8N_ASK_GOLIATH_WEBHOOK','HERMES_ASK_WEBHOOK_URL','OPENCLAW_ASK_WEBHOOK_URL'] as $c){ if(defined($c) && constant($c)) $urls[]=constant($c); }
  foreach($urls as $url){
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['message'=>$message,'conversation_id'=>$conversation_id,'context'=>$context,'source'=>'ask_goliath_freeform']),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>35]);
    $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $j=json_decode($body,true);
    if($http>=200&&$http<300&&is_array($j)){
      $ans=$j['answer']??$j['response']??$j['text']??null;
      if($ans) return [$ans,'webhook'];
    }
  }
  return [null,null];
}
function g_local_answer($message,$agent,$lane,$context){
  $l=strtolower($message);
  if($agent==='Goliath'){
    return "Here is my executive read: this is an open conversation, not just a task. I would first identify the revenue impact, then decide whether Scout, Jessica, Columbo, Scorsese, Shakespeare, Einstein, Prospector, or Rockefeller should act. If you want, I can turn this into a command, but I am answering you first so the conversation stays natural.";
  }
  $base=[
    'Scout'=>'Scout should treat this as contact intelligence: find owner names, phone numbers, emails, source links, DNC status, confidence, and a clear next action for Mark to manually verify before calling.',
    'Jessica'=>'Jessica should treat this as executive assistant work: draft email-only follow-up in Mark’s voice, request the appointment, update the lead record, and alert Mark when someone is ready for a call.',
    'Columbo'=>'Columbo should treat this as archive gold mining: search Mark Inspires, Discover Connecticut, and older long-form content for moments worth repurposing, score them, then hand timestamps to Scorsese.',
    'Scorsese'=>'Scorsese should treat this as production work: turn the strongest asset into review-ready media with hook, title, captions, thumbnail direction, and distribution-ready formats.',
    'Shakespeare'=>'Shakespeare should treat this as writing work: create a tight, beautiful, Mark Pires-branded blog/email/social package tailored to the lead’s exact interest and search intent.',
    'Einstein'=>'Einstein should treat this as intelligence work: identify search questions, AEO angles, data points, buyer/seller objections, and the answer structure that makes Mark look like the obvious expert.',
    'Prospector'=>'Prospector should treat this as opportunity expansion: turn one lead or idea into campaigns, audiences, offer angles, and next-best outreach paths.',
    'Rockefeller'=>'Rockefeller should treat this as prioritization: rank the move by likely revenue, speed, effort, and whether it gets Mark closer to appointments.'
  ];
  return ($base[$agent]??'I understand.').' My recommendation: answer the immediate question first, then queue only the tasks that move appointments, content, or revenue forward.';
}

[$agent,$lane]=g_pick_intent($message);
$context=g_context_snapshot();
$trace=[
  ['label'=>'Heard','detail'=>'Captured the message in free-form conversation mode.'],
  ['label'=>'Classified','detail'=>$lane.' → '.$agent],
  ['label'=>'Checked context','detail'=>$context ? 'Pulled recent events/tasks for operating context.' : 'No database context available yet.'],
];
[$answer,$provider]=g_call_webhook($message,$conversation_id,$context);
if(!$answer){$answer=g_local_answer($message,$agent,$lane,$context);$provider='local_fallback';}
$trace[]=['label'=>'Answered','detail'=>$provider==='webhook'?'Answered through connected n8n/Hermes/OpenClaw webhook.':'Answered immediately through built-in Goliath fallback.'];

$should_queue=preg_match('/\b(tell|assign|queue|run|create|build|send|draft|find|mine|schedule|post|email|research)\b/i',$message) || $agent!=='Goliath';
$routed=null;
if($should_queue && $agent!=='Goliath'){
  [$h,$r]=g_sb('POST','local_ai_tasks',[[
    'task_type'=>'ask_goliath_'.$agent,
    'model'=>'goliath-agent',
    'prompt'=>$agent.' received from Ask Goliath: '.$message,
    'status'=>'queued',
    'priority'=>95,
    'metadata'=>['agent'=>$agent,'source'=>$source,'conversation_id'=>$conversation_id,'lane'=>$lane,'freeform_chat'=>true]
  ]]);
  if($h>=200&&$h<300){$routed=$agent.' task queued.';$trace[]=['label'=>'Routed','detail'=>$routed];}
}

g_sb('POST','goliath_events',[[
  'department'=>$agent==='Goliath'?'Rockefeller':$agent,
  'event_type'=>'ask_goliath_freeform',
  'title'=>'Ask Goliath conversation',
  'detail'=>$message,
  'roi_estimate'=>0,
  'confidence'=>95,
  'status'=>'answered',
  'link_url'=>'/dashboard/goliath-mission-control.php',
  'metadata'=>['agent'=>$agent,'lane'=>$lane,'answer'=>$answer,'conversation_id'=>$conversation_id,'trace'=>$trace,'provider'=>$provider]
]]);

echo json_encode(['success'=>true,'answer'=>$answer,'response'=>$answer,'agent'=>$agent,'lane'=>$lane,'conversation_id'=>$conversation_id,'trace'=>$trace,'routed'=>$routed,'mode'=>'freeform_conversation'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
