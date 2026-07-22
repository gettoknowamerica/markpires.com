<?php
/**
 * Goliath Omni OS v57.4
 * Seed Scout with FSBO URLs / public opportunity URLs.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/scout-revenue-pipeline-lib.php';

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$key = $data['key'] ?? $_GET['key'] ?? '';
if(!srp_key_ok($key)) srp_json(['success'=>false,'error'=>'Invalid key'],403);

$defaultUrls = [
 'https://fsbo.com/search/30-blackman-road-ridgefield-ct-06877-1778121218281',
 'https://fsbo.com/search/113-pepper-street-monroe-ct-06468-1777324880331',
 'https://fsbo.com/search/72-nichols-avenue-fairfield-ct-06825-1781099722615',
 'https://fsbo.com/search/cmp2rpa9y14kis601zgii914t',
 'https://fsbo.com/search/cmp5usv6200bys601dvdv79fw',
 'https://fsbo.com/search/cmq8fojte00bgs601rbrn9vzu',
 'https://fsbo.com/search/cmqprfzpk01o1s601962qz0yn'
];
$urls = $data['urls'] ?? null;
if(is_string($urls)) $urls = preg_split('/\s+/', trim($urls));
if(!is_array($urls) || !count($urls)) $urls = $defaultUrls;

$created=[]; $errors=[];
foreach($urls as $url){
  $url = trim((string)$url);
  if(!$url) continue;
  $hash = srp_source_hash('fsbo_url_seed','','','',$url);
  $path = parse_url($url, PHP_URL_PATH) ?: '';
  $guess = preg_replace('#^/search/#','',$path);
  $guess = str_replace('-',' ', $guess);
  $town = '';
  if(preg_match('/\b(ridgefield|monroe|fairfield|westport|greenwich|stamford|norwalk|darien|new canaan|wilton|weston|easton|trumbull|bridgeport|shelton)\b/i',$guess,$m)) $town = ucwords(strtolower($m[1]));
  $addr = ucwords(trim(preg_replace('/\bct\b.*$/i','',$guess)));
  $score = srp_score('fsbo',null,'','',$town,null);
  $base = [
    'source'=>'fsbo_seed',
    'source_url'=>$url,
    'source_hash'=>$hash,
    'opportunity_type'=>'fsbo',
    'property_address'=>$addr ?: null,
    'town'=>$town,
    'state'=>'CT',
    'lead_score'=>$score,
    'priority'=>$score>=80?'hot':'high',
    'status'=>'new',
    'next_executive'=>'Jessica',
    'scout_summary'=>'FSBO opportunity seeded from Founder-provided public URL. Scout should enrich from permitted public sources and prepare for Jessica.',
    'recommended_action'=>'Research owner/contact information, confirm status, prepare call/door-knock package.',
    'raw_payload'=>['url'=>$url],
    'updated_at'=>gmdate('c')
  ];
  $scripts = srp_scripts($base);
  $base = array_merge($base,$scripts);
  $up = srp_sb('POST','scout_opportunity_files?on_conflict=source_hash', [$base], 'resolution=merge-duplicates,return=representation');
  if(!$up['ok']){ $errors[]=['url'=>$url,'stage'=>'opportunity_upsert','response'=>$up]; continue; }
  $opp = $up['data'][0] ?? null;
  $oppId = $opp['id'] ?? null;

  $queue = srp_sb('POST','scout_research_queue', [[
    'source'=>'fsbo_seed',
    'property_address'=>$addr,
    'town'=>$town,
    'state'=>'CT',
    'status'=>'queued',
    'priority'=>$score,
    'recommended_action'=>'Scout should enrich this FSBO opportunity from permitted public sources and prepare Jessica handoff.',
    'source_url'=>$url,
    'opportunity_type'=>'fsbo',
    'opportunity_file_id'=>$oppId,
    'metadata'=>['source_url'=>$url,'founder_seed'=>true]
  ]]);
  $queueId = ($queue['ok'] && !empty($queue['data'][0]['id'])) ? $queue['data'][0]['id'] : null;

  $task = srp_sb('POST','local_ai_tasks', [[
    'task_type'=>'v55_deliverable_commission',
    'model'=>'llama3.1:8b',
    'prompt'=>"Scout, enrich this FSBO opportunity from approved public sources only. URL: {$url}\nReturn address, town, asking price if visible, any permitted public contact info, motivation score, and a Jessica-ready outreach brief.",
    'status'=>'queued',
    'priority'=>$score,
    'metadata'=>[
      'agent'=>'Scout',
      'source'=>'fsbo_url_seed',
      'version'=>'57.4',
      'next_agent'=>'Jessica',
      'commission_id'=>'SCOUT-FSBO-'.date('Ymd-His').'-'.substr($hash,0,8),
      'deliverable_type'=>'fsbo_opportunity_file',
      'opportunity_file_id'=>$oppId,
      'scout_queue_id'=>$queueId,
      'source_url'=>$url
    ]
  ]]);
  $taskId = ($task['ok'] && !empty($task['data'][0]['id'])) ? $task['data'][0]['id'] : null;

  srp_sb('POST','goliath_events', [[
    'department'=>'Scout',
    'event_type'=>'fsbo_opportunity_seeded',
    'title'=>'FSBO opportunity seeded',
    'detail'=>$url,
    'roi_estimate'=>12000,
    'confidence'=>88,
    'status'=>'queued',
    'phase'=>'fsbo_research',
    'progress'=>15,
    'link_url'=>'/dashboard/scout-intelligence.php',
    'metadata'=>['opportunity_file_id'=>$oppId,'scout_queue_id'=>$queueId,'task_id'=>$taskId,'source_url'=>$url]
  ]]);

  $created[]=['url'=>$url,'score'=>$score,'opportunity_file_id'=>$oppId,'scout_queue_id'=>$queueId,'task_id'=>$taskId];
}

srp_json(['success'=>count($errors)===0,'version'=>'57.4','created_count'=>count($created),'created'=>$created,'errors'=>$errors,'next'=>'Leave V55.2 local worker running. Scout commissions were queued.']);
?>