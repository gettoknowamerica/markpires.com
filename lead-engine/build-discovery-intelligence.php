<?php
/**
 * V12 Discovery Intelligence
 * Upload: /public_html/lead-engine/build-discovery-intelligence.php
 *
 * Night-safe research planner. Does not scrape private data or call leads.
 * It builds structured research missions, search queries, ad angles, landing-page angles,
 * and opportunity queues for morning review/import.
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb12($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$segments=[
  ['source_type'=>'sellers','market'=>'Lower Fairfield County','towns'=>['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Wilton'],'intent'=>'future seller / home value'],
  ['source_type'=>'buyers','market'=>'NYC to CT','towns'=>['Brooklyn','Manhattan','Queens','Upper West Side','Upper East Side'],'intent'=>'buyer relocation to Fairfield County'],
  ['source_type'=>'relocation','market'=>'Westchester to CT','towns'=>['Rye','Scarsdale','White Plains','Larchmont','Mamaroneck'],'intent'=>'move to Fairfield County'],
  ['source_type'=>'builders','market'=>'Fairfield County Builder Opportunities','towns'=>['Greenwich','Westport','Darien','New Canaan','Fairfield','Wilton','Weston'],'intent'=>'land teardown subdivision'],
  ['source_type'=>'developers','market'=>'Developer Acquisition','towns'=>['Greenwich','Stamford','Norwalk','Fairfield','Westport'],'intent'=>'assemblage renovation land']
];

$platforms=['Google Search','Meta Ads','YouTube','TikTok','Discover CT','Local SEO/AEO'];
$createdSources=[];$createdOpps=[];$errors=[];

foreach($segments as $seg){
  foreach($seg['towns'] as $town){
    foreach($platforms as $platform){
      $query='';
      $offer='';
      $ad='';
      $landing='/home-valuation';
      $score=70;

      if($seg['source_type']==='sellers'){
        $query="$town CT home value downsizing selling timing Fairfield County Realtor";
        $offer="Free $town Home Value + Timing Report";
        $ad="Thinking about selling in $town? Jessica can help you get a smarter local value read before you make a move.";
        $landing='/home-valuation';
        $score=90;
      } elseif($seg['source_type']==='buyers'){
        $query="moving from $town to Connecticut Fairfield County homes commute schools";
        $offer="NYC to Fairfield County Town Match Guide";
        $ad="Leaving $town for more space? Discover which Fairfield County town fits your lifestyle.";
        $landing='/relocation';
        $score=86;
      } elseif($seg['source_type']==='relocation'){
        $query="$town NY to Fairfield County CT moving guide homes commute taxes schools";
        $offer="Westchester to Connecticut Relocation Guide";
        $ad="Thinking about crossing the border into CT? Compare lifestyle, space, commute, and home options.";
        $landing='/relocation';
        $score=82;
      } elseif($seg['source_type']==='builders'){
        $query="$town CT land teardown subdivision builder opportunity off market";
        $offer="$town Builder Opportunity Watchlist";
        $ad="Land, teardown, and renovation signals in $town for builders looking for the next project.";
        $landing='/builder-opportunities';
        $score=80;
      } else {
        $query="$town CT developer opportunity assemblage renovation land multifamily";
        $offer="$town Developer Acquisition Watchlist";
        $ad="Developer and acquisition opportunities in $town: land, renovation, assemblage and repositioning signals.";
        $landing='/builder-opportunities';
        $score=78;
      }

      $src=[[
        'source_type'=>$seg['source_type'],
        'market'=>$seg['market'],
        'town'=>$town,
        'query'=>$query,
        'platform'=>$platform,
        'intent'=>$seg['intent'],
        'suggested_action'=>'Use this for compliant research, SEO/AEO page planning, ad targeting, and manual data import review.',
        'priority_score'=>$score,
        'status'=>'research',
        'raw_payload'=>['segment'=>$seg,'platform'=>$platform],
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $r=sb12('POST','discovery_intelligence_sources',$src);
      if(!$r['ok']){$errors[]=['source'=>$town.' '.$platform,'body'=>$r['body']];continue;}
      $sourceId=$r['data'][0]['id']??null;
      $createdSources[]=$town.' '.$platform;

      $opp=[[
        'source_id'=>$sourceId,
        'opportunity_type'=>$seg['source_type'],
        'market'=>$seg['market'],
        'town'=>$town,
        'audience'=>$seg['intent'],
        'offer'=>$offer,
        'landing_page'=>$landing,
        'ad_angle'=>$ad,
        'compliance_status'=>'research_only',
        'priority_score'=>$score,
        'status'=>'new',
        'notes'=>'Night research item. Review before importing any contacts or launching calls. Use for morning ad/SEO/lead-source planning.',
        'raw_payload'=>['query'=>$query,'platform'=>$platform],
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $or=sb12('POST','discovery_opportunity_queue',$opp);
      if($or['ok'])$createdOpps[]=$town.' '.$seg['source_type'];
      else $errors[]=['opp'=>$town,'body'=>$or['body']];
    }
  }
}

/* Seed cron registry */
$host=$_SERVER['HTTP_HOST']??'markpires.com';
$jobs=[
  ['Night Research','/lead-engine/build-overnight-research.php?key=YOUR_KEY','10:05 PM','daily','Research only. No calls.'],
  ['V12 Discovery Intelligence','/lead-engine/build-discovery-intelligence.php?key=YOUR_KEY','10:20 PM','daily','Builds buyer/seller/builder discovery plans.'],
  ['Master Intelligence Cron','/lead-engine/cron-master.php?key=YOUR_KEY','8:05 AM','daily','Runs daytime lead intelligence.'],
  ['Guarded Hunter Calls','/lead-engine/run-hunter-cron.php?key=YOUR_KEY','10:15 AM, 1:15 PM, 4:15 PM','daily','Calls only if hunter_enabled=true and guardrails pass.'],
  ['Appointment Automation','/lead-engine/appointment-automation.php?key=YOUR_KEY','every 30-60 min daytime','daily','Processes appointment slots and calendar bookings.']
];
foreach($jobs as $j){
  sb12('POST','cron_schedule_registry',[[
    'job_name'=>$j[0],
    'job_url'=>'https://'.$host.$j[1],
    'recommended_time'=>$j[2],
    'frequency'=>$j[3],
    'status'=>'active',
    'notes'=>$j[4],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]]);
}

sb12('POST','mark_action_queue',[[
  'related_type'=>'v12_discovery',
  'related_id'=>date('Y-m-d'),
  'name'=>'V12 Discovery Review',
  'source'=>'V12 Discovery Intelligence',
  'priority'=>'high',
  'action_type'=>'review_discovery_plan',
  'recommended_action'=>'Review V12 discovery queue: sellers, NYC/Brooklyn buyers, Westchester movers, builders, developers. Pick first ad/landing page target.',
  'notes'=>'Use this to launch first targeted ads and decide which approved data sources to import.',
  'status'=>'open',
  'due_at'=>date('c',strtotime('+1 day 8:30')),
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]]);

echo json_encode([
  'success'=>empty($errors),
  'sources_created'=>count($createdSources),
  'opportunities_created'=>count($createdOpps),
  'cron_registry_seeded'=>true,
  'message'=>'V12 Discovery Intelligence built research targets. It does not scrape private data or call anyone without approved imports/guardrails.',
  'errors'=>$errors
],JSON_PRETTY_PRINT);
?>