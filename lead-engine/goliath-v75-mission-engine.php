<?php
/**
 * Goliath V75 — Autonomous Executive Mission Engine
 * Gives every executive a permanent mission and turns idle time into valuable commissions.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gv75_now(){ return date('Y-m-d H:i:s'); }
function gv75_json($v){ return json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function gv75_jarr($v){ if(is_array($v)) return $v; if(is_string($v)){ $j=json_decode($v,true); return is_array($j)?$j:[]; } return []; }
function gv75_table($t){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }
function gv75_col($t,$c){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }

function gv75_exec($sql,$params=[]){ try{return gdb_exec($sql,$params);}catch(Throwable $e){ error_log('Goliath V75 SQL: '.$e->getMessage()); return false; } }
function gv75_all($sql,$params=[]){ try{return gdb_all($sql,$params) ?: [];}catch(Throwable $e){ error_log('Goliath V75 SQL all: '.$e->getMessage()); return []; } }
function gv75_one($sql,$params=[]){ try{return gdb_one($sql,$params) ?: null;}catch(Throwable $e){ error_log('Goliath V75 SQL one: '.$e->getMessage()); return null; } }

function gv75_install_schema(){
  if(!gdb_enabled()) return false;
  gv75_exec("CREATE TABLE IF NOT EXISTS executive_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_uid VARCHAR(80) NOT NULL UNIQUE,
    executive_key VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    mission_type VARCHAR(80) NOT NULL DEFAULT 'autonomous',
    mission_statement MEDIUMTEXT NULL,
    backlog_prompt MEDIUMTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    priority INT NOT NULL DEFAULT 80,
    cadence_minutes INT NOT NULL DEFAULT 60,
    max_daily_commissions INT NOT NULL DEFAULT 3,
    current_item VARCHAR(255) NULL,
    progress_total INT NOT NULL DEFAULT 0,
    progress_completed INT NOT NULL DEFAULT 0,
    last_dispatched_at DATETIME NULL,
    score_weight INT NOT NULL DEFAULT 50,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exec_status (executive_key,status),
    INDEX idx_dispatch (status,last_dispatched_at,priority)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gv75_exec("CREATE TABLE IF NOT EXISTS executive_opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opportunity_uid VARCHAR(80) NOT NULL UNIQUE,
    executive_key VARCHAR(64) NOT NULL,
    mission_id INT NULL,
    commission_id INT NULL,
    title VARCHAR(255) NOT NULL,
    summary MEDIUMTEXT NULL,
    opportunity_type VARCHAR(80) NOT NULL DEFAULT 'initiative',
    status VARCHAR(40) NOT NULL DEFAULT 'proposed',
    value_score INT NOT NULL DEFAULT 0,
    initiative_score INT NOT NULL DEFAULT 0,
    collaboration_score INT NOT NULL DEFAULT 0,
    business_value_estimate DECIMAL(12,2) NOT NULL DEFAULT 0,
    recommended_action MEDIUMTEXT NULL,
    handoff_to VARCHAR(64) NULL,
    source_url VARCHAR(500) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exec_status (executive_key,status),
    INDEX idx_score (value_score,initiative_score)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gv75_exec("CREATE TABLE IF NOT EXISTS executive_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    award_uid VARCHAR(80) NOT NULL UNIQUE,
    award_type VARCHAR(40) NOT NULL DEFAULT 'daily_mvp',
    award_date DATE NOT NULL,
    executive_key VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    reason MEDIUMTEXT NULL,
    impact_score INT NOT NULL DEFAULT 0,
    revenue_score INT NOT NULL DEFAULT 0,
    initiative_score INT NOT NULL DEFAULT 0,
    collaboration_score INT NOT NULL DEFAULT 0,
    deliverables_count INT NOT NULL DEFAULT 0,
    opportunities_count INT NOT NULL DEFAULT 0,
    trophy_active TINYINT(1) NOT NULL DEFAULT 1,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_award (award_type,award_date),
    INDEX idx_active (trophy_active,award_type),
    INDEX idx_exec (executive_key)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  return true;
}

function gv75_read_blog_style(){
  $paths=[__DIR__.'/../blog/blog-template.html', __DIR__.'/../blog/index.html'];
  $out=[];
  foreach($paths as $p){ if(is_file($p)){ $txt=file_get_contents($p); $txt=preg_replace('/<script\b[^>]*>.*?<\/script>/is','',$txt); $txt=preg_replace('/<style\b[^>]*>.*?<\/style>/is','',$txt); $txt=trim(preg_replace('/\s+/',' ',strip_tags($txt))); if($txt) $out[]=mb_substr($txt,0,1800); } }
  return $out ? implode("\n\n--- BLOG STYLE EXCERPT ---\n",$out) : 'Use MarkPires.com blog style: premium Fairfield County real estate authority, local expertise, strong hero image request, helpful structure, CTAs to Mark Pires, and SEO/AEO clarity.';
}

function gv75_default_missions(){
  $blogStyle=gv75_read_blog_style();
  return [
    'columbo'=>[
      'title'=>'Optimize Mark insPires YouTube archive forever', 'type'=>'youtube_archive_optimization','priority'=>99,'cadence'=>20,'max'=>8,'weight'=>95,
      'statement'=>'Columbo is the Chief Research & Archive Intelligence Officer. His permanent mission is to turn Mark Pires long-form YouTube/archive content into optimized, searchable, clickable, repurposable assets forever.',
      'backlog'=>"NEVER IDLE. Start with Mark insPires the World YouTube archive. Pick the next unoptimized video and create a complete YouTube optimization package. Deliver: best MrBeast-style title based on what actually happened, full SEO/AEO description, timestamps/chapters, tags, top short-form moments with timestamps, thumbnail direction for Scorsese using a big clean vivid Mark image, and handoff notes for Shakespeare/Scorsese. If no specific video is available, propose the next video intake/indexing task and tell Mark exactly what channel/video data you need."
    ],
    'scout'=>[
      'title'=>'Find seller opportunities and missing contact intelligence forever','type'=>'seller_contact_intelligence','priority'=>98,'cadence'=>20,'max'=>8,'weight'=>95,
      'statement'=>'Scout is the lead discovery and market intelligence executive. His permanent mission is to grow the seller opportunity database and improve contact intelligence every day.',
      'backlog'=>'NEVER IDLE. Work expired listings, withdrawn listings, failed sales, FSBO, probate, absentee owners, missing phone numbers, missing emails, duplicate contacts, and owner-property mismatches. Deliver a ranked list with new/changed items first, confidence, source notes, and recommended next action. If exact scraping tools are unavailable, create a concrete research plan and identify the next 25 records to verify.'
    ],
    'shakespeare'=>[
      'title'=>'Publish Fairfield County authority content forever','type'=>'blog_authority_engine','priority'=>92,'cadence'=>35,'max'=>5,'weight'=>88,
      'statement'=>'Shakespeare is the publishing and storytelling executive. His permanent mission is to make Mark Pires the clearest real estate authority in Fairfield County and surrounding CT markets.',
      'backlog'=>"NEVER IDLE. Review the current MarkPires.com blog style and create the next high-value post, guide, newsletter, landing page, caption pack, or SEO/AEO refresh. Preserve consistent site style and improve it. Every blog should include hero image direction for Scorsese, internal link ideas, backlink targets, local data sections, FAQ/AEO answers, and CTA to Mark Pires.\n\nBLOG STYLE REFERENCE:\n{$blogStyle}"
    ],
    'scorsese'=>[
      'title'=>'Turn every piece of media into finished assets forever','type'=>'media_asset_factory','priority'=>94,'cadence'=>25,'max'=>6,'weight'=>90,
      'statement'=>'Scorsese is the media director. His permanent mission is to convert raw footage, scripts, content ideas, and archive moments into high-impact videos, thumbnails, shorts, reels, and visual assets.',
      'backlog'=>'NEVER IDLE. Look for unfinished media, raw uploads, Scorsese requests, blog image requests, thumbnail requests, shorts candidates, and prompt-video ideas. Deliver the actual production plan plus ComfyUI/video prompt details, scene list, thumbnail concept, hook, captions, and what output file should be created. When a real visual asset is required, request a Scorsese ComfyUI job.'
    ],
    'jessica'=>[
      'title'=>'Human touch follow-up engine forever','type'=>'relationship_followup','priority'=>88,'cadence'=>40,'max'=>5,'weight'=>82,
      'statement'=>'Jessica is the relationship and human-touch executive. Her permanent mission is to make every lead, past client, referral partner, and opportunity feel personally followed up with.',
      'backlog'=>'NEVER IDLE. Find stale leads, due follow-ups, warm relationships, missing touches, valuation leads, after-hours calls, and referral opportunities. Deliver ready-to-review SMS/email/call scripts in Mark Pires voice. Never mention internal referral fees or lead scoring.'
    ],
    'prospector'=>[
      'title'=>'Find speaking, press, partnership, sponsor, and revenue opportunities forever','type'=>'opportunity_mining','priority'=>87,'cadence'=>45,'max'=>5,'weight'=>84,
      'statement'=>'Prospector is the opportunity discovery executive. His permanent mission is to find places where Mark Pires, Discover CT, BeatSeat, LegacySaved, House Detective, and Mark insPires can grow.',
      'backlog'=>'NEVER IDLE. Find speaking opportunities, podcasts, wineries, venues, radio, local press, business partnerships, sponsorships, lead sources, referral partners, and collaboration targets. Deliver contact target, reason, angle, pitch, and recommended next action.'
    ],
    'einstein'=>[
      'title'=>'Analyze bottlenecks and compound assets forever','type'=>'strategy_lab','priority'=>84,'cadence'=>60,'max'=>4,'weight'=>80,
      'statement'=>'Einstein is the data and strategy executive. His permanent mission is to improve systems, measure value, identify bottlenecks, and compound every completed asset.',
      'backlog'=>'NEVER IDLE. Review KPIs, completed assets, stalled queues, conversion gaps, SEO/AEO opportunities, and automation failures. Deliver a practical optimization plan with expected value, next action, and which executive should receive follow-up work.'
    ],
    'rockefeller'=>[
      'title'=>'Prioritize revenue and asset compounding forever','type'=>'revenue_priority','priority'=>83,'cadence'=>70,'max'=>3,'weight'=>78,
      'statement'=>'Rockefeller is the revenue and financial priority executive. His permanent mission is to protect focus, identify highest ROI opportunities, and turn assets into cash flow.',
      'backlog'=>'NEVER IDLE. Review funnels, packages, lead sources, referral pathways, sponsorship potential, pricing, royalties, licensing, and revenue bottlenecks. Deliver a ranked priority stack with estimated ROI and what Mark should do first.'
    ],
    'mozart'=>[
      'title'=>'Turn music and audio into leverage forever','type'=>'music_audio_assets','priority'=>78,'cadence'=>90,'max'=>3,'weight'=>72,
      'statement'=>'Mozart is the music and audio executive. His permanent mission is to turn BeatSeat, original songs, performances, voice, podcast/audio, and music credentials into assets.',
      'backlog'=>'NEVER IDLE. Find music/audio repurposing opportunities, BeatSeat demos, song clips, voiceover needs, podcast intros, YouTube audio fixes, and performance highlights. Deliver asset ideas and handoffs to Scorsese/Shakespeare.'
    ],
    'pandora'=>[
      'title'=>'Create new business possibilities forever','type'=>'innovation_lab','priority'=>77,'cadence'=>90,'max'=>3,'weight'=>74,
      'statement'=>'Pandora is the creative expansion executive. Her permanent mission is to find unexpected combinations across Mark Pires businesses and turn them into new opportunities.',
      'backlog'=>'NEVER IDLE. Combine Discover CT, real estate, LegacySaved, BeatSeat, House Detective, music, speaking, AI lead gen, and media into new products/campaigns. Deliver the most practical idea, the weird/high-upside idea, and the fastest test.'
    ],
  ];
}

function gv75_seed_missions(){
  gv75_install_schema();
  $created=0;
  foreach(gv75_default_missions() as $exec=>$m){
    $exists=gv75_one('SELECT id FROM executive_missions WHERE executive_key=? AND mission_type=? LIMIT 1',[$exec,$m['type']]);
    if($exists) continue;
    $id=gdb_insert('executive_missions',[
      'mission_uid'=>gdb_uid('mission'),
      'executive_key'=>$exec,
      'title'=>$m['title'],
      'mission_type'=>$m['type'],
      'mission_statement'=>$m['statement'],
      'backlog_prompt'=>$m['backlog'],
      'status'=>'active',
      'priority'=>$m['priority'],
      'cadence_minutes'=>$m['cadence'],
      'max_daily_commissions'=>$m['max'],
      'score_weight'=>$m['weight'],
      'metadata'=>gv75_json(['source'=>'v75_default_missions'])
    ]);
    if($id) $created++;
  }
  return $created;
}

function gv75_active_count($exec){
  if(!gv75_table('executive_commissions')) return 0;
  $r=gv75_one("SELECT COUNT(*) c FROM executive_commissions WHERE executive_key=? AND status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100",[$exec]);
  return (int)($r['c']??0);
}

function gv75_daily_mission_count($missionId){
  if(!gv75_table('executive_commissions')) return 0;
  $rows=gv75_all("SELECT metadata FROM executive_commissions WHERE DATE(created_at)=CURRENT_DATE");
  $n=0; foreach($rows as $r){ $m=gv75_jarr($r['metadata']??null); if((int)($m['mission_id']??0)===(int)$missionId) $n++; }
  return $n;
}

function gv75_recent_cooldown_ok($mission){
  $last=$mission['last_dispatched_at']??null; if(!$last) return true;
  $mins=(int)($mission['cadence_minutes']??60); if($mins<1) $mins=1;
  return (time()-strtotime($last)) >= ($mins*60);
}

function gv75_commission_prompt($mission){
  $exec=ucfirst(strtolower($mission['executive_key']));
  $date=date('Y-m-d H:i');
  return "GOLIATH V75 AUTONOMOUS EXECUTIVE MISSION\n\nExecutive: {$exec}\nMission: {$mission['title']}\nTime: {$date}\n\nCORE RULE:\nNever sit idle. If no assigned task exists, create value. If the work touches another department, hand it off clearly. Produce concrete finished work or a concrete ranked opportunity list.\n\nMission statement:\n{$mission['mission_statement']}\n\nToday's autonomous backlog instruction:\n{$mission['backlog_prompt']}\n\nRequired output format:\n1. Value created today\n2. Finished deliverable or ranked opportunities\n3. Best next action for Mark\n4. Handoffs to other executives, if any\n5. Impact estimate: revenue, authority, time saved, relationship value, or asset value\n6. What you will do next if Goliath gives no further instruction\n\nDo not say you cannot access tools unless truly blocked. If blocked, create the most useful next-step work packet and specify exactly what data/access is needed.";
}

function gv75_dispatch($limit=10){
  gv75_install_schema(); gv75_seed_missions();
  $made=[]; $skipped=[];
  if(!gv75_table('executive_commissions')) return ['created'=>[],'skipped'=>[['reason'=>'executive_commissions_missing']]];
  $missions=gv75_all("SELECT * FROM executive_missions WHERE status='active' ORDER BY priority DESC, last_dispatched_at IS NULL DESC, last_dispatched_at ASC LIMIT 50");
  foreach($missions as $mission){
    if(count($made)>=$limit) break;
    $exec=$mission['executive_key'];
    if(gv75_active_count($exec)>0){ $skipped[]=['executive'=>$exec,'mission'=>$mission['title'],'reason'=>'executive_has_active_work']; continue; }
    if(!gv75_recent_cooldown_ok($mission)){ $skipped[]=['executive'=>$exec,'mission'=>$mission['title'],'reason'=>'cadence_wait']; continue; }
    $daily=gv75_daily_mission_count((int)$mission['id']);
    if($daily >= (int)$mission['max_daily_commissions']){ $skipped[]=['executive'=>$exec,'mission'=>$mission['title'],'reason'=>'daily_cap']; continue; }
    $prompt=gv75_commission_prompt($mission);
    $cid=gdb_insert('executive_commissions',[
      'commission_uid'=>gdb_uid('com'),
      'executive_key'=>$exec,
      'title'=>'Autonomous Mission: '.$mission['title'],
      'commission_type'=>'autonomous_mission',
      'status'=>'queued',
      'priority'=>(int)$mission['priority'],
      'progress'=>0,
      'current_task'=>$mission['title'],
      'prompt'=>$prompt,
      'metadata'=>gv75_json(['mission_id'=>(int)$mission['id'],'mission_uid'=>$mission['mission_uid'],'mission_type'=>$mission['mission_type'],'source'=>'v75_autonomous_dispatcher','autonomous'=>true])
    ]);
    if($cid){
      gv75_exec('UPDATE executive_missions SET last_dispatched_at=NOW(), updated_at=NOW() WHERE id=?',[(int)$mission['id']]);
      if(gv75_table('executive_heartbeats')){
        gv75_exec("INSERT INTO executive_heartbeats (executive_key,status,phase,current_task,progress,current_step,last_heartbeat_at,updated_at)
          VALUES (:e,'queued','Autonomous mission queued',:task,5,'V75 dispatcher created proactive work',NOW(),NOW())
          ON DUPLICATE KEY UPDATE status='queued', phase='Autonomous mission queued', current_task=VALUES(current_task), progress=GREATEST(progress,5), current_step='V75 dispatcher created proactive work', last_heartbeat_at=NOW(), updated_at=NOW()",['e'=>$exec,'task'=>$mission['title']]);
      }
      $made[]=['commission_id'=>$cid,'executive'=>$exec,'mission'=>$mission['title']];
    }
  }
  return ['created'=>$made,'skipped'=>$skipped];
}

function gv75_award_daily($date=null){
  gv75_install_schema();
  $date=$date ?: date('Y-m-d');
  $existing=gv75_one('SELECT * FROM executive_awards WHERE award_type=? AND award_date=? LIMIT 1',['daily_mvp',$date]);
  if($existing) return $existing;
  $execs=['scout','jessica','scorsese','shakespeare','einstein','rockefeller','prospector','columbo','mozart','pandora'];
  $scores=[];
  foreach($execs as $e){
    $deliveries=gv75_one("SELECT COUNT(*) c FROM goliath_worker_completions WHERE LOWER(executive)=? AND DATE(created_at)=?",[$e,$date]);
    $reviews=gv75_one("SELECT COUNT(*) c FROM goliath_review_queue WHERE LOWER(executive)=? AND DATE(created_at)=?",[$e,$date]);
    $opps=gv75_table('executive_opportunities')?gv75_one("SELECT COUNT(*) c, COALESCE(SUM(value_score+initiative_score+collaboration_score),0) s FROM executive_opportunities WHERE executive_key=? AND DATE(created_at)=?",[$e,$date]):['c'=>0,'s'=>0];
    $d=(int)($deliveries['c']??0); $r=(int)($reviews['c']??0); $o=(int)($opps['c']??0); $os=(int)($opps['s']??0);
    $score=($d*30)+($r*10)+($o*18)+min(80,$os);
    $scores[$e]=['score'=>$score,'deliveries'=>$d,'reviews'=>$r,'opps'=>$o,'opp_score'=>$os];
  }
  arsort($scores);
  $winner=array_key_first($scores); $s=$scores[$winner];
  if($s['score']<=0){ return null; }
  gv75_exec("UPDATE executive_awards SET trophy_active=0 WHERE award_type='daily_mvp'");
  $id=gdb_insert('executive_awards',[
    'award_uid'=>gdb_uid('award'),
    'award_type'=>'daily_mvp',
    'award_date'=>$date,
    'executive_key'=>$winner,
    'title'=>'Executive of the Day',
    'reason'=>ucfirst($winner).' created the highest value today through '.$s['deliveries'].' completed deliverables, '.$s['reviews'].' review-ready items, and '.$s['opps'].' proactive opportunities.',
    'impact_score'=>(int)$s['score'],
    'initiative_score'=>min(100,(int)($s['opps']*20)),
    'collaboration_score'=>0,
    'deliverables_count'=>(int)$s['deliveries'],
    'opportunities_count'=>(int)$s['opps'],
    'trophy_active'=>1,
    'metadata'=>gv75_json(['scores'=>$scores,'source'=>'v75_daily_award'])
  ]);
  return gv75_one('SELECT * FROM executive_awards WHERE id=?',[$id]);
}
?>
