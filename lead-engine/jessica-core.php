<?php
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function jc_sb($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>60
  ]);
  if($payload!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function jc_rows($t,$q){$r=jc_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
function jc_num($v){ return is_numeric($v) ? (float)$v : 0; }
function jc_route_lead($lead){
  $value=jc_num($lead['estimated_value']??0);
  $route=$lead['route']??'';
  if($value>=1000000) return 'Immediate Mark Alert';
  if($value>=500000) return 'Mark Priority';
  if($value>0 && $value<500000) return 'Jessica Follow-Up';
  if(stripos($route,'referral')!==false) return 'Referral Network';
  return 'Review';
}
function jc_context(){
  $leads=jc_rows('leads','select=id,name,email,phone,address,town,towns,timeline,estimated_value,lead_score,adaptive_score,route,type,status,created_at&order=created_at.desc&limit=15');
  $seller=jc_rows('seller_acquisition_director','select=property_address,town,acquisition_score,recommended_action,estimated_commission&order=acquisition_score.desc&limit=8');
  $traffic=jc_rows('traffic_performance','select=source_name,traffic_score,scale_recommendation,recommended_action&order=traffic_date.desc,traffic_score.desc&limit=8');
  $campaigns=jc_rows('advertising_campaigns','select=campaign_name,provider,status,daily_budget,leads,appointments,command_score,jessica_recommendation,next_action&order=command_score.desc&limit=8');
  if(!$campaigns) $campaigns=jc_rows('campaign_command_center','select=campaign_name,command_stage,command_score,recommended_daily_action&order=command_score.desc&limit=8');
  $creative=jc_rows('media_clip_intelligence','select=clip_title,platform,hook_line,viral_score,retention_score,conversion_score,status&order=viral_score.desc&limit=8');
  $reviews=jc_rows('media_editor_reviews','select=review_status,corrected_caption,corrected_cta,top_title_text,updated_at&order=updated_at.desc&limit=8');
  $audio=jc_rows('media_audio_reviews','select=audio_status,jessica_audio_notes,updated_at&order=updated_at.desc&limit=8');
  $memory=jc_rows('jessica_memory','select=memory_type,category,importance,title,question,response,created_at&order=importance.desc,created_at.desc&limit=8');
  return compact('leads','seller','traffic','campaigns','creative','reviews','audio','memory');
}
function jc_brief(){
  $c=jc_context(); $hot=[];
  foreach($c['leads'] as $l){
    $score=(int)($l['adaptive_score']??$l['lead_score']??0);
    $value=jc_num($l['estimated_value']??0);
    if($score>=70 || $value>=500000) $hot[]=[
      'name'=>$l['name']??'Unknown','address'=>$l['address']??'','value'=>$value,
      'score'=>$score,'route'=>jc_route_lead($l),'timeline'=>$l['timeline']??''
    ];
  }
  $topCampaign=$c['campaigns'][0]??null; $topCreative=$c['creative'][0]??null; $topTraffic=$c['traffic'][0]??null;
  $priorities=[];
  if($hot) $priorities[]='Call or review top Mark-priority lead: '.$hot[0]['name'].' '.$hot[0]['address'];
  if($topCampaign) $priorities[]='Review campaign: '.($topCampaign['campaign_name']??'campaign').' — '.($topCampaign['jessica_recommendation']??$topCampaign['recommended_daily_action']??'needs action');
  if($topCreative) $priorities[]='Review creative clip: '.($topCreative['clip_title']??'top clip').' — viral score '.($topCreative['viral_score']??'');
  if($topTraffic) $priorities[]='Traffic focus: '.($topTraffic['source_name']??'source').' — '.($topTraffic['scale_recommendation']??'');
  return ['success'=>true,'greeting'=>'Good morning Mark. Jessica Core is online.','summary'=>[
    'new_leads'=>count($c['leads']),'hot_leads'=>count($hot),'seller_opportunities'=>count($c['seller']),
    'campaigns'=>count($c['campaigns']),'creative_items'=>count($c['creative']),'audio_reviews'=>count($c['audio'])
  ],'hot_leads'=>$hot,'top_campaign'=>$topCampaign,'top_creative'=>$topCreative,'top_traffic'=>$topTraffic,'priorities'=>$priorities?:['Run your builders and review new leads/content.'],'context'=>$c];
}
function jc_detect_mode($msg,$fallback='executive'){
  $m=strtolower($msg);
  if(str_contains($m,'lead')||str_contains($m,'call')||str_contains($m,'seller')||str_contains($m,'valuation')) return 'lead';
  if(str_contains($m,'film')||str_contains($m,'content')||str_contains($m,'creative')||str_contains($m,'clip')||str_contains($m,'video')||str_contains($m,'audio')) return 'creative';
  if(str_contains($m,'ad')||str_contains($m,'campaign')||str_contains($m,'budget')||str_contains($m,'meta')||str_contains($m,'google')||str_contains($m,'youtube')) return 'advertising';
  if(str_contains($m,'revenue')||str_contains($m,'commission')||str_contains($m,'roi')||str_contains($m,'money')) return 'revenue';
  if(str_contains($m,'research')||str_contains($m,'search')||str_contains($m,'what is')||str_contains($m,'news')) return 'research';
  return $fallback ?: 'executive';
}
function jc_answer($msg,$mode){
  $brief=jc_brief(); $m=strtolower($msg); $out=[]; $intent='general';
  if(str_contains($m,'remember') || str_contains($m,'save this')){
    jc_sb('POST','jessica_memory',[['memory_type'=>'preference','category'=>$mode,'importance'=>80,'title'=>'Mark instruction','question'=>$msg,'response'=>'Saved by Jessica Core.','notes'=>$msg,'source'=>'jessica_os','created_at'=>date('c'),'updated_at'=>date('c')]]);
    return ['text'=>'Saved to memory. I will use that preference going forward.','intent'=>'memory_save','brief'=>$brief];
  }
  if(str_contains($m,'what should i do')||str_contains($m,'focus')||str_contains($m,'today')||str_contains($m,'executive')){
    $intent='executive_brief';
    $out[]=$brief['greeting'];
    $out[]='Today I see '.$brief['summary']['new_leads'].' recent leads, '.$brief['summary']['hot_leads'].' hot leads, '.$brief['summary']['campaigns'].' campaigns, and '.$brief['summary']['creative_items'].' creative items.';
    $out[]='Top priorities:'; foreach(array_slice($brief['priorities'],0,5) as $i=>$p){$out[]=($i+1).'. '.$p;}
  } elseif($mode==='lead'){
    $intent='lead_brain'; $out[]='Lead Brain: $1M+ immediate Mark alert, $500k+ Mark priority, under $500k Jessica follow-up, outside area referral.';
    if($brief['hot_leads']){ $out[]='Top leads to review:'; foreach(array_slice($brief['hot_leads'],0,6) as $i=>$l){$val=$l['value']?('$'.number_format($l['value'])):'value unknown'; $out[]=($i+1).'. '.$l['name'].' — '.$l['address'].' — '.$val.' — score '.$l['score'].' — '.$l['route'];}} else $out[]='I do not see hot leads yet. Run seller acquisition and traffic builders.';
  } elseif($mode==='creative'){
    $intent='creative_brain'; $out[]='Creative Brain: focus on strongest emotional hook, local curiosity, and clear CTA.'; $creative=$brief['context']['creative']??[];
    if($creative){$out[]='Best creative opportunities:'; foreach(array_slice($creative,0,6) as $i=>$c){$out[]=($i+1).'. '.($c['clip_title']??'Clip').' — '.($c['hook_line']??'hook needed').' — viral score '.($c['viral_score']??0);}} else $out[]='Upload a Discover CT or House Detective video, then run Media Director, Shorts Factory, and Content Intelligence.';
  } elseif($mode==='advertising'){
    $intent='advertising_brain'; $out[]='Advertising Brain: I recommend, but human approval should stay required before any spend moves.'; $campaigns=$brief['context']['campaigns']??[];
    if($campaigns){$out[]='Campaigns to review:'; foreach(array_slice($campaigns,0,6) as $i=>$c){$out[]=($i+1).'. '.($c['campaign_name']??'Campaign').' — score '.($c['command_score']??0).' — '.($c['jessica_recommendation']??$c['recommended_daily_action']??'review');}} else $out[]='Run Advertising Command Center to create starter campaigns.';
  } elseif($mode==='revenue'){
    $intent='revenue_brain'; $out[]='Revenue Brain foundation: I am watching lead source, campaign, appointment, listing, and commission signals.'; $out[]='Current top money action: call Mark-priority leads first, then approve high-score seller campaigns.';
  } elseif($mode==='research'){
    $intent='research_brain'; $out[]='Research Mode is active. Right now I can search internal Goliath OS data. Live web/news/model routing will be connected in a later patch.'; $out[]='Ask me about leads, campaigns, content, clips, traffic, or priorities.';
  } else {
    $out[]='Jessica Core is online. Ask me what to focus on today, who to call, what to film, which campaign to scale, or what is producing revenue.';
  }
  return ['text'=>implode("\n\n",$out),'intent'=>$intent,'brief'=>$brief];
}
try{
  $input=json_decode(file_get_contents('php://input'),true); if(!is_array($input)) $input=array_merge($_GET,$_POST);
  $key=$input['key'] ?? ($_GET['key']??'');
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;}
  $action=$input['action']??'brief';
  if($action==='brief'){echo json_encode(jc_brief(),JSON_PRETTY_PRINT); exit;}
  if($action==='ask'){
    $message=trim($input['message']??''); $mode=jc_detect_mode($message,$input['mode']??'executive'); $ans=jc_answer($message,$mode);
    jc_sb('POST','jessica_conversations',[['conversation_date'=>date('Y-m-d'),'mode'=>$mode,'user_message'=>$message,'jessica_response'=>$ans['text'],'intent'=>$ans['intent'],'confidence'=>85,'raw_context'=>$ans['brief']['summary']??[],'created_at'=>date('c')]]);
    jc_sb('POST','jessica_memory',[['memory_type'=>'conversation','category'=>$mode,'importance'=>45,'title'=>'Conversation: '.$ans['intent'],'question'=>$message,'response'=>$ans['text'],'source'=>'jessica_os','created_at'=>date('c'),'updated_at'=>date('c')]]);
    echo json_encode(['success'=>true,'mode'=>$mode,'intent'=>$ans['intent'],'response'=>$ans['text'],'brief'=>$ans['brief']],JSON_PRETTY_PRINT); exit;
  }
  if($action==='memory'){echo json_encode(['success'=>true,'memory'=>jc_rows('jessica_memory','select=*&order=importance.desc,created_at.desc&limit=100')],JSON_PRETTY_PRINT); exit;}
  echo json_encode(['success'=>false,'error'=>'Unknown action']);
}catch(Throwable $e){http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>