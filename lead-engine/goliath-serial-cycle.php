<?php
/**
 * Goliath Omni V45.3 — Serial Lead-to-Content Cycle
 * Path: /lead-engine/goliath-serial-cycle.php
 * Purpose: every buyer/seller/valuation form submission creates the 8-agent chain:
 * capture -> immediate email/Retell -> research -> opportunity mining -> analysis -> content -> media -> schedule -> appointment text rescue -> ROI priority.
 */
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw=file_get_contents('php://input');
$body=json_decode($raw,true); if(!is_array($body)) $body=$_POST ?: [];
$key=$body['key'] ?? ($_GET['key'] ?? '');
$expected=defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key!==$expected){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'bad_key']); exit; }
function gclean($v){ if(is_array($v)) return implode(', ',array_map('gclean',$v)); return trim(strip_tags((string)$v)); }
function gi($n,$d=''){ global $body; return gclean($body[$n] ?? $d); }
function sb_insert_rows($table,$rows){
  if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')||!SUPABASE_URL||!SUPABASE_SERVICE_ROLE_KEY) return ['ok'=>false,'http'=>0,'body'=>'Supabase not configured'];
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($rows),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  $out=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$out,'error'=>$err];
}
function build_lead_from_input(){
  global $body;
  $lead=$body['lead'] ?? $body;
  if(!is_array($lead)) $lead=[];
  return [
    'name'=>gclean($lead['name']??''),'email'=>strtolower(gclean($lead['email']??'')),'phone'=>gclean($lead['phone']??''),'address'=>gclean($lead['address']??''),
    'town'=>gclean($lead['town']??($lead['towns']??($lead['target_towns']??''))),'type'=>gclean($lead['type']??($lead['form_type']??'general')),
    'timeline'=>gclean($lead['timeline']??''),'goal'=>gclean($lead['goal']??''),'message'=>gclean($lead['message']??($lead['notes']??'')),
    'price_range'=>gclean($lead['price_range']??''),'estimated_value'=>gclean($lead['estimated_value']??($lead['value_range']??'')),'budget'=>gclean($lead['budget']??''),
    'style'=>gclean($lead['style']??($lead['property_style']??'')),'property_type'=>gclean($lead['property_type']??''),'source'=>gclean($lead['source']??'markpires.com'),
    'route'=>gclean($lead['route']??''),'lead_score'=>gclean($lead['lead_score']??''),'page_url'=>gclean($lead['page_url']??($_SERVER['HTTP_REFERER']??'')),'created_at'=>date('c')
  ];
}
$lead=build_lead_from_input();
$summary='Lead: '.($lead['name']?:'Unknown').' | Type: '.$lead['type'].' | Towns: '.$lead['town'].' | Price/value: '.($lead['budget']?:$lead['estimated_value']?:$lead['price_range']).' | Style: '.$lead['style'].' '.$lead['property_type'].' | Timeline: '.$lead['timeline'].' | Notes: '.$lead['message'];
$agents=[
  'Scout'=>'Research the lead context: towns, property type, price point, local search intent, relevant comps/themes and data angles. Return facts and source-safe angles for content.',
  'Prospector'=>'Find the highest-value opportunities this lead creates: buyer content angles, seller angles, referral/auction potential, town gaps, and follow-up hooks.',
  'Einstein'=>'Analyze conversion intent, SEO/AEO opportunities, social platform fit, lead score, and what content would most likely move this person to an appointment.',
  'Rockefeller'=>'Rank the money path: direct Mark priority, referral, auction, nurture, expected ROI, and exact next best action.',
  'Jessica'=>'Prepare immediate follow-up context. Confirm the email went out, prep call notes, and if appropriate prepare a friendly call/touch sequence.',
  'Columbo'=>'Investigate missing context and create a text-first appointment rescue message. Do not call for this task. Text to make the appointment happen.',
  'Shakespeare'=>'Create a client-specific content pack: email follow-up, blog angle, Facebook/Instagram/LinkedIn captions, YouTube Shorts title, TikTok hook, and CTA tied to the lead intent.',
  'Scorsese'=>'Create the media brief: visual direction, thumbnail idea, 9:16 short, 1:1 square, 16:9 video/ad concept, b-roll/image prompts, quality gate and revision prompt.'
];
$tasks=[];$events=[];$priority=100;
foreach($agents as $agent=>$directive){
  $prompt="SERIAL MODE — NEW LEAD CONTENT CYCLE\n\n".$directive."\n\nLead summary:\n".$summary."\n\nWorkflow rule: This is not a one-off answer. Create output that can move to the next agent, become content, enter the scheduler, and be repurposed across all platforms for this exact buyer/seller/valuation lead.";
  $tasks[]=['task_type'=>'serial_lead_cycle','model'=>'local_orchestrator','prompt'=>$prompt,'status'=>'queued','priority'=>$priority--,'metadata'=>['agent'=>$agent,'serial_mode'=>true,'cycle'=>'lead_to_content','lead'=>$lead,'source'=>'goliath-serial-cycle.php']];
  $events[]=['department'=>$agent,'event_type'=>'serial_lead_cycle','title'=>$agent.' serial task queued','detail'=>$summary,'roi_estimate'=>$agent==='Rockefeller'?3500:1200,'confidence'=>91,'status'=>'queued','link_url'=>'/dashboard/goliath-agent-detail.php?department='.rawurlencode($agent),'metadata'=>['agent'=>$agent,'serial_mode'=>true,'cycle'=>'lead_to_content','lead'=>$lead]];
}
$taskRes=sb_insert_rows('local_ai_tasks',$tasks);
$eventRes=sb_insert_rows('goliath_events',$events);
echo json_encode(['success'=>$taskRes['ok']||$eventRes['ok'],'message'=>'Goliath Serial Mode queued the 8-agent lead-to-content cycle.','lead'=>$lead,'tasks_queued'=>count($tasks),'events_queued'=>count($events),'supabase'=>['tasks'=>$taskRes,'events'=>$eventRes]]);
