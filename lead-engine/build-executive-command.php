<?php
/**
 * V14.4B Executive Command Mode
 * Upload: /public_html/lead-engine/build-executive-command.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb144c($method,$endpoint,$payload=null){
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
      CURLOPT_TIMEOUT=>45
    ]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch);
    $h=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows144c($t,$q){$r=sb144c('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function first144c($arr,$field,$fallback=''){return !empty($arr[0][$field])?$arr[0][$field]:$fallback;}

  $focus = $_GET['focus'] ?? 'daily_growth';
  $caller = $_GET['caller_phone'] ?? '2032472655';

  $pipeline = rows144c('jessica_opportunity_pipeline','select=*&status=eq.active&order=priority_score.desc,created_at.desc&limit=25');
  $listings = rows144c('listing_intelligence_opportunities','select=*&status=eq.active&order=call_eligible.desc,listing_probability_score.desc&limit=25');
  $heat = rows144c('market_heat_snapshots','select=*&order=total_heat.desc,created_at.desc&limit=15');
  $seo = rows144c('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=25');
  $seller = rows144c('seller_opportunity_sources','select=*&status=eq.active&order=total_seller_score.desc,created_at.desc&limit=25');
  $enrich = rows144c('contact_enrichment_queue','select=*&status=eq.active&order=priority_score.desc,created_at.desc&limit=25');
  $creative = rows144c('creative_review_items','select=*&order=priority_score.desc,created_at.desc&limit=25');
  $voice = rows144c('business_call_log','select=*&order=priority_score.desc,call_date.desc&limit=25');

  $topMarket = first144c($heat,'town','No market heat yet');
  $topLead = first144c($pipeline,'name','No active lead yet');
  $topListing = first144c($listings,'name', first144c($listings,'opportunity_name','No listing intelligence yet'));

  $actions = [];
  $actions[] = ['cat'=>'lead','title'=>'Work the top call queue first','details'=>'Open Opportunity Pipeline and handle call_queue / callback_needed records before new prospecting.','score'=>100];
  $actions[] = ['cat'=>'seller_acquisition','title'=>'Feed seller sources','details'=>'Add or import FSBO, expired, withdrawn, and price-reduced records into V14.1 Seller Opportunity Engine.','score'=>95];
  $actions[] = ['cat'=>'enrichment','title'=>'Enrich highest-value records','details'=>'Open V14.2 Contact Enrichment and find phone/email only for A/B priority opportunities first.','score'=>90];
  $actions[] = ['cat'=>'compliance','title'=>'Run Realtor Exclusion before calls','details'=>'Run V14.3 Realtor Exclusion before approving any owner list or seller source for calls.','score'=>88];
  $actions[] = ['cat'=>'content','title'=>'Create one seller-market authority asset','details'=>'Use the top heat market and top SEO/AEO opportunity to create one post/video/ad today.','score'=>82];

  $strategy = "JESSICA EXECUTIVE STRATEGY MODE\\n";
  $strategy .= "========================================\\n\\n";
  $strategy .= "Focus: {$focus}\\n";
  $strategy .= "Top Market: {$topMarket}\\n";
  $strategy .= "Top Lead: {$topLead}\\n";
  $strategy .= "Top Listing Opportunity: {$topListing}\\n\\n";
  $strategy .= "TODAY'S OPERATING ORDER\\n----------------------------------------\\n";
  $strategy .= "1. Handle callbacks and call-queue records first.\\n";
  $strategy .= "2. Feed Jessica with high-intent seller sources: FSBO, expired, withdrawn, price-reduced.\\n";
  $strategy .= "3. Enrich only A/B priority records before buying broad lists.\\n";
  $strategy .= "4. Run DNC/Realtor exclusion before approval.\\n";
  $strategy .= "5. Publish or approve one seller authority asset for the top market.\\n\\n";
  $strategy .= "TOP PIPELINE ITEMS\\n----------------------------------------\\n";
  foreach(array_slice($pipeline,0,8) as $i=>$p){
    $strategy .= ($i+1).". ".(($p['name']??'')?:'Unnamed')." — ".($p['pipeline_stage']??'')." — ".($p['town']??'')." — Score ".($p['priority_score']??0)."\\n";
  }
  $strategy .= "\\nTOP SELLER FOOD SOURCES\\n----------------------------------------\\n";
  foreach(array_slice($seller,0,8) as $i=>$s){
    $strategy .= ($i+1).". ".(($s['property_address']??'')?:($s['listing_title']??'Seller Source'))." — ".($s['town']??'')." — ".($s['source_type']??'')." — Score ".($s['total_seller_score']??0)."\\n";
  }
  $strategy .= "\\nEXECUTIVE ACTIONS\\n----------------------------------------\\n";
  foreach($actions as $i=>$a){
    $strategy .= ($i+1).". ".$a['title']." — ".$a['details']."\\n";
  }

  $sessionPayload=[[
    'session_date'=>date('c'),
    'trigger_phrase'=>'timetomakethedonuts',
    'caller_phone'=>$caller,
    'command_mode'=>'executive_strategy',
    'requested_focus'=>$focus,
    'session_summary'=>'Executive command session generated from Jessica system data.',
    'strategy_text'=>$strategy,
    'hot_leads'=>array_slice($pipeline,0,10),
    'priority_markets'=>array_slice($heat,0,10),
    'listing_opportunities'=>array_slice($listings,0,10),
    'seo_aeo_opportunities'=>array_slice($seo,0,10),
    'creative_priorities'=>array_slice($creative,0,10),
    'acquisition_priorities'=>array_slice($enrich,0,10),
    'recommended_actions'=>$actions,
    'status'=>'open',
    'raw_payload'=>['voice'=>$voice,'seller'=>$seller,'enrichment'=>$enrich],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $sr = sb144c('POST','executive_command_sessions',$sessionPayload);
  $sessionId = $sr['ok'] && !empty($sr['data'][0]['id']) ? $sr['data'][0]['id'] : null;
  $insertedActions = 0;

  if($sessionId){
    $ap=[];
    foreach($actions as $a){
      $ap[]=[
        'session_id'=>$sessionId,
        'action_date'=>date('Y-m-d'),
        'action_category'=>$a['cat'],
        'action_title'=>$a['title'],
        'action_details'=>$a['details'],
        'priority_score'=>$a['score'],
        'due_at'=>date('c',strtotime('+1 day')),
        'status'=>'open',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ];
    }
    $ar=sb144c('POST','executive_command_actions',$ap);
    if($ar['ok'])$insertedActions=count($ap);
  }

  $brief=[[
    'briefing_date'=>date('Y-m-d'),
    'sessions_today'=>1,
    'open_actions'=>$insertedActions,
    'top_market'=>$topMarket,
    'top_lead'=>$topLead,
    'top_listing_opportunity'=>$topListing,
    'strategy_text'=>$strategy,
    'recommendations'=>$actions,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $br=sb144c('POST','executive_command_briefings',$brief);
  if(!$br['ok'] && str_contains($br['body'],'duplicate key')){
    sb144c('PATCH','executive_command_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$brief[0]);
  }

  echo json_encode([
    'success'=>$sr['ok'],
    'session_id'=>$sessionId,
    'actions_created'=>$insertedActions,
    'strategy'=>$strategy,
    'supabase_http'=>$sr['http'],
    'body'=>$sr['ok']?'':$sr['body']
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>