<?php
/**
 * Goliath Omni V45.7 — Lead Content + Distribution Cycle
 * Path: /lead-engine/goliath-content-cycle.php
 * Purpose: turns each buyer/seller/valuation lead into a content pack and a Blotato-style distribution queue.
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? ($_POST['key'] ?? '');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$expected){http_response_code(403);echo json_encode(['success'=>false,'error'=>'bad_key']);exit;}
$raw=file_get_contents('php://input');$body=json_decode($raw,true);if(!is_array($body))$body=$_POST?:[];
function gc_clean($v){if(is_array($v))return implode(', ',array_map('gc_clean',$v));return trim(strip_tags((string)$v));}
function gc_sb_insert($table,$rows){
  if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')||!SUPABASE_URL||!SUPABASE_SERVICE_ROLE_KEY)return ['ok'=>false,'http'=>0,'body'=>'Supabase not configured'];
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($rows),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  $out=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$out,'error'=>$err];
}
$lead=$body['lead'] ?? $body;if(!is_array($lead))$lead=[];
$lead=[
 'name'=>gc_clean($lead['name']??''),'email'=>strtolower(gc_clean($lead['email']??'')),'phone'=>gc_clean($lead['phone']??''),'type'=>gc_clean($lead['type']??($lead['form_type']??'general')),
 'town'=>gc_clean($lead['town']??($lead['towns']??($lead['target_towns']??''))),'address'=>gc_clean($lead['address']??''),'budget'=>gc_clean($lead['budget']??''),'price_range'=>gc_clean($lead['price_range']??''),'estimated_value'=>gc_clean($lead['estimated_value']??''),
 'style'=>gc_clean($lead['style']??($lead['property_style']??'')),'property_type'=>gc_clean($lead['property_type']??''),'timeline'=>gc_clean($lead['timeline']??''),'goal'=>gc_clean($lead['goal']??''),'message'=>gc_clean($lead['message']??($lead['notes']??'')),'source'=>gc_clean($lead['source']??'markpires.com')
];
$price=$lead['budget'] ?: $lead['estimated_value'] ?: $lead['price_range'];
$intent=strtolower($lead['type'].' '.$lead['goal'].' '.$lead['message']);
$persona=(strpos($intent,'sell')!==false||strpos($intent,'valuation')!==false)?'seller':'buyer';
$topic=trim(($lead['town']?:'Fairfield County').' '.($lead['style']?:$lead['property_type']).' '.($persona==='seller'?'home value / selling strategy':'home search / buying strategy'));
$platforms=['email_followup','blog_article','facebook','instagram','linkedin','tiktok','youtube_shorts','google_business_profile'];
$tasks=[];$events=[];$priority=95;
$master="GOLIATH CLIENT-SPECIFIC CONTENT CYCLE\nPersona: {$persona}\nTopic: {$topic}\nLead: ".($lead['name']?:'Unknown')." | Town/search area: {$lead['town']} | Price/value: {$price} | Timeline: {$lead['timeline']} | Notes: {$lead['message']}\n\nCreate content that helps this exact person and can also be repurposed publicly without exposing private lead information.";
$tasks[]=['task_type'=>'client_content_research','model'=>'local_orchestrator','prompt'=>$master."\n\nSCOUT + PROSPECTOR: research the search intent, town angles, property style, and appointment hooks. Return safe content angles and lead follow-up insights.",'status'=>'queued','priority'=>$priority--,'metadata'=>['cycle'=>'client_content_cycle','agent'=>'Scout','lead'=>$lead,'topic'=>$topic,'persona'=>$persona]];
$tasks[]=['task_type'=>'client_content_pack','model'=>'local_orchestrator','prompt'=>$master."\n\nSHAKESPEARE: write the custom email, blog article outline/full article, ad article angle, captions for every platform, CTAs, and subject lines. Include a private email version for the lead and public-safe versions for social.",'status'=>'queued','priority'=>$priority--,'metadata'=>['cycle'=>'client_content_cycle','agent'=>'Shakespeare','lead'=>$lead,'topic'=>$topic,'persona'=>$persona]];
$tasks[]=['task_type'=>'client_media_pack','model'=>'local_orchestrator','prompt'=>$master."\n\nSCORSESE: create the video package: 9:16 short script, 1:1 social cut, 16:9 YouTube/ad version, thumbnail prompt, b-roll prompts, image prompts, captions, hook score, and revision gate. Send outputs to Goliath Studio.",'status'=>'queued','priority'=>$priority--,'metadata'=>['cycle'=>'client_content_cycle','agent'=>'Scorsese','lead'=>$lead,'topic'=>$topic,'persona'=>$persona,'studio'=>true]];
foreach($platforms as $p){
  $tasks[]=['task_type'=>'blotato_distribution_queue','model'=>'scheduler','prompt'=>$master."\n\nGOLIATH DISTRIBUTION / BLOTATO-STYLE QUEUE: Prepare and schedule the {$p} asset. Do not publish private lead information. Use the public-safe angle while the private email goes only to the lead.",'status'=>'queued','priority'=>$priority--,'metadata'=>['cycle'=>'blotato_distribution','platform'=>$p,'lead'=>$lead,'topic'=>$topic,'persona'=>$persona,'status'=>'needs_asset']];
  $events[]=['department'=>'Shakespeare','event_type'=>'blotato_distribution_queued','title'=>'Distribution queued: '.str_replace('_',' ',$p),'detail'=>$topic.' | '.$persona.' | '.$price,'roi_estimate'=>900,'confidence'=>88,'status'=>'queued','link_url'=>'/dashboard/goliath-distribution-queue.php','metadata'=>['cycle'=>'blotato_distribution','platform'=>$p,'lead'=>$lead,'topic'=>$topic,'persona'=>$persona]];
}
$events[]=['department'=>'Rockefeller','event_type'=>'client_content_cycle','title'=>'New lead-to-content cycle started','detail'=>$topic.' | '.$persona.' | '.$price,'roi_estimate'=>3500,'confidence'=>92,'status'=>'queued','link_url'=>'/dashboard/goliath-distribution-queue.php','metadata'=>['cycle'=>'client_content_cycle','lead'=>$lead,'topic'=>$topic,'persona'=>$persona]];
$taskRes=gc_sb_insert('local_ai_tasks',$tasks);$eventRes=gc_sb_insert('goliath_events',$events);
echo json_encode(['success'=>$taskRes['ok']||$eventRes['ok'],'message'=>'Client-specific content + Goliath Blotato-style distribution cycle queued.','persona'=>$persona,'topic'=>$topic,'tasks_queued'=>count($tasks),'events_queued'=>count($events),'supabase'=>['tasks'=>$taskRes,'events'=>$eventRes]]);
