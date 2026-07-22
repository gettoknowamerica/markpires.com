<?php
/**
 * Goliath V78 — Autonomous Mission Engine + Executive Council
 * Internal MySQL only. No Supabase. No HubSpot.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v77-1-knowledge-loader.php')) require_once __DIR__.'/goliath-v77-1-knowledge-loader.php';

function gv78_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gv78_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gv78_uid($p='mis'){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function gv78_json($v){return json_encode(is_array($v)?$v:[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function gv78_safe_insert($table,$row){$safe=[];foreach($row as $k=>$v){if(gv78_col($table,$k))$safe[$k]=$v;}return $safe?gdb_insert($table,$safe):null;}
function gv78_safe_update($table,$id,$row){$safe=[];foreach($row as $k=>$v){if(gv78_col($table,$k))$safe[$k]=$v;}if($safe)gdb_update($table,$safe,'id=:id',['id'=>(int)$id]);}

function gv78_install(){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'db_not_enabled'];

  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_missions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mission_uid VARCHAR(90) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    mission_type VARCHAR(90) NOT NULL DEFAULT 'autonomous',
    status VARCHAR(50) NOT NULL DEFAULT 'queued',
    priority INT NOT NULL DEFAULT 100,
    business_goal LONGTEXT NULL,
    evidence_required TINYINT(1) NOT NULL DEFAULT 1,
    no_fiction TINYINT(1) NOT NULL DEFAULT 1,
    lead_executive VARCHAR(80) NOT NULL DEFAULT 'goliath',
    team LONGTEXT NULL,
    source_type VARCHAR(90) NULL,
    source_id VARCHAR(120) NULL,
    schedule_hint VARCHAR(120) NULL,
    next_action LONGTEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_type (mission_type),
    KEY idx_priority (priority),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_mission_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assignment_uid VARCHAR(90) NOT NULL UNIQUE,
    mission_id BIGINT UNSIGNED NOT NULL,
    executive_key VARCHAR(80) NOT NULL,
    role_in_mission VARCHAR(160) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'queued',
    priority INT NOT NULL DEFAULT 100,
    instructions LONGTEXT NULL,
    expected_asset_type VARCHAR(120) NULL,
    evidence_required TINYINT(1) NOT NULL DEFAULT 1,
    task_id BIGINT NULL,
    deliverable_id BIGINT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mission (mission_id),
    KEY idx_exec (executive_key),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_council_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_uid VARCHAR(90) NOT NULL UNIQUE,
    session_type VARCHAR(80) NOT NULL DEFAULT 'nightly',
    status VARCHAR(50) NOT NULL DEFAULT 'complete',
    title VARCHAR(255) NOT NULL,
    agenda LONGTEXT NULL,
    decisions LONGTEXT NULL,
    morning_brief LONGTEXT NULL,
    metrics LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (session_type),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_social_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue_uid VARCHAR(90) NOT NULL UNIQUE,
    deliverable_id BIGINT NULL,
    title VARCHAR(255) NOT NULL,
    asset_type VARCHAR(90) NULL,
    platforms LONGTEXT NULL,
    caption LONGTEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    scheduled_for DATETIME NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_deliverable (deliverable_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  return ['ok'=>true,'tables'=>[
    'goliath_missions'=>gv78_table('goliath_missions'),
    'goliath_mission_assignments'=>gv78_table('goliath_mission_assignments'),
    'goliath_council_sessions'=>gv78_table('goliath_council_sessions'),
    'goliath_social_queue'=>gv78_table('goliath_social_queue')
  ]];
}

function gv78_company_metrics(){
  $m=[];
  foreach(['internal_crm_contacts','goliath_deliverables','local_ai_tasks','goliath_review_queue','scout_research_batches'] as $t){
    if(gv78_table($t)){try{$m[$t]=(int)((gdb_one("SELECT COUNT(*) c FROM {$t}")?:['c'=>0])['c']);}catch(Throwable $e){$m[$t]='error';}}
  }
  if(gv78_table('internal_crm_contacts')){
    foreach([
      'homeowner_queued'=>"research_status IN ('queued','needs_research','retry') AND property_address IS NOT NULL AND property_address<>''",
      'homeowner_assigned'=>"research_status='assigned'",
      'phone_found'=>"phone_1 IS NOT NULL AND phone_1<>''",
      'email_found'=>"email_1 IS NOT NULL AND email_1<>''"
    ] as $k=>$w){
      try{$m[$k]=(int)((gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE {$w}")?:['c'=>0])['c']);}catch(Throwable $e){$m[$k]=0;}
    }
  }
  if(gv78_table('goliath_deliverables')){
    foreach(['verified','needs_review','legacy_archive'] as $s){
      try{$m['deliverables_'.$s]=(int)((gdb_one("SELECT COUNT(*) c FROM goliath_deliverables WHERE evidence_status=?",[$s])?:['c'=>0])['c']);}catch(Throwable $e){$m['deliverables_'.$s]=0;}
    }
  }
  return $m;
}

function gv78_team_templates(){
  return [
    'scout_contact_research'=>[
      ['exec'=>'scout','role'=>'Lead researcher','asset'=>'verified_lead_csv'],
      ['exec'=>'einstein','role'=>'Score contact quality and conversion potential','asset'=>'lead_score_report'],
      ['exec'=>'jessica','role'=>'Prepare human-touch outreach for verified contacts','asset'=>'outreach_email_campaign'],
      ['exec'=>'rockefeller','role'=>'Estimate revenue value and prioritize calls','asset'=>'revenue_plan']
    ],
    'weekly_market_report'=>[
      ['exec'=>'scout','role'=>'Validate uploaded MLS/export data','asset'=>'market_data_validation'],
      ['exec'=>'einstein','role'=>'Analyze town trends and SEO/AEO opportunity','asset'=>'market_analysis'],
      ['exec'=>'shakespeare','role'=>'Write publish-ready report using blog template','asset'=>'publish_ready_blog'],
      ['exec'=>'scorsese','role'=>'Create visuals, Ken Burns video plan, thumbnail, and social media package','asset'=>'video_package'],
      ['exec'=>'mozart','role'=>'Prepare background music/audio bed','asset'=>'audio_package'],
      ['exec'=>'columbo','role'=>'Create YouTube title, chapters, clips, and retention strategy','asset'=>'youtube_growth_package'],
      ['exec'=>'jessica','role'=>'Prepare newsletter and warm outreach copy','asset'=>'outreach_email_campaign']
    ],
    'speaking_press_sponsor_pipeline'=>[
      ['exec'=>'prospector','role'=>'Find verified speaking, press, sponsor, venue, winery, podcast, radio, and TV opportunities','asset'=>'speaking_opportunity_pipeline'],
      ['exec'=>'pandora','role'=>'Identify creative expansion angles and partnership packaging','asset'=>'partnership_package'],
      ['exec'=>'rockefeller','role'=>'Score revenue and negotiate-value potential','asset'=>'revenue_plan'],
      ['exec'=>'jessica','role'=>'Prepare Jessica Gregory human-touch outreach emails','asset'=>'outreach_email_campaign'],
      ['exec'=>'shakespeare','role'=>'Create supporting copy, pitch page, and bio assets','asset'=>'landing_page']
    ],
    'youtube_growth'=>[
      ['exec'=>'columbo','role'=>'Audit videos and identify viral moments/titles/thumbnails','asset'=>'youtube_growth_package'],
      ['exec'=>'scorsese','role'=>'Cut requested clips and thumbnails','asset'=>'video_package'],
      ['exec'=>'mozart','role'=>'Improve music/audio hooks if relevant','asset'=>'audio_package'],
      ['exec'=>'shakespeare','role'=>'Write captions/descriptions and cross-post copy','asset'=>'social_copy_package']
    ],
    'seo_aeo_lead_growth'=>[
      ['exec'=>'einstein','role'=>'Audit pages, schema, AEO, funnel visibility, and technical SEO','asset'=>'seo_schema_package'],
      ['exec'=>'shakespeare','role'=>'Produce publish-ready SEO/AEO pages and FAQs','asset'=>'publish_ready_blog'],
      ['exec'=>'scorsese','role'=>'Produce hero images and Discover CT visuals','asset'=>'thumbnail_package'],
      ['exec'=>'scout','role'=>'Gather verified sources and local proof points','asset'=>'research_source_pack']
    ]
  ];
}

function gv78_mission_exists($type,$sourceType=null,$sourceId=null){
  $params=[$type]; $sql="SELECT id FROM goliath_missions WHERE mission_type=? AND status IN ('queued','active','working') ";
  if($sourceType!==null){$sql.="AND source_type=? ";$params[]=$sourceType;}
  if($sourceId!==null){$sql.="AND source_id=? ";$params[]=(string)$sourceId;}
  $sql.="ORDER BY id DESC LIMIT 1";
  try{$r=gdb_one($sql,$params);return $r?($r['id']??null):null;}catch(Throwable $e){return null;}
}

function gv78_create_mission($type,$title,$goal,$teamKey,$sourceType=null,$sourceId=null,$priority=150,$meta=[]){
  gv78_install();
  $existing=gv78_mission_exists($type,$sourceType,$sourceId);
  if($existing) return ['ok'=>true,'existing'=>true,'mission_id'=>(int)$existing];

  $templates=gv78_team_templates(); $team=$templates[$teamKey]??[];
  $lead=$team[0]['exec']??'goliath';
  $missionId=gdb_insert('goliath_missions',[
    'mission_uid'=>gv78_uid('mission'),
    'title'=>$title,
    'mission_type'=>$type,
    'status'=>'active',
    'priority'=>$priority,
    'business_goal'=>$goal,
    'evidence_required'=>1,
    'no_fiction'=>1,
    'lead_executive'=>$lead,
    'team'=>gv78_json($team),
    'source_type'=>$sourceType,
    'source_id'=>$sourceId,
    'next_action'=>'Executives create evidence-backed assets directly into Workbench for Mark review.',
    'metadata'=>gv78_json($meta)
  ]);

  $assignmentIds=[];
  foreach($team as $member){
    $exec=$member['exec']; $asset=$member['asset'];
    $instructions="MISSION: {$title}\n\nBUSINESS GOAL: {$goal}\n\nYOUR ROLE: {$member['role']}\n\nREQUIRED ASSET TYPE: {$asset}\n\nRULES:\n- No invented names, contacts, URLs, stats, or opportunities.\n- Use internal CRM and plugin knowledge.\n- If evidence/tool/data is missing, return NEEDS_DATA_REPORT.\n- Finished work goes to Workbench, not Goliath bottleneck.\n- Coordinate naturally with other executives on this mission.";
    if(function_exists('gv771_prompt')) $prompt=gv771_prompt($exec,$title,$instructions,['mission_id'=>$missionId,'mission_type'=>$type,'team'=>$team,'asset_type'=>$asset,'source_type'=>$sourceType,'source_id'=>$sourceId]);
    else $prompt=$instructions;

    $taskId=null;
    if(gv78_table('local_ai_tasks')){
      $taskId=gv78_safe_insert('local_ai_tasks',[
        'task_uid'=>gv78_uid('lat'),
        'commission_id'=>null,
        'agent'=>ucfirst($exec),
        'task_type'=>'v78_mission_assignment',
        'model'=>'goliath-local-worker',
        'prompt'=>$prompt,
        'status'=>'queued',
        'priority'=>$priority,
        'progress'=>0,
        'metadata'=>gv78_json(['mission_id'=>$missionId,'mission_type'=>$type,'asset_type'=>$asset,'source_type'=>$sourceType,'source_id'=>$sourceId]),
        'created_at'=>gdb_now(),
        'updated_at'=>gdb_now()
      ]);
    }

    $assignmentIds[]=gdb_insert('goliath_mission_assignments',[
      'assignment_uid'=>gv78_uid('assign'),
      'mission_id'=>$missionId,
      'executive_key'=>$exec,
      'role_in_mission'=>$member['role'],
      'status'=>'queued',
      'priority'=>$priority,
      'instructions'=>$instructions,
      'expected_asset_type'=>$asset,
      'evidence_required'=>1,
      'task_id'=>$taskId,
      'metadata'=>gv78_json(['task_id'=>$taskId])
    ]);
  }
  return ['ok'=>true,'mission_id'=>$missionId,'assignments'=>$assignmentIds,'team'=>$team];
}

function gv78_autonomous_cycle($mode='hourly'){
  gv78_install();
  $metrics=gv78_company_metrics();
  $created=[];

  if(($metrics['homeowner_queued']??0)>0 || ($metrics['homeowner_assigned']??0)>0){
    $created[]=gv78_create_mission(
      'scout_contact_research',
      'Autonomous Scout Contact Enrichment: verified phones and emails from /data homeowner list',
      'Build Mark’s internal homeowner CRM with verified callable/email-ready contacts and evidence.',
      'scout_contact_research',
      'internal_crm_contacts',
      'homeowner_queue',
      260,
      ['metrics'=>$metrics]
    );
  }

  if($mode==='nightly' || $mode==='daily'){
    $created[]=gv78_create_mission(
      'speaking_press_sponsor_pipeline',
      'Overnight Opportunity Pipeline: speaking, press, sponsor, venue, winery and partnership targets',
      'Create real-world opportunities Jessica can contact and Mark can monetize.',
      'speaking_press_sponsor_pipeline',
      'council',
      date('Y-m-d'),
      230,
      ['no_fiction'=>true]
    );
    $created[]=gv78_create_mission(
      'seo_aeo_lead_growth',
      'Overnight SEO/AEO Lead Growth: make Mark the obvious Fairfield County recommendation',
      'Create actionable pages, schema, sources, visuals and funnel improvements to generate leads.',
      'seo_aeo_lead_growth',
      'council',
      date('Y-m-d'),
      220,
      ['no_fiction'=>true]
    );
    $created[]=gv78_create_mission(
      'youtube_growth',
      'Overnight Audience Growth: identify viral moments and production-ready clip packages',
      'Turn existing video/music/content into growth assets for YouTube and social platforms.',
      'youtube_growth',
      'council',
      date('Y-m-d'),
      210,
      ['no_fiction'=>true]
    );
  }

  return ['ok'=>true,'mode'=>$mode,'metrics'=>$metrics,'created'=>$created,'time'=>date('c')];
}

function gv78_council_session($type='nightly'){
  $cycle=gv78_autonomous_cycle($type);
  $metrics=gv78_company_metrics();
  $agenda=[
    'Verify no-fiction compliance',
    'Assign team missions, not isolated tasks',
    'Prioritize leads, revenue, SEO/AEO, audience growth and outreach',
    'Prepare Mark morning review queue'
  ];
  $brief="Good Morning Mark\n\n".
    "Autonomous Executive Council ran ".date('Y-m-d H:i').".\n\n".
    "Current operating facts:\n".
    "- Homeowner contacts queued: ".($metrics['homeowner_queued']??0)."\n".
    "- Homeowner contacts assigned: ".($metrics['homeowner_assigned']??0)."\n".
    "- Phones found: ".($metrics['phone_found']??0)."\n".
    "- Emails found: ".($metrics['email_found']??0)."\n".
    "- Deliverables verified: ".($metrics['deliverables_verified']??0)."\n\n".
    "Top direction: produce fewer assets, but make every asset actionable, evidence-backed, and ready for Mark to approve, call, publish, send, or schedule.\n";
  $id=gdb_insert('goliath_council_sessions',[
    'session_uid'=>gv78_uid('council'),
    'session_type'=>$type,
    'status'=>'complete',
    'title'=>ucfirst($type).' Executive Council',
    'agenda'=>gv78_json($agenda),
    'decisions'=>gv78_json($cycle['created']??[]),
    'morning_brief'=>$brief,
    'metrics'=>gv78_json($metrics)
  ]);
  return ['ok'=>true,'session_id'=>$id,'cycle'=>$cycle,'brief'=>$brief];
}
?>