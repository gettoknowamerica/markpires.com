<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && $key && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}
function classify_intent($text){
  $t=strtolower($text);
  if(preg_match('/\b(remind|task|todo|to do|remember to)\b/',$t)) return 'task';
  if(preg_match('/\b(calendar|appointment|schedule|meeting|put .* calendar)\b/',$t)) return 'calendar';
  if(preg_match('/\b(video|reel|short|edit|scorsese|comfy|clip)\b/',$t)) return 'creative_video';
  if(preg_match('/\b(blog|post|caption|email|shakespeare|seo|aeo)\b/',$t)) return 'content';
  if(preg_match('/\b(lead|buyer|seller|client|follow up|phone number)\b/',$t)) return 'lead';
  if(preg_match('/\b(idea|business|startup|should i build|what do you think)\b/',$t)) return 'idea';
  if(preg_match('/\b(today|briefing|what is happening|plan)\b/',$t)) return 'briefing';
  return 'conversation';
}
$text=trim($data['message'] ?? $data['text'] ?? '');
if(!$text){echo json_encode(['success'=>false,'error'=>'Missing message']);exit;}
$intent=classify_intent($text);$priority=70;$department='Goliath';$action='answer';
if($intent==='creative_video'){$department='Scorsese';$priority=125;$action='delegate';}
if($intent==='content'){$department='Shakespeare';$priority=110;$action='delegate';}
if($intent==='lead'){$department='Rockefeller';$priority=130;$action='analyze';}
if($intent==='task'){$department='Goliath';$priority=95;$action='save_task';}
if($intent==='calendar'){$department='Goliath';$priority=100;$action='calendar_request';}
if($intent==='idea'){$department='Einstein';$priority=85;$action='save_idea';}
$response='I heard you, Mark. ';$created=[];
if($intent==='task'){
  [$h,$r]=sb('POST','goliath_tasks',[['title'=>substr($text,0,120),'detail'=>$text,'priority'=>$priority,'department'=>'Goliath','source'=>'executive_core','metadata'=>['intent'=>$intent]]]);
  $created['task']=$r[0]??$r;$response.='I saved that as a task. I will keep it visible until it is handled.';
}elseif($intent==='idea'){
  $score=preg_match('/lead|real estate|video|content|listing|seller|buyer|goliath|discover/i',$text)?90:75;
  [$h,$r]=sb('POST','goliath_ideas',[['title'=>substr($text,0,90),'idea'=>$text,'score'=>$score,'recommendation'=>$score>=88?'Strong fit. Build a fast MVP and connect it to revenue or authority.':'Worth saving, but keep it secondary until core revenue tasks are done.','next_step'=>$score>=88?'Ask Rockefeller for ROI and Shakespeare for positioning.':'Park it in ideas and revisit after current priorities.','metadata'=>['intent'=>$intent]]]);
  $created['idea']=$r[0]??$r;$response.='I saved this idea and gave it an executive score of '.$score.'.';
}elseif($intent==='creative_video'||$intent==='content'||$intent==='lead'){
  [$h,$r]=sb('POST','goliath_commands',[['command_type'=>'executive_core_'.$intent,'department'=>$department,'title'=>'Executive Core Request','prompt'=>$text,'status'=>'queued','priority'=>$priority,'source'=>'executive_core','brand'=>'mark_pires','metadata'=>['intent'=>$intent,'from'=>'Ask Goliath']]]);
  $created['command']=$r[0]??$r;$response.='I assigned this to '.$department.'. I will bring it back when it is ready for review.';
}elseif($intent==='calendar'){
  [$h,$r]=sb('POST','goliath_tasks',[['title'=>'Calendar request: '.substr($text,0,90),'detail'=>$text,'priority'=>$priority,'department'=>'Goliath','source'=>'executive_core','metadata'=>['intent'=>$intent,'needs_google_calendar'=>true]]]);
  $created['calendar_request']=$r[0]??$r;$response.='I saved this as a calendar request. The next layer will create it directly in Google Calendar.';
}elseif($intent==='briefing'){
  $response.='I am preparing the briefing layer. For now, Mission Control has the live work queues, leads, and review-ready media.';
}else{
  $response.='I can think through that with you. I will treat this as an open CEO conversation unless you ask me to save it, schedule it, or assign it.';
}
sb('POST','goliath_events',[['department'=>$department,'event_type'=>'executive_core','title'=>'Goliath understood a '.$intent.' request','detail'=>$text,'roi_estimate'=>0,'confidence'=>92,'status'=>'handled','phase'=>'executive_core','progress'=>100,'link_url'=>'/dashboard/goliath-executive-core.php','metadata'=>['intent'=>$intent,'action'=>$action,'created'=>$created]]]);
echo json_encode(['success'=>true,'intent'=>$intent,'department'=>$department,'action'=>$action,'response'=>$response,'created'=>$created],JSON_PRETTY_PRINT);
?>