<?php
/**
 * Goliath Kernel v53
 * Creates mission orders without flooding duplicates. Kernel gives orders; Worker commissions execution.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-v53-lib.php';

if (!g53_key_ok()) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Forbidden: bad key']);
  exit;
}

function gk_recent_exists($agent,$jobType,$minutes=60){
  $since = gmdate('c', time() - ($minutes*60));
  $ep = 'agent_jobs?select=id,status&agent=eq.'.rawurlencode($agent).'&job_type=eq.'.rawurlencode($jobType).'&status=in.(queued,working,ai_queued,waiting)&created_at=gte.'.rawurlencode($since).'&limit=1';
  $r = g53_req('GET',$ep);
  return $r['ok'] && is_array($r['data']) && count($r['data']) > 0;
}
function gk_event($agent,$title,$detail,$meta=[],$confidence=90,$roi=0){
  return g53_req('POST','goliath_events', [[
    'department'=>$agent,
    'event_type'=>'kernel_order',
    'title'=>$title,
    'detail'=>$detail,
    'status'=>'active',
    'confidence'=>$confidence,
    'roi_estimate'=>$roi,
    'link_url'=>'/dashboard/goliath-deliverables.php?agent='.rawurlencode($agent),
    'metadata'=>$meta
  ]]);
}
function gk_orders(){
  return [
    ['agent'=>'Scout','priority'=>'critical','title'=>'Find revenue leads today','description'=>'Create real lead deliverables from approved owned data only: inbound website leads, compliant imports, homeowner intelligence, approved FSBO/expired exports, public-record imports, or source records included in payload. Return phone numbers only if present in a real source.','payload'=>['job_type'=>'lead_discovery','goal'=>25]],
    ['agent'=>'Jessica','priority'=>'critical','title'=>'Process communications queue','description'=>'Review new leads and Scout deliverables. Prepare or send Resend-powered admin notifications where configured. Create follow-up tasks and flag urgent leads for Mark.','payload'=>['job_type'=>'communications']],
    ['agent'=>'Einstein','priority'=>'high','title'=>'Score new leads','description'=>'Score new Lead Brain records and explain urgency, motivation, value, likely intent, and recommended next action.','payload'=>['job_type'=>'lead_scoring']],
    ['agent'=>'Rockefeller','priority'=>'high','title'=>'Prioritize revenue','description'=>'Rank today’s leads and opportunities by expected revenue impact. Tell Mark who to call first and why.','payload'=>['job_type'=>'roi_prioritization']],
    ['agent'=>'Columbo','priority'=>'normal','title'=>'Recover YouTube gold','description'=>'If YouTube transcript/source data is available, produce clips, titles, thumbnails, chapters, SEO/AEO upgrades, and shorts queue. If no source is available, report exact missing source.','payload'=>['job_type'=>'archive_growth']],
    ['agent'=>'Scorsese','priority'=>'normal','title'=>'Prepare video deliverables','description'=>'Turn Columbo/content inputs into video packages: captions, thumbnails, b-roll plans, publish copy, and render queue instructions.','payload'=>['job_type'=>'video_production']],
    ['agent'=>'Mozart','priority'=>'normal','title'=>'Find forgotten songs','description'=>'If audio/transcript inputs are available, locate hooks, best verses, guitar sections and propose radio-ready arrangements. If no source is available, report exact missing source.','payload'=>['job_type'=>'music_recovery']],
    ['agent'=>'Shakespeare','priority'=>'normal','title'=>'Create authority content','description'=>'Prepare publish-ready blog/email/social content tied to active leads and business priorities. Output HTML/markdown/SEO/CTA.','payload'=>['job_type'=>'content_writing']],
    ['agent'=>'Prospector','priority'=>'normal','title'=>'Mine new opportunities','description'=>'Find new niches, local partnerships, AI tools, directories, public signals, business ideas and revenue opportunities.','payload'=>['job_type'=>'opportunity_mining']],
    ['agent'=>'Pandora','priority'=>'normal','title'=>'Expand the empire','description'=>'Find business development opportunities across BeatSeat, Mark insPires speaking, LegacySaved, Discover CT, music, sponsorships, podcasts, wineries, venues, chambers, radio and partnerships.','payload'=>['job_type'=>'business_expansion']]
  ];
}
function gk_insert_order($order){
  $missionPayload = [
    'title'=>$order['title'],
    'description'=>$order['description'],
    'agent'=>$order['agent'],
    'priority'=>$order['priority'],
    'status'=>'queued',
    'source'=>'goliath_kernel_v53',
    'payload'=>$order['payload']
  ];
  $m = g53_req('POST','goliath_missions',$missionPayload);
  $mission = $m['ok'] && is_array($m['data']) && isset($m['data'][0]) ? $m['data'][0] : null;
  $jobPayload = [
    'mission_id'=>$mission['id'] ?? null,
    'agent'=>$order['agent'],
    'job_type'=>$order['payload']['job_type'] ?? 'daily_mission',
    'priority'=>$order['priority'],
    'status'=>'queued',
    'payload'=>[
      'mission'=>$order,
      'prompt'=>'Commissioned through Goliath Kernel v53. Worker will add agent-specific JSON contract.'
    ]
  ];
  $j = g53_req('POST','agent_jobs',$jobPayload);
  gk_event($order['agent'],'Order issued: '.$order['title'],$order['description'],['job_type'=>$jobPayload['job_type'],'kernel'=>'v53'],92,0);
  return ['mission'=>$m,'job'=>$j];
}
function gk_health(){
  $jobs = g53_req('GET','agent_jobs?select=id,status,agent,priority,job_type,created_at&order=created_at.desc&limit=25');
  $tasks = g53_req('GET','local_ai_tasks?select=id,status,task_type,metadata,created_at&order=created_at.desc&limit=25');
  $deliverables = g53_req('GET','goliath_deliverables?select=id,agent,deliverable_type,title,created_at&order=created_at.desc&limit=10');
  $leads = g53_req('GET','leads?select=id,name,phone,email,address,town,type,lead_score,created_at&order=created_at.desc&limit=10');
  return ['jobs'=>$jobs['data']??[],'tasks'=>$tasks['data']??[],'deliverables'=>$deliverables['data']??[],'leads'=>$leads['data']??[]];
}

$action = $_GET['action'] ?? 'run';
if($action === 'health'){
  echo json_encode(['success'=>true,'kernel'=>'v53','health'=>gk_health()], JSON_PRETTY_PRINT);
  exit;
}

$created=[]; $skipped=[];
$mode = $_GET['mode'] ?? 'normal';
$dedupeMinutes = $mode === 'force' ? 0 : (int)($_GET['dedupe_minutes'] ?? 45);
foreach(gk_orders() as $order){
  $jobType = $order['payload']['job_type'] ?? 'daily_mission';
  if($dedupeMinutes > 0 && gk_recent_exists($order['agent'],$jobType,$dedupeMinutes)){
    $skipped[] = ['agent'=>$order['agent'],'job_type'=>$jobType,'reason'=>'recent queued/working job exists'];
    continue;
  }
  $created[] = ['agent'=>$order['agent'],'job_type'=>$jobType,'result'=>gk_insert_order($order)];
}

echo json_encode([
  'success'=>true,
  'kernel'=>'Goliath Kernel v53',
  'message'=>'Orders issued with duplicate flood protection.',
  'created_count'=>count($created),
  'skipped_count'=>count($skipped),
  'created'=>$created,
  'skipped'=>$skipped,
  'health'=>gk_health()
], JSON_PRETTY_PRINT);
