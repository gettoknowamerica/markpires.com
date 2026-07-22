<?php
/**
 * Goliath Omni V45.8 — Columbo Content Archaeologist
 * Path: /lead-engine/goliath-columbo-archive.php
 * Purpose: queue Columbo jobs to mine Mark's long-form YouTube archive for shorts-worthy gold.
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? ($_POST['key'] ?? '');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$expected){http_response_code(403);echo json_encode(['success'=>false,'error'=>'bad_key']);exit;}
$raw=file_get_contents('php://input');$body=json_decode($raw,true);if(!is_array($body))$body=$_POST?:[];
function ca_clean($v){if(is_array($v))return implode(', ',array_map('ca_clean',$v));return trim(strip_tags((string)$v));}
function ca_insert($table,$rows){
  if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')||!SUPABASE_URL||!SUPABASE_SERVICE_ROLE_KEY)return ['ok'=>false,'http'=>0,'body'=>'Supabase not configured'];
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($rows),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  $out=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$out,'error'=>$err];
}
$action=ca_clean($body['action'] ?? ($_GET['action'] ?? 'seed_archive'));
$channels=$body['channels'] ?? null;
if(!is_array($channels)||!$channels){
  $channels=[
    ['name'=>'Mark Inspires The World','url'=>'https://www.youtube.com/@MarkInspiresTheWorld/videos','brand'=>'Mark insPires','content_types'=>['music','comedy','powerful moments','motivation','original songs']],
    ['name'=>'Discover Connecticut with Mark Pires','url'=>'https://www.youtube.com/@DiscoverConnecticutwithMarkPires/videos','brand'=>'Discover CT','content_types'=>['town features','street interviews','real estate','local businesses','community gold']]
  ];
}
$limit=(int)($body['limit'] ?? ($_GET['limit'] ?? 12)); if($limit<1)$limit=12; if($limit>100)$limit=100;
$tasks=[];$events=[];$priority=100;
$baseDirective="COLUMBO CONTENT ARCHAEOLOGIST — ONE JOB ONLY\n\nYou are Columbo. Your entire mission is to rediscover gold inside Mark Pires' long-form archive. Mine old YouTube episodes, especially 4-hour and 8-hour shows that barely got viewed. Find songs, comedy, emotional moments, powerful lessons, CT community moments, BeatSeat moments, House Detective/noir moments, and anything that can become a high-retention short.\n\nFor every find, return: source channel, video title, video URL, timestamp start/end, quote or moment summary, why it matters, viral hook, short title, description, tags, best platform, best posting window, and Scorsese cut instructions. Never mark weak moments complete. Score Hook, Emotion, Originality, Music/Comedy/Story, and Shorts Potential from 1-100. Only moments above 82 go to Scorsese.";
if($action==='scan_episode'){
  $url=ca_clean($body['url'] ?? ($_GET['url'] ?? ''));
  $title=ca_clean($body['title'] ?? 'Manual Episode Scan');
  if(!$url){echo json_encode(['success'=>false,'error'=>'missing_episode_url']);exit;}
  $prompt=$baseDirective."\n\nSCAN THIS SINGLE EPISODE NOW:\nTitle: {$title}\nURL: {$url}\n\nFirst inventory metadata/transcript if available, then produce the best shorts candidates. Send winners to Scorsese as cut briefs.";
  $tasks[]=['task_type'=>'columbo_episode_scan','model'=>'archive_miner','prompt'=>$prompt,'status'=>'queued','priority'=>$priority--,'metadata'=>['agent'=>'Columbo','cycle'=>'archive_gold_mining','action'=>'scan_episode','url'=>$url,'title'=>$title,'send_to'=>'Scorsese']];
  $events[]=['department'=>'Columbo','event_type'=>'archive_gold_mining','title'=>'Columbo episode scan queued','detail'=>$title.' | '.$url,'roi_estimate'=>5000,'confidence'=>94,'status'=>'queued','link_url'=>'/dashboard/goliath-columbo-vault.php','metadata'=>['agent'=>'Columbo','cycle'=>'archive_gold_mining','url'=>$url,'title'=>$title]];
}else{
  foreach($channels as $c){
    $name=ca_clean($c['name']??'YouTube Channel');$url=ca_clean($c['url']??'');$brand=ca_clean($c['brand']??'Mark Pires');$types=$c['content_types']??[];
    if(!$url)continue;
    $prompt=$baseDirective."\n\nCHANNEL INVENTORY JOB:\nChannel: {$name}\nBrand: {$brand}\nURL: {$url}\nLimit this pass to {$limit} videos. Prioritize old long-form videos with low views or hidden value. Content categories to hunt: ".ca_clean($types).".\n\nOutput two sections:\n1) Archive inventory with videos worth scanning next.\n2) Immediate shorts candidates if transcript/metadata is available.\n\nFor every candidate, create a Scorsese-ready cut brief.";
    $tasks[]=['task_type'=>'columbo_channel_inventory','model'=>'archive_miner','prompt'=>$prompt,'status'=>'queued','priority'=>$priority--,'metadata'=>['agent'=>'Columbo','cycle'=>'archive_gold_mining','action'=>'channel_inventory','channel_name'=>$name,'channel_url'=>$url,'brand'=>$brand,'limit'=>$limit,'send_to'=>'Scorsese']];
    $events[]=['department'=>'Columbo','event_type'=>'archive_gold_mining','title'=>'Columbo archive hunt queued','detail'=>$name.' | first '.$limit.' videos','roi_estimate'=>7500,'confidence'=>95,'status'=>'queued','link_url'=>'/dashboard/goliath-columbo-vault.php','metadata'=>['agent'=>'Columbo','cycle'=>'archive_gold_mining','channel_name'=>$name,'channel_url'=>$url,'brand'=>$brand,'limit'=>$limit]];
  }
}
$taskRes=ca_insert('local_ai_tasks',$tasks);$eventRes=ca_insert('goliath_events',$events);
echo json_encode(['success'=>$taskRes['ok']||$eventRes['ok'],'message'=>'Columbo archive gold-mining queued.','tasks_queued'=>count($tasks),'events_queued'=>count($events),'next'=>'Run local-runner/goliath-columbo-archive-runner.py on the desktop that has Ollama/Comfy/yt-dlp available.','supabase'=>['tasks'=>$taskRes,'events'=>$eventRes]]);
