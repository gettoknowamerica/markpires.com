<?php
/**
 * V11.11 Overnight Lead Research Planner
 * Upload: /public_html/lead-engine/build-overnight-research.php
 *
 * Run:
 * /lead-engine/build-overnight-research.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb1111($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$today=date('Y-m-d');
$markets=[
  ['market'=>'Lower Fairfield County','towns'=>['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Wilton'],'audience'=>'future sellers and valuation leads'],
  ['market'=>'Westchester to CT','towns'=>['Rye','Scarsdale','White Plains','Larchmont','Mamaroneck'],'audience'=>'relocation buyers moving to Fairfield County'],
  ['market'=>'NYC / Brooklyn to CT','towns'=>['Brooklyn','Manhattan','Queens'],'audience'=>'NYC buyers looking for space, schools, commute, lifestyle'],
  ['market'=>'Builder / Developer','towns'=>['Greenwich','Westport','Darien','New Canaan','Fairfield','Wilton','Weston'],'audience'=>'land, teardown, subdivision, renovation opportunities']
];

$createdMissions=[];$createdTargets=[];$errors=[];

foreach($markets as $m){
  foreach($m['towns'] as $town){
    $mission=[[
      'mission_date'=>$today,
      'mission_type'=>'overnight_targeting',
      'market'=>$m['market'],
      'town'=>$town,
      'audience'=>$m['audience'],
      'source_focus'=>'organic search, paid ad targeting, public property data imports, Discover CT content angles',
      'research_prompt'=>'Find compliant lead angles, audience segments, search queries, offer hooks, and landing page ideas. Do not cold-call without DNC/manual review.',
      'priority'=>75,
      'status'=>'planned',
      'run_window_start'=>'22:00',
      'run_window_end'=>'08:00',
      'raw_payload'=>$m,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];
    $r=sb1111('POST','overnight_research_missions',$mission);
    if(!$r['ok']){$errors[]=['mission'=>$town,'http'=>$r['http'],'body'=>$r['body']];continue;}
    $missionId=$r['data'][0]['id']??null;
    $createdMissions[]=$town;

    $targets=[
      [
        'target_type'=>'seller',
        'search_query'=>"{$town} CT home value seller leads long time homeowners downsizing",
        'suggested_offer'=>"Free {$town} Home Value + Seller Timing Report",
        'suggested_ad_angle'=>"Thinking about selling in {$town}? See what your home could really be worth before you make a move.",
        'suggested_landing_page'=>'/home-valuation',
        'priority_score'=>88
      ],
      [
        'target_type'=>'buyer',
        'search_query'=>"moving from NYC to {$town} CT homes commute schools lifestyle",
        'suggested_offer'=>"Moving from NYC to {$town}: Buyer's Guide",
        'suggested_ad_angle'=>"Leaving NYC for more space? Discover what {$town} gives you before you buy.",
        'suggested_landing_page'=>'/buyers',
        'priority_score'=>82
      ],
      [
        'target_type'=>'relocation',
        'search_query'=>"Brooklyn Manhattan families relocating to Fairfield County {$town}",
        'suggested_offer'=>'Fairfield County Relocation Map + Town Match',
        'suggested_ad_angle'=>"Not sure which CT town fits your life after NYC? Jessica can help narrow it down.",
        'suggested_landing_page'=>'/relocation',
        'priority_score'=>80
      ],
      [
        'target_type'=>'builder',
        'search_query'=>"{$town} land teardown subdivision builder opportunity",
        'suggested_offer'=>"Private {$town} Builder Opportunity Watchlist",
        'suggested_ad_angle'=>"Builder/developer opportunities in {$town}: land, teardown, renovation signals.",
        'suggested_landing_page'=>'/builder-opportunities',
        'priority_score'=>78
      ]
    ];

    foreach($targets as $t){
      $payload=[[
        'mission_id'=>$missionId,
        'target_type'=>$t['target_type'],
        'market'=>$m['market'],
        'town'=>$town,
        'audience'=>$m['audience'],
        'search_query'=>$t['search_query'],
        'lead_source'=>'overnight_research_planner',
        'suggested_offer'=>$t['suggested_offer'],
        'suggested_ad_angle'=>$t['suggested_ad_angle'],
        'suggested_landing_page'=>$t['suggested_landing_page'],
        'priority_score'=>$t['priority_score'],
        'status'=>'research',
        'notes'=>'Use for ads, SEO/AEO pages, content, and compliant data imports. No autonomous calling without approval/DNC review.',
        'raw_payload'=>$t,
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $tr=sb1111('POST','lead_research_targets',$payload);
      if($tr['ok'])$createdTargets[]=$town.' '.$t['target_type'];
      else $errors[]=['target'=>$town.' '.$t['target_type'],'http'=>$tr['http'],'body'=>$tr['body']];
    }
  }
}

/* Create Mark action item with tonight's priorities */
sb1111('POST','mark_action_queue',[[
  'related_type'=>'overnight_research',
  'related_id'=>$today,
  'name'=>'Jessica Overnight Research',
  'source'=>'V11.11 Overnight Lead Research',
  'priority'=>'high',
  'action_type'=>'review_research_targets',
  'recommended_action'=>'Review overnight targeting plan: Lower Fairfield sellers, NYC/Brooklyn relocation buyers, Westchester movers, builder opportunities.',
  'notes'=>'This creates targeting and research tasks, not unreviewed cold calls. Approve/import data only after compliance review.',
  'status'=>'open',
  'due_at'=>date('c',strtotime('+1 day 8:30')),
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]]);

echo json_encode([
  'success'=>empty($errors),
  'missions_created'=>count($createdMissions),
  'targets_created'=>count($createdTargets),
  'markets'=>$markets,
  'errors'=>$errors
],JSON_PRETTY_PRINT);
?>