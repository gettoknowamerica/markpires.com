<?php
/**
 * Goliath V81 — Commission + Full Executive Council Link Repair
 *
 * Fixes:
 * - No more commission_id = 0 for seeded council/team work.
 * - Creates a real executive_commissions parent record.
 * - Creates 11 local_ai_tasks, one for every executive including Goliath last.
 * - Links task metadata back to one shared commission/council mission.
 * - Keeps executives confident/specialized with asset requirements and no-fiction rule.
 */

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v77-1-knowledge-loader.php')) require_once __DIR__.'/goliath-v77-1-knowledge-loader.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? ($_POST['key'] ?? '');
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'bad_key']);
  exit;
}

function v81_table($t){
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
  catch(Throwable $e){return false;}
}
function v81_col($t,$c){
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
  catch(Throwable $e){return false;}
}
function v81_uid($p='v81'){
  return function_exists('gdb_uid') ? gdb_uid($p) : $p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
}
function v81_json($v){
  return json_encode(is_array($v)?$v:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
function v81_safe_insert($table,$row){
  $safe=[];
  foreach($row as $k=>$v){ if(v81_col($table,$k)) $safe[$k]=$v; }
  if(!$safe) return null;
  return gdb_insert($table,$safe);
}
function v81_safe_update($table,$id,$row){
  $safe=[];
  foreach($row as $k=>$v){ if(v81_col($table,$k)) $safe[$k]=$v; }
  if($safe) gdb_update($table,$safe,'id=:id',['id'=>(int)$id]);
}
function v81_prompt($exec,$title,$instructions,$meta=[]){
  if(function_exists('gv771_prompt')) return gv771_prompt($exec,$title,$instructions,$meta);
  return "GOLIATH V81 EXECUTIVE COUNCIL ASSIGNMENT\n\nEXECUTIVE: {$exec}\nTITLE: {$title}\n\n{$instructions}\n\nREQUIRED OUTPUT:\nASSET_TYPE:\nEXECUTIVE:\nBUSINESS_GOAL:\nACTIONABLE_ASSET:\nEVIDENCE:\nCLICKABLE_OUTPUTS:\nQUALITY_SCORE:\nBUSINESS_IMPACT_SCORE:\nHANDOFFS:\nNEXT_ACTION:\n\nNO FICTION. CREATE ACTIONABLE ASSETS ONLY.";
}
function v81_counts(){
  $out=[];
  foreach(['executive_commissions','local_ai_tasks','goliath_missions','goliath_mission_assignments','goliath_deliverables'] as $t){
    if(v81_table($t)){
      try{
        if(v81_col($t,'status')) $out[$t]=gdb_all("SELECT status, COUNT(*) c FROM {$t} GROUP BY status");
        else $out[$t]=(int)((gdb_one("SELECT COUNT(*) c FROM {$t}")?:['c'=>0])['c']);
      }catch(Throwable $e){$out[$t]='error: '.$e->getMessage();}
    }
  }
  return $out;
}

function v81_create_commission($title,$goal,$metadata=[]){
  if(!v81_table('executive_commissions')) return null;

  // Avoid duplicate active V81 council commission for same day/title.
  try{
    $existing=gdb_one("SELECT id FROM executive_commissions WHERE title=? AND status IN ('queued','working','claimed','in_progress','processing') ORDER BY id DESC LIMIT 1",[$title]);
    if($existing && !empty($existing['id'])) return (int)$existing['id'];
  }catch(Throwable $e){}

  $row=[
    'commission_uid'=>v81_uid('commission'),
    'executive_key'=>'goliath',
    'executive'=>'Goliath',
    'title'=>$title,
    'description'=>$goal,
    'prompt'=>$goal,
    'status'=>'queued',
    'priority'=>300,
    'progress'=>0,
    'current_step'=>'Created by V81 Executive Council Commission Link Repair',
    'metadata'=>v81_json($metadata),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ];
  return v81_safe_insert('executive_commissions',$row);
}

function v81_create_task($commissionId,$exec,$taskType,$prompt,$priority,$meta=[]){
  if(!v81_table('local_ai_tasks')) return null;
  $row=[
    'task_uid'=>v81_uid('lat'),
    'commission_id'=>$commissionId ?: null,
    'agent'=>ucfirst(strtolower($exec)),
    'task_type'=>$taskType,
    'model'=>'goliath-local-worker',
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priority,
    'progress'=>0,
    'metadata'=>v81_json($meta),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ];
  return v81_safe_insert('local_ai_tasks',$row);
}

$mode=$_GET['mode'] ?? 'seed_full_council';
$title=$_GET['title'] ?? 'V81 Executive Council: One real estate content idea becomes five lead-generating AEO/listing opportunities';
$goal='Create a coordinated, evidence-backed asset package where one strong real estate content idea becomes five actionable AEO/listing-generation strategies, with videos, blogs, outreach, SEO, social, partnership, and revenue follow-up. No invented stats, contacts, phone numbers, emails, events, or opportunities. If evidence is missing, say exactly what data/tool/source is needed.';

$team=[
  'scout'=>[
    'role'=>'Verified local research and source finder',
    'asset'=>'research_source_pack',
    'instructions'=>'Find real search questions, local proof points, source links, and CRM/lead angles tied to the topic. Do not fabricate data. If MLS data is needed, request MLS upload.'
  ],
  'einstein'=>[
    'role'=>'SEO/AEO strategist and schema architect',
    'asset'=>'seo_aeo_strategy',
    'instructions'=>'Create the AEO structure: questions, schema, FAQ, page strategy, internal links, and measurable ranking/lead-generation actions.'
  ],
  'shakespeare'=>[
    'role'=>'Conversion copy and publish-ready content director',
    'asset'=>'publish_ready_content_plan',
    'instructions'=>'Turn the strategy into five publish-ready content assets: blog/page outlines, headlines, CTAs, FAQs, and conversion copy for seller leads.'
  ],
  'scorsese'=>[
    'role'=>'Video and visual production director',
    'asset'=>'video_visual_package',
    'instructions'=>'Create video/image package specs for each idea: prompt, aspect ratio, thumbnail concept, social clip angle, and required Comfy/Remotion handoff. Queue direct Comfy jobs only when appropriate.'
  ],
  'columbo'=>[
    'role'=>'YouTube and viral growth specialist',
    'asset'=>'youtube_social_growth_package',
    'instructions'=>'Create hooks, YouTube titles, Shorts/Reels/TikTok angles, thumbnail strategy, retention notes, and virality score for each idea.'
  ],
  'jessica'=>[
    'role'=>'Human-touch outreach coordinator',
    'asset'=>'outreach_followup_package',
    'instructions'=>'Create Jessica Gregory relationship-first outreach drafts connected to the content topic: seller follow-up, coffee invite, community/venue note, and newsletter CTA.'
  ],
  'prospector'=>[
    'role'=>'Real-world opportunity hunter',
    'asset'=>'verified_opportunity_pipeline',
    'instructions'=>'Find real speaking, podcast, radio, local media, sponsor, venue, winery, community, or partnership opportunities related to the content topic. Must include source URL/contact page or mark NEEDS_VERIFIED_CONTACT.'
  ],
  'rockefeller'=>[
    'role'=>'Revenue and ROI prioritization executive',
    'asset'=>'revenue_priority_plan',
    'instructions'=>'Score the five ideas by revenue potential, lead quality, time required, and next-step priority. Translate content into listing/revenue opportunities.'
  ],
  'pandora'=>[
    'role'=>'Expansion and partnership strategist',
    'asset'=>'partnership_expansion_plan',
    'instructions'=>'Identify creative partnership angles and cross-brand extensions across Discover CT, House Detective, BeatSeat, LegacySaved, speaking, and real estate.'
  ],
  'mozart'=>[
    'role'=>'Audio/music/voiceover director',
    'asset'=>'audio_voiceover_package',
    'instructions'=>'Create music, audio, voiceover, hook, intro/outro, and background-bed recommendations for the video/social/audio versions.'
  ],
  'goliath'=>[
    'role'=>'Executive Council coordinator and final morning brief',
    'asset'=>'executive_council_brief',
    'instructions'=>'Review all ten executive outputs, identify dependencies, rank the best actions for Mark, call out blockers, and prepare the final morning brief. You are coordinator, not bottleneck.'
  ]
];

$res=[
  'ok'=>true,
  'version'=>'V81 Commission + Full Executive Council Link Repair',
  'mode'=>$mode,
  'counts_before'=>v81_counts(),
  'commission_id'=>null,
  'created_tasks'=>[],
  'skipped'=>[],
  'tables'=>[
    'executive_commissions'=>v81_table('executive_commissions'),
    'local_ai_tasks'=>v81_table('local_ai_tasks')
  ]
];

$commissionId=v81_create_commission($title,$goal,[
  'version'=>'v81',
  'team'=>array_keys($team),
  'goal'=>'commission_is_parent_object_no_more_commission_zero',
  'created_by'=>'goliath-v81-commission-council-link-repair'
]);
$res['commission_id']=$commissionId;

if(!$commissionId){
  $res['ok']=false;
  $res['error']='could_not_create_commission';
  echo json_encode($res,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
}

$order=0;
foreach($team as $exec=>$cfg){
  $order++;
  // Avoid duplicate queued task for same commission/executive.
  $exists=null;
  try{
    $exists=gdb_one("SELECT id FROM local_ai_tasks WHERE commission_id=? AND agent=? AND status IN ('queued','working','running','processing') ORDER BY id DESC LIMIT 1",[$commissionId,ucfirst($exec)]);
  }catch(Throwable $e){}
  if($exists && !empty($exists['id'])){
    $res['skipped'][]=['executive'=>$exec,'reason'=>'already_has_active_task','task_id'=>(int)$exists['id']];
    continue;
  }

  $instructions="COMMISSION ID: {$commissionId}\n".
    "COMMISSION TITLE: {$title}\n\n".
    "BUSINESS GOAL:\n{$goal}\n\n".
    "YOUR EXECUTIVE ROLE:\n{$cfg['role']}\n\n".
    "REQUIRED ASSET TYPE:\n{$cfg['asset']}\n\n".
    "YOUR INSTRUCTIONS:\n{$cfg['instructions']}\n\n".
    "TEAM COLLABORATION:\nYou are part of an 11-executive council. Assume the others are also working on the same commission. Reference handoffs clearly. Do not wait for permission. Create your usable asset and name who should act next.\n\n".
    "CONFIDENCE STANDARD:\nAct like a 30-year master executive in your specialty. Be decisive, practical, and specific. Do not produce filler. Do not invent facts. If evidence/tool/data is missing, state NEEDS_VERIFIED_DATA or NEEDS_TOOL_ACCESS and exactly what is needed.\n\n".
    "COMPLETION STANDARD:\nYour deliverable must be something Mark can open, publish, send, call from, approve, revise, or hand to another executive.";

  $prompt=v81_prompt($exec,$title,$instructions,[
    'commission_id'=>$commissionId,
    'executive'=>$exec,
    'role'=>$cfg['role'],
    'expected_asset_type'=>$cfg['asset'],
    'council_order'=>$order,
    'team_size'=>11,
    'mode'=>'full_executive_council'
  ]);

  $priority = ($exec==='goliath') ? 250 : 320 - $order; // Goliath lower so he is last-ish.
  $taskId=v81_create_task($commissionId,$exec,'v81_full_council_commission',$prompt,$priority,[
    'commission_id'=>$commissionId,
    'executive'=>$exec,
    'expected_asset_type'=>$cfg['asset'],
    'council_order'=>$order,
    'team_size'=>11,
    'goliath_last'=>($exec==='goliath')
  ]);

  $res['created_tasks'][]=[
    'executive'=>$exec,
    'role'=>$cfg['role'],
    'asset'=>$cfg['asset'],
    'task_id'=>$taskId,
    'commission_id'=>$commissionId
  ];
}

$res['counts_after']=v81_counts();
$res['next']=[
  'test_pull'=>'/lead-engine/local-ai-task-pull.php?key=...',
  'run_worker'=>'powershell -ExecutionPolicy Bypass -File "F:\\GOliathOmni\\goliath-universal-executive-runtime-v80.ps1"',
  'workbench'=>'/dashboard/goliath-workbench.php',
  'missions'=>'/dashboard/goliath-missions.php'
];
$res['time']=date('c');
echo json_encode($res,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>