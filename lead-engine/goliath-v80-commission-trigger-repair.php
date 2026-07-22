<?php
/**
 * Goliath V80 — Commission / Mission Trigger Repair
 * - Repairs V78 mission assignments with no local worker task.
 * - Resets stale working tasks back to queued.
 * - Optional: seeds Real Estate AEO content multiplier mission.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v77-1-knowledge-loader.php')) require_once __DIR__.'/goliath-v77-1-knowledge-loader.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??($_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function v80t($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80c($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80uid($p='task'){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function v80json($v){return json_encode(is_array($v)?$v:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function v80safe_insert($table,$row){$safe=[];foreach($row as $k=>$v){if(v80c($table,$k))$safe[$k]=$v;}return $safe?gdb_insert($table,$safe):null;}
function v80safe_update($table,$id,$row){$safe=[];foreach($row as $k=>$v){if(v80c($table,$k))$safe[$k]=$v;}if($safe)gdb_update($table,$safe,'id=:id',['id'=>(int)$id]);}
function v80prompt($exec,$title,$instructions,$meta=[]){
  if(function_exists('gv771_prompt')) return gv771_prompt($exec,$title,$instructions,$meta);
  return "GOLIATH V80 ASSIGNMENT\n\nEXECUTIVE: {$exec}\nTITLE: {$title}\n\n{$instructions}\n\nASSET_TYPE:\nEXECUTIVE:\nBUSINESS_GOAL:\nACTIONABLE_ASSET:\nEVIDENCE:\nCLICKABLE_OUTPUTS:\nQUALITY_SCORE:\nBUSINESS_IMPACT_SCORE:\nHANDOFFS:\nNEXT_ACTION:\n\nNO FICTION.";
}
function v80create_task($exec,$taskType,$prompt,$priority=200,$meta=[],$commissionId=null){
  if(!v80t('local_ai_tasks')) return null;
  return v80safe_insert('local_ai_tasks',[
    'task_uid'=>v80uid('lat'),
    'commission_id'=>$commissionId,
    'agent'=>ucfirst(strtolower($exec)),
    'task_type'=>$taskType,
    'model'=>'goliath-local-worker',
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priority,
    'progress'=>0,
    'metadata'=>v80json($meta),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ]);
}
function v80counts(){
  $out=[];
  foreach(['local_ai_tasks','goliath_mission_assignments','goliath_missions','executive_commissions'] as $t){
    if(v80t($t)){
      try{$out[$t]=gdb_all("SELECT status, COUNT(*) c FROM {$t} GROUP BY status");}
      catch(Throwable $e){$out[$t]='error: '.$e->getMessage();}
    }
  }
  return $out;
}

$mode=$_GET['mode']??'repair';
$res=[
  'ok'=>true,
  'version'=>'V80 Commission / Mission Trigger Repair',
  'mode'=>$mode,
  'tables'=>[
    'goliath_missions'=>v80t('goliath_missions'),
    'goliath_mission_assignments'=>v80t('goliath_mission_assignments'),
    'local_ai_tasks'=>v80t('local_ai_tasks'),
    'executive_commissions'=>v80t('executive_commissions')
  ],
  'counts_before'=>v80counts(),
  'reset_stale_tasks'=>0,
  'repaired_assignments'=>[],
  'seeded'=>[]
];

/* Reset stale working tasks */
if(v80t('local_ai_tasks') && v80c('local_ai_tasks','updated_at') && v80c('local_ai_tasks','status')){
  try{
    gdb_exec("UPDATE local_ai_tasks
      SET status='queued', progress=LEAST(COALESCE(progress,0),10), updated_at=NOW()
      WHERE status IN ('working','running','processing')
        AND COALESCE(progress,0) < 100
        AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $res['reset_stale_tasks']=(int)((gdb_one("SELECT ROW_COUNT() c")?:['c'=>0])['c']);
  }catch(Throwable $e){$res['reset_error']=$e->getMessage();}
}

/* Repair assignments with no task_id */
if(v80t('goliath_mission_assignments') && v80t('goliath_missions')){
  try{
    $assignments=gdb_all("SELECT a.*, m.title mission_title, m.business_goal, m.mission_type, m.source_type, m.source_id
      FROM goliath_mission_assignments a
      JOIN goliath_missions m ON m.id=a.mission_id
      WHERE (a.task_id IS NULL OR a.task_id=0)
        AND a.status IN ('queued','active','working','assigned')
      ORDER BY a.priority DESC, a.id ASC
      LIMIT 75");
  }catch(Throwable $e){$assignments=[];$res['assignment_query_error']=$e->getMessage();}
  foreach($assignments as $a){
    $exec=strtolower($a['executive_key']??'goliath');
    $title=$a['mission_title']??('Mission Assignment #'.$a['id']);
    $asset=$a['expected_asset_type']??'business_asset';
    $body="MISSION: {$title}\n\n".
      "MISSION TYPE: ".($a['mission_type']??'mission')."\n".
      "BUSINESS GOAL: ".($a['business_goal']??'Produce an actionable business asset.')."\n\n".
      "YOUR ROLE: ".($a['role_in_mission']??'Create your required asset.')."\n\n".
      "EXPECTED ASSET TYPE: {$asset}\n\n".
      "INSTRUCTIONS:\n".($a['instructions']??'')."\n\n".
      "COLLABORATION RULE: Work with the assigned mission team naturally. Do not wait for Goliath approval. Send finished work to Workbench/Mark Review.\n\n".
      "NO-FICTION RULE: Never invent contacts, opportunities, emails, phone numbers, URLs, stats, prior press, or MLS data.";
    $prompt=v80prompt($exec,$title,$body,[
      'mission_id'=>(int)$a['mission_id'],
      'assignment_id'=>(int)$a['id'],
      'mission_type'=>$a['mission_type']??'mission',
      'expected_asset_type'=>$asset,
      'repair'=>'v80_commission_trigger_repair'
    ]);
    $taskId=v80create_task($exec,'v80_mission_assignment',$prompt,(int)($a['priority']??200),[
      'mission_id'=>(int)$a['mission_id'],
      'assignment_id'=>(int)$a['id'],
      'expected_asset_type'=>$asset,
      'repair'=>'v80_commission_trigger_repair'
    ]);
    if($taskId){
      v80safe_update('goliath_mission_assignments',(int)$a['id'],['task_id'=>$taskId,'status'=>'queued','updated_at'=>gdb_now()]);
    }
    $res['repaired_assignments'][]=[
      'assignment_id'=>(int)$a['id'],
      'mission_id'=>(int)$a['mission_id'],
      'executive'=>$exec,
      'asset'=>$asset,
      'task_id'=>$taskId
    ];
  }
}

/* Optional seed: one content idea -> five AEO/listing-generation assets */
if($mode==='seed_content_multiplier' || isset($_GET['seed'])){
  $missionTitle='V80 Priority: Turn one content idea into five lead-generating Real Estate AEO assets';
  $goal='Create five evidence-backed Real Estate AEO/listing-generation assets from one strong content idea.';
  $team=[
    ['exec'=>'einstein','asset'=>'seo_aeo_strategy','role'=>'Find AEO questions, search intent, schema opportunities, and page strategy.'],
    ['exec'=>'shakespeare','asset'=>'publish_ready_content_plan','role'=>'Turn the idea into blogs, FAQs, landing pages, and answer-engine copy.'],
    ['exec'=>'scorsese','asset'=>'video_visual_package','role'=>'Create video/image package requests for each idea.'],
    ['exec'=>'columbo','asset'=>'youtube_social_growth_package','role'=>'Create titles, hooks, thumbnails, retention strategy, and social repurposing plan.'],
    ['exec'=>'jessica','asset'=>'outreach_followup_package','role'=>'Create human-touch follow-up messages for leads generated from the topic.']
  ];
  $missionId=null;
  if(v80t('goliath_missions')){
    try{
      $existing=gdb_one("SELECT id FROM goliath_missions WHERE title=? AND status IN ('queued','active','working') LIMIT 1",[$missionTitle]);
      if($existing) $missionId=(int)$existing['id'];
      else $missionId=v80safe_insert('goliath_missions',[
        'mission_uid'=>v80uid('mission'),
        'title'=>$missionTitle,
        'mission_type'=>'content_multiplier_real_estate_aeo',
        'status'=>'active',
        'priority'=>275,
        'business_goal'=>$goal,
        'evidence_required'=>1,
        'no_fiction'=>1,
        'lead_executive'=>'einstein',
        'team'=>v80json($team),
        'source_type'=>'strategic_priority',
        'source_id'=>date('Y-m-d'),
        'next_action'=>'Executives create five actionable, evidence-backed assets in Workbench.',
        'metadata'=>v80json(['seed'=>'v80_content_multiplier'])
      ]);
    }catch(Throwable $e){$res['seed_mission_error']=$e->getMessage();}
  }
  foreach($team as $member){
    $exec=$member['exec'];
    $instructions="MISSION: {$missionTitle}\n\nBUSINESS GOAL: {$goal}\n\nYOUR ROLE: {$member['role']}\n\nEXPECTED ASSET: {$member['asset']}\n\nCreate exactly five specific, actionable ideas/assets/strategies. Each must include target AEO question, asset to create, audience, evidence/source needed, CTA, handoff, and next action. No invented market stats.";
    $prompt=v80prompt($exec,$missionTitle,$instructions,['mission_id'=>$missionId,'mission_type'=>'content_multiplier_real_estate_aeo','expected_asset_type'=>$member['asset']]);
    $taskId=v80create_task($exec,'v80_content_multiplier_real_estate_aeo',$prompt,275,['mission_id'=>$missionId,'expected_asset_type'=>$member['asset'],'seed'=>'v80_content_multiplier']);
    $res['seeded'][]=['executive'=>$exec,'asset'=>$member['asset'],'task_id'=>$taskId];
  }
}

$res['counts_after']=v80counts();
$res['next']=[
  'test_pull'=>'/lead-engine/local-ai-task-pull.php?key=...',
  'run_local_worker'=>'powershell -ExecutionPolicy Bypass -File "F:\\GOliathOmni\\goliath-universal-executive-runtime-v80.ps1"',
  'missions'=>'/dashboard/goliath-missions.php',
  'workbench'=>'/dashboard/goliath-workbench.php'
];
$res['time']=date('c');
echo json_encode($res,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>