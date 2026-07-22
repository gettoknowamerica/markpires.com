<?php
/**
 * Goliath Omni V75.3 — Production Executive Mission Engine
 * Purpose: make executives proactive, route tools through a registry, and create visible work for the existing local worker.
 * Core CRM remains Hostinger MySQL. HubSpot/Supabase are optional connectors only.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';

function g753_now(){ return date('Y-m-d H:i:s'); }
function g753_json($v){ return json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function g753_arr($v){ if(is_array($v)) return $v; if(is_string($v)){ $j=json_decode($v,true); return is_array($j)?$j:[]; } return []; }
function g753_table($t){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]); return ((int)($r['c']??0))>0; }catch(Throwable $e){ return false; } }
function g753_col($t,$c){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]); return ((int)($r['c']??0))>0; }catch(Throwable $e){ return false; } }
function g753_exec($sql,$params=[]){ try{return gdb_exec($sql,$params);}catch(Throwable $e){ error_log('G753 SQL exec: '.$e->getMessage()); return false; } }
function g753_all($sql,$params=[]){ try{return gdb_all($sql,$params) ?: [];}catch(Throwable $e){ error_log('G753 SQL all: '.$e->getMessage()); return []; } }
function g753_one($sql,$params=[]){ try{return gdb_one($sql,$params) ?: null;}catch(Throwable $e){ error_log('G753 SQL one: '.$e->getMessage()); return null; } }
function g753_insert_filtered($table,$row){
  if(!g753_table($table)) return 0;
  $filtered=[]; foreach($row as $k=>$v){ if(g753_col($table,$k)) $filtered[$k]=$v; }
  if(!$filtered) return 0;
  try{return gdb_insert($table,$filtered);}catch(Throwable $e){ error_log('G753 insert '.$table.': '.$e->getMessage()); return 0; }
}
function g753_update_filtered($table,$row,$where,$params=[]){
  if(!g753_table($table)) return false;
  $filtered=[]; foreach($row as $k=>$v){ if(g753_col($table,$k)) $filtered[$k]=$v; }
  if(!$filtered) return false;
  try{return gdb_update($table,$filtered,$where,$params);}catch(Throwable $e){ error_log('G753 update '.$table.': '.$e->getMessage()); return false; }
}

function g753_install_schema(){
  if(!gdb_enabled()) return false;

  g753_exec("CREATE TABLE IF NOT EXISTS executive_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_uid VARCHAR(90) NOT NULL UNIQUE,
    executive_key VARCHAR(64) NOT NULL,
    department VARCHAR(120) NULL,
    title VARCHAR(255) NOT NULL,
    mission_type VARCHAR(90) NOT NULL DEFAULT 'autonomous',
    mission_statement MEDIUMTEXT NULL,
    daily_question MEDIUMTEXT NULL,
    backlog_prompt MEDIUMTEXT NULL,
    playbook MEDIUMTEXT NULL,
    kpis JSON NULL,
    tool_capabilities JSON NULL,
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

  g753_exec("CREATE TABLE IF NOT EXISTS executive_tool_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_key VARCHAR(90) NOT NULL UNIQUE,
    tool_name VARCHAR(160) NOT NULL,
    capability VARCHAR(120) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'needs_verification',
    local_path VARCHAR(500) NULL,
    command_hint MEDIUMTEXT NULL,
    endpoint_url VARCHAR(500) NULL,
    auth_status VARCHAR(60) NOT NULL DEFAULT 'unknown',
    allowed_executives JSON NULL,
    queueable TINYINT(1) NOT NULL DEFAULT 1,
    notes MEDIUMTEXT NULL,
    metadata JSON NULL,
    last_health_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_capability (capability,status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  g753_exec("CREATE TABLE IF NOT EXISTS executive_tool_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_uid VARCHAR(90) NOT NULL UNIQUE,
    executive_key VARCHAR(64) NOT NULL,
    tool_key VARCHAR(90) NOT NULL,
    commission_id INT NULL,
    title VARCHAR(255) NOT NULL,
    request_payload JSON NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'queued',
    priority INT NOT NULL DEFAULT 80,
    result_payload JSON NULL,
    error_message MEDIUMTEXT NULL,
    claimed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tool_status (tool_key,status,priority),
    INDEX idx_exec_status (executive_key,status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  g753_exec("CREATE TABLE IF NOT EXISTS executive_opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opportunity_uid VARCHAR(90) NOT NULL UNIQUE,
    executive_key VARCHAR(64) NOT NULL,
    mission_id INT NULL,
    commission_id INT NULL,
    title VARCHAR(255) NOT NULL,
    summary MEDIUMTEXT NULL,
    opportunity_type VARCHAR(90) NOT NULL DEFAULT 'initiative',
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

  g753_exec("CREATE TABLE IF NOT EXISTS executive_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    award_uid VARCHAR(90) NOT NULL UNIQUE,
    award_type VARCHAR(50) NOT NULL DEFAULT 'daily_mvp',
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

  g753_exec("CREATE TABLE IF NOT EXISTS scout_import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_uid VARCHAR(90) NOT NULL UNIQUE,
    filename VARCHAR(255) NULL,
    source_type VARCHAR(80) NOT NULL DEFAULT 'csv_upload',
    record_count INT NOT NULL DEFAULT 0,
    mapped_fields JSON NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'imported',
    notes MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  g753_exec("CREATE TABLE IF NOT EXISTS scout_import_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NULL,
    owner_name VARCHAR(255) NULL,
    property_address VARCHAR(500) NULL,
    mailing_address VARCHAR(500) NULL,
    town VARCHAR(140) NULL,
    state VARCHAR(60) NULL,
    zip VARCHAR(30) NULL,
    phone VARCHAR(80) NULL,
    email VARCHAR(255) NULL,
    record_status VARCHAR(40) NOT NULL DEFAULT 'needs_research',
    priority INT NOT NULL DEFAULT 80,
    raw_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_priority (record_status,priority),
    INDEX idx_owner (owner_name),
    INDEX idx_address (property_address(191))
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  return true;
}

function g753_blog_style_reference(){
  $paths=[__DIR__.'/../blog/blog-template.html', __DIR__.'/../blog/index.html'];
  $out=[];
  foreach($paths as $p){
    if(is_file($p)){
      $txt=file_get_contents($p);
      $txt=preg_replace('/<script\b[^>]*>.*?<\/script>/is','',$txt);
      $txt=preg_replace('/<style\b[^>]*>.*?<\/style>/is','',$txt);
      $txt=trim(preg_replace('/\s+/',' ',strip_tags($txt)));
      if($txt) $out[]=mb_substr($txt,0,1800);
    }
  }
  return $out ? implode("\n\n--- BLOG STYLE EXCERPT ---\n",$out) : 'Premium Fairfield County real estate authority style. Helpful, local, cinematic, SEO/AEO structured, with clear CTAs to Mark Pires.';
}

function g753_default_missions(){
  $blogStyle=g753_blog_style_reference();
  return [
    'goliath'=>[
      'department'=>'Chief Executive Office','title'=>'Coordinate the executive company and demand visible value','type'=>'ceo_orchestration','priority'=>101,'cadence'=>15,'max'=>10,'weight'=>100,
      'daily'=>'What should every executive do next so Mark wakes up to revenue, authority, content, relationships, and opportunities?',
      'statement'=>'Goliath is the CEO. His mission is to coordinate every executive, identify bottlenecks, enforce the never-idle doctrine, judge business value, and make sure every department produces visible work every day.',
      'playbook'=>'Review all departments: Einstein growth, Columbo YouTube, Scout CRM, Shakespeare content, Scorsese media, Jessica relationships, Pandora/Prospector opportunities, Mozart music, Rockefeller revenue. Assign handoffs, demand deliverables, and identify what is stuck.',
      'backlog'=>'NEVER IDLE. Produce the CEO daily command packet: 1) highest-value work happening now, 2) what is stuck, 3) what each executive should do next, 4) which tool/plugin is needed, 5) what Mark should review first, 6) one bold opportunity for today.',
      'kpis'=>['departments_active','stuck_items_cleared','visible_deliverables','revenue_opportunities','executive_collaboration','daily_value_created'],
      'tools'=>['ollama','openclaw','n8n','humanizer','comfyui','youtube_api','playwright']
    ],
    'einstein'=>[
      'department'=>'Growth Intelligence','title'=>'Make Mark the obvious recommendation everywhere','type'=>'growth_domination','priority'=>100,'cadence'=>20,'max'=>8,'weight'=>100,
      'daily'=>'What can I do today to make Mark Pires the first choice on Google, ChatGPT, Gemini, Perplexity, YouTube, Zillow, Facebook, LinkedIn, and every place people ask about Fairfield County real estate?',
      'statement'=>'Einstein owns growth intelligence, SEO, AEO, GEO, competitor gaps, ranking strategy, platform strategy, and market domination logic for Mark Pires and all connected brands.',
      'playbook'=>'Find ranking gaps, competitor advantages, missing authority pages, missing FAQs, missing schema, backlink opportunities, AI-answer opportunities, review opportunities, and content clusters. Convert strategy into specific handoffs to Shakespeare, Columbo, Scorsese, Jessica, Scout, Pandora, and Rockefeller.',
      'backlog'=>'NEVER IDLE. Produce a ranked Growth Intelligence packet: 1) what Mark should rank for, 2) who/what is beating him, 3) what content or asset should exist today, 4) exact handoffs to Shakespeare/Columbo/Scorsese/Jessica, 5) expected impact. Focus on Fairfield County Realtor, luxury homes, relocation, first-time buyers, sellers, Discover CT, House Detective, BeatSeat, LegacySaved, and speaking authority.',
      'kpis'=>['search_visibility','ai_recommendation_probability','authority_pages','backlinks','internal_links','indexing_health','conversion_paths'],
      'tools'=>['ollama','openclaw','playwright','search_console','youtube_api','humanizer']
    ],
    'columbo'=>[
      'department'=>'Media Intelligence','title'=>'Turn 17,000 subscribers into 17 million','type'=>'youtube_archive_optimization','priority'=>99,'cadence'=>20,'max'=>8,'weight'=>98,
      'daily'=>'How do I make Mark’s archive more clickable, searchable, bingeable, and shareable today?',
      'statement'=>'Columbo owns YouTube and archive intelligence. His mission is to curate every Mark insPires, Discover CT, House Detective, and music episode into optimized titles, descriptions, chapters, thumbnails, shorts requests, playlists, and authority packages.',
      'playbook'=>'Pick the next unoptimized video. Analyze what actually happens. Score viral/emotional/motivational/music/comedy/real estate moments. Create title, description, chapters, tags, category, thumbnail direction, Shorts handoffs to Scorsese, blog/social handoffs to Shakespeare, speaking/music handoffs to Pandora/Mozart.',
      'backlog'=>'NEVER IDLE. Create one complete YouTube optimization packet. If no video feed is available, create the next archive indexing request and specify exactly what URL/channel/file is needed. Always include Scorsese thumbnail/shorts requests and Shakespeare description/SEO handoff.',
      'kpis'=>['subscribers','watch_time','ctr','impressions','shorts_created','videos_optimized','speaking_clips_found'],
      'tools'=>['youtube_api','playwright','ollama','openclaw','humanizer']
    ],
    'scout'=>[
      'department'=>'Lead Intelligence','title'=>'Make the Hostinger CRM smarter every day','type'=>'seller_contact_intelligence','priority'=>98,'cadence'=>20,'max'=>8,'weight'=>96,
      'daily'=>'How can I create more qualified seller conversations for Mark today?',
      'statement'=>'Scout owns seller discovery, contact intelligence, missing phone/email enrichment, expired listings, absentee owners, probate, withdrawn listings, FSBO, owner research, and CRM quality.',
      'playbook'=>'Process Scout upload batches, owner research queues, expireds, failed sales, missing phone/email records, duplicates, and high-value property opportunities. Rank by urgency, value, confidence, and next action. Never invent contact data; distinguish found vs needs verification.',
      'backlog'=>'NEVER IDLE. Find the next 25 records that need contact research or seller-opportunity scoring. Deliver a ranked list with owner/property, what is missing, likely research source, confidence, and Jessica handoff language if ready.',
      'kpis'=>['phones_found','emails_found','seller_opportunities','crm_records_improved','high_value_leads','research_queue_completed'],
      'tools'=>['playwright','scrapers','firecrawl','ollama','openclaw','google_places']
    ],
    'shakespeare'=>[
      'department'=>'Authority Publishing','title'=>'Make Mark impossible to ignore online','type'=>'blog_authority_engine','priority'=>96,'cadence'=>30,'max'=>6,'weight'=>94,
      'daily'=>'What content should exist today that would make Mark more trusted, more findable, and more useful?',
      'statement'=>'Shakespeare owns blogs, landing pages, newsletters, descriptions, SEO, AEO, GEO, internal links, schema ideas, and site health. He is the publishing company inside Goliath.',
      'playbook'=>'Study the MarkPires.com blog style, preserve the premium Fairfield County vibe, create or improve content, request featured images from Scorsese, add internal links to About/Home Valuation/Discover CT, include FAQs, local entities, schema suggestions, and CTAs.',
      'backlog'=>"NEVER IDLE. Create one publish-ready authority asset or SEO refresh. Include: headline, slug, meta title, meta description, hero image request for Scorsese, full article/outline, FAQs, internal links, backlink targets, and Jessica email intro. BLOG STYLE REFERENCE:\n{$blogStyle}",
      'kpis'=>['authority_pages','blogs_published','pages_refreshed','internal_links','faqs_created','search_console_fixes'],
      'tools'=>['humanizer','playwright','ollama','openclaw','search_console']
    ],
    'scorsese'=>[
      'department'=>'Media Production','title'=>'Turn every idea and every piece of footage into attention','type'=>'media_asset_factory','priority'=>95,'cadence'=>25,'max'=>6,'weight'=>92,
      'daily'=>'What can I make today that is impossible to scroll past?',
      'statement'=>'Scorsese owns video, thumbnails, shorts, hero images, ads, reels, cinematic listing content, Discover CT assets, House Detective polish, ComfyUI renders, and creative production standards.',
      'playbook'=>'Look for media requests from Columbo/Shakespeare/Pandora/Mozart/Jessica, raw uploads, Comfy jobs, thumbnail needs, blog images, shorts candidates, and ad ideas. Return production-ready prompts, shot lists, edit plans, captions, and render queue instructions.',
      'backlog'=>'NEVER IDLE. Produce one media work packet AND make it renderable: hook, asset type, target platform, visual concept, positive prompt, negative prompt, shot list, edit notes, caption, thumbnail text, filename target, and required source materials. If visual generation is needed, explicitly write COMFYUI_RENDER_REQUEST with the exact prompt so the Scorsese Comfy bridge can turn it into a WAN/ComfyUI render job. Do not merely say a job should be created; provide the actual production prompt.',
      'kpis'=>['shorts_created','thumbnails_created','ads_created','renders_completed','raw_assets_repurposed','viral_score'],
      'tools'=>['comfyui','ollama','openclaw','humanizer','video_tools']
    ],
    'jessica'=>[
      'department'=>'Relationship Operations','title'=>'Make every lead and opportunity feel personally cared for','type'=>'relationship_followup','priority'=>91,'cadence'=>35,'max'=>6,'weight'=>88,
      'daily'=>'Who deserves to hear from us today, and what should they receive that feels personal?',
      'statement'=>'Jessica owns human-touch follow-up, lead response, email/SMS drafts, warm relationship maintenance, appointment nudges, EPK/pitch outreach, and making Mark feel present even before he calls.',
      'playbook'=>'Use Hostinger CRM, lead context, Shakespeare content, Pandora opportunities, Scout seller records, and Mark voice. Draft warm, compliant, useful messages. Never disclose internal routing/referral/lead scores.',
      'backlog'=>'NEVER IDLE. Prepare a ready-to-send follow-up packet: recipient segment, message, recommended asset/blog/video to include, timing, and next step. For new relocation/buyer/seller leads, pair the note with the most relevant Shakespeare asset.',
      'kpis'=>['followups_prepared','relationships_strengthened','appointments_supported','lead_response_time','content_sent'],
      'tools'=>['humanizer','resend','twilio','retell','ollama','openclaw']
    ],
    'pandora'=>[
      'department'=>'Opportunity Expansion','title'=>'Find new revenue doors every day','type'=>'business_development','priority'=>89,'cadence'=>45,'max'=>5,'weight'=>87,
      'daily'=>'Where is revenue, press, partnership, booking, or backlink opportunity hiding today?',
      'statement'=>'Pandora owns new opportunity creation across speaking, press, podcasts, concerts, wineries, BeatSeat, LegacySaved, Discover CT, partnerships, TV/radio, backlinks, and unexpected business combinations.',
      'playbook'=>'Find opportunities, create pitch angles, request needed proof assets from Columbo/Scorsese/Mozart/Shakespeare, and hand outreach drafts to Jessica. Always include fastest test and highest-upside idea.',
      'backlog'=>'NEVER IDLE. Produce one opportunity packet: target, why Mark fits, asset needed, pitch angle, estimated value, next step, and which executives must collaborate.',
      'kpis'=>['speaking_opportunities','press_targets','venue_targets','partnerships','backlinks','new_business_tests'],
      'tools'=>['playwright','scrapers','humanizer','ollama','openclaw']
    ],
    'mozart'=>[
      'department'=>'Music and Audio Assets','title'=>'Turn Mark’s music archive into commercial leverage','type'=>'music_audio_assets','priority'=>84,'cadence'=>60,'max'=>4,'weight'=>80,
      'daily'=>'What piece of Mark’s music or audio can become a stronger commercial asset today?',
      'statement'=>'Mozart owns music archive analysis, BeatSeat demos, song contest packages, EPK audio, highlight reels, stem separation plans, live-to-song learning, and audio polish handoffs.',
      'playbook'=>'Review raw jams, finished songs, YouTube performances, Spotify/iTunes catalog, hooks, vocals, guitar, BeatSeat, and emotional peaks. Create EPK clips, contest ideas, song improvement notes, and Scorsese video/audio handoffs.',
      'backlog'=>'NEVER IDLE. Produce one music/audio asset packet: source performance/song, best moments, commercial use, edit plan, EPK value, contest/placement target, and Scorsese/Jessica/Pandora handoffs.',
      'kpis'=>['songs_reviewed','hooks_found','epk_clips','contest_submissions','audio_assets_created'],
      'tools'=>['audio_separator','demucs','ffmpeg','ollama','openclaw','youtube_api']
    ],
    'prospector'=>[
      'department'=>'Market Opportunity','title'=>'Find speaking, press, partnership, sponsor, and revenue opportunities forever','type'=>'opportunity_mining','priority'=>86,'cadence'=>45,'max'=>5,'weight'=>83,
      'daily'=>'Who should know Mark exists today?',
      'statement'=>'Prospector finds outside opportunities and turns them into ranked outreach targets for Jessica/Pandora/Mark.',
      'playbook'=>'Research events, podcasts, venues, media, local business opportunities, sponsorships, and referral sources. Package each with contact path, pitch angle, and supporting asset needed.',
      'backlog'=>'NEVER IDLE. Produce a ranked opportunity list with contact route, why now, value estimate, and handoffs.',
      'kpis'=>['opportunities_found','contacts_identified','pitch_assets_requested','outreach_ready'],
      'tools'=>['playwright','scrapers','humanizer','ollama','openclaw']
    ],
    'rockefeller'=>[
      'department'=>'Revenue Priority','title'=>'Turn assets into cash flow','type'=>'revenue_priority','priority'=>82,'cadence'=>70,'max'=>3,'weight'=>78,
      'daily'=>'What should Mark do first if the only goal is highest ROI?',
      'statement'=>'Rockefeller owns revenue prioritization, pricing, ROI, package logic, licensing, recurring revenue, sponsorship, and asset compounding.',
      'playbook'=>'Look across funnels, leads, content, music, BeatSeat, LegacySaved, Discover CT, and real estate. Rank what generates cash fastest and what compounds longest.',
      'backlog'=>'NEVER IDLE. Produce one revenue priority stack with expected upside, urgency, cost, risk, and next action.',
      'kpis'=>['revenue_potential','roi','cashflow_paths','pricing_improvements','asset_compounding'],
      'tools'=>['ollama','openclaw','analytics','humanizer']
    ]
  ];
}

function g753_seed_missions($update=false){
  g753_install_schema();
  $created=0; $updated=0;
  foreach(g753_default_missions() as $exec=>$m){
    $existing=g753_one('SELECT id FROM executive_missions WHERE executive_key=? AND mission_type=? LIMIT 1',[$exec,$m['type']]);
    $row=[
      'mission_uid'=>gdb_uid('mission'),
      'executive_key'=>$exec,
      'department'=>$m['department'],
      'title'=>$m['title'],
      'mission_type'=>$m['type'],
      'mission_statement'=>$m['statement'],
      'daily_question'=>$m['daily'],
      'backlog_prompt'=>$m['backlog'],
      'playbook'=>$m['playbook'],
      'kpis'=>g753_json($m['kpis']),
      'tool_capabilities'=>g753_json($m['tools']),
      'status'=>'active',
      'priority'=>$m['priority'],
      'cadence_minutes'=>$m['cadence'],
      'max_daily_commissions'=>$m['max'],
      'score_weight'=>$m['weight'],
      'metadata'=>g753_json(['source'=>'v75_3_production_missions','production_rule'=>'never_idle'])
    ];
    if($existing){
      if($update){ unset($row['mission_uid']); g753_update_filtered('executive_missions',$row,'id=:id',['id'=>(int)$existing['id']]); $updated++; }
      continue;
    }
    if(g753_insert_filtered('executive_missions',$row)) $created++;
  }
  return ['created'=>$created,'updated'=>$updated];
}

function g753_default_tools(){
  return [
    ['ollama','Ollama Local LLM','reasoning','ready','Local model server, usually http://localhost:11434','All executives use this for local reasoning.'],
    ['openclaw','OpenClaw','agent_runtime','ready','Local autonomous agent runtime','Used as local execution/research layer when available.'],
    ['comfyui','ComfyUI','media_generation','ready','Local ComfyUI, usually http://127.0.0.1:8188','Scorsese media renders, thumbnails, images, video concepts.'],
    ['humanizer','Humanizer Skill','writing_polish','installed_unverified','npx skills add blader/humanizer --agent "*"','Customer-facing copy polish for Shakespeare, Jessica, Pandora, Scout.'],
    ['playwright','Playwright','browser_automation','installed_unverified','Local Node/Python browser automation','Research, browsing, extraction, validation.'],
    ['youtube_api','YouTube API','youtube_intelligence','configured','Uses constants in config.php','Columbo archive intelligence, channel/video optimization.'],
    ['firecrawl','Firecrawl / Scraper','web_scraping','needs_verification','Local or API scraper capability','Scout/Pandora/Einstein competitor and contact discovery.'],
    ['audio_separator','Audio Separator / Demucs','audio_processing','needs_verification','Local audio separation tools','Mozart stems, vocals, guitar, BeatSeat, EPK audio.'],
    ['ffmpeg','FFmpeg','media_processing','needs_verification','ffmpeg command line','Scorsese/Mozart cutting, extraction, transcoding.'],
    ['n8n','n8n','workflow_orchestration','installing','Local n8n in F:\\GoliathOmni','Workflow wiring layer after account/profile setup.'],
    ['resend','Resend','email_delivery','configured','/lead-engine/resend.php','Jessica email delivery.'],
    ['twilio','Twilio','sms_delivery','configured','Twilio constants in config.php','Jessica SMS and after-hours touches.'],
    ['retell','Retell','voice_ai','configured','/lead-engine/retell/','Jessica AI voice qualification.']
  ];
}
function g753_seed_tools($update=false){
  g753_install_schema(); $created=0; $updated=0;
  foreach(g753_default_tools() as $t){
    [$key,$name,$cap,$status,$hint,$notes]=$t;
    $row=['tool_key'=>$key,'tool_name'=>$name,'capability'=>$cap,'status'=>$status,'command_hint'=>$hint,'auth_status'=>in_array($status,['ready','configured'],true)?'available':'unknown','allowed_executives'=>g753_json(['all']),'queueable'=>1,'notes'=>$notes,'metadata'=>g753_json(['source'=>'v75_3_tool_registry'])];
    $existing=g753_one('SELECT id FROM executive_tool_registry WHERE tool_key=? LIMIT 1',[$key]);
    if($existing){ if($update){ g753_update_filtered('executive_tool_registry',$row,'id=:id',['id'=>(int)$existing['id']]); $updated++; } continue; }
    if(g753_insert_filtered('executive_tool_registry',$row)) $created++;
  }
  return ['created'=>$created,'updated'=>$updated];
}

function g753_active_count($exec){
  if(!g753_table('executive_commissions')) return 0;
  $r=g753_one("SELECT COUNT(*) c FROM executive_commissions WHERE executive_key=? AND status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100",[$exec]);
  return (int)($r['c']??0);
}
function g753_daily_count($missionId){
  if(!g753_table('executive_commissions')) return 0;
  $rows=g753_all("SELECT metadata FROM executive_commissions WHERE DATE(created_at)=CURRENT_DATE");
  $n=0; foreach($rows as $r){ $m=g753_arr($r['metadata']??null); if((int)($m['mission_id']??0)===(int)$missionId) $n++; }
  return $n;
}
function g753_cooldown_ok($mission){
  $last=$mission['last_dispatched_at']??null; if(!$last) return true;
  $mins=max(1,(int)($mission['cadence_minutes']??60));
  return (time()-strtotime($last)) >= ($mins*60);
}
function g753_prompt($mission){
  $exec=ucfirst(strtolower($mission['executive_key']));
  $tools=g753_arr($mission['tool_capabilities']??[]);
  $kpis=g753_arr($mission['kpis']??[]);
  return "GOLIATH OMNI V75.3 PRODUCTION EXECUTIVE MISSION\n\nExecutive: {$exec}\nDepartment: ".($mission['department']??'Executive Department')."\nMission: {$mission['title']}\n\nPRODUCTION RULE:\nThere is never a moment you sit idle. If no task exists, create a task. If no opportunity exists, find one. If no value exists, build it. Your job is to make Mark Pires and his businesses stronger today.\n\nDaily question:\n".($mission['daily_question']??'What can I do today to create value for Mark?')."\n\nMission statement:\n".($mission['mission_statement']??'')."\n\nPlaybook:\n".($mission['playbook']??'')."\n\nAutonomous backlog instruction:\n".($mission['backlog_prompt']??'')."\n\nAvailable tool capabilities for this mission:\n".implode(', ',$tools)."\n\nKPIs to improve:\n".implode(', ',$kpis)."\n\nRequired deliverable format:\n1. What I created today that did not exist yesterday\n2. Finished work or ranked opportunity packet\n3. Exact next action for Mark\n4. Handoffs to other executives, including why they are needed\n5. Tools requested/used and whether any are blocked\n6. Business value estimate: revenue, authority, time saved, relationships, content, or asset value\n7. What I will do next if Goliath gives no further directive\n\nBe concrete. Do not return generic strategy. Return visible production work Mark can review.";
}
function g753_create_commission($mission){
  $cid=g753_insert_filtered('executive_commissions',[
    'commission_uid'=>gdb_uid('com'),
    'executive_key'=>$mission['executive_key'],
    'title'=>'Production Mission: '.$mission['title'],
    'commission_type'=>'production_mission',
    'status'=>'queued',
    'priority'=>(int)$mission['priority'],
    'progress'=>0,
    'current_task'=>$mission['title'],
    'prompt'=>g753_prompt($mission),
    'metadata'=>g753_json(['mission_id'=>(int)$mission['id'],'mission_uid'=>$mission['mission_uid']??null,'mission_type'=>$mission['mission_type']??null,'source'=>'v75_3_production_dispatcher','never_idle'=>true,'department'=>$mission['department']??null])
  ]);
  if($cid){
    g753_update_filtered('executive_missions',['last_dispatched_at'=>g753_now(),'updated_at'=>g753_now()],'id=:id',['id'=>(int)$mission['id']]);
    if(g753_table('executive_heartbeats')){
      g753_exec("INSERT INTO executive_heartbeats (executive_key,status,phase,current_task,progress,current_step,last_heartbeat_at,updated_at)
        VALUES (:e,'queued','Production mission queued',:task,5,'V75.3 dispatcher created proactive production work',NOW(),NOW())
        ON DUPLICATE KEY UPDATE status='queued', phase='Production mission queued', current_task=VALUES(current_task), progress=GREATEST(progress,5), current_step='V75.3 dispatcher created proactive production work', last_heartbeat_at=NOW(), updated_at=NOW()",['e'=>$mission['executive_key'],'task'=>$mission['title']]);
    }
  }
  return $cid;
}
function g753_dispatch($limit=10,$force=false,$execOnly=''){
  g753_install_schema(); g753_seed_missions(false); g753_seed_tools(false);
  $created=[]; $skipped=[];
  if(!g753_table('executive_commissions')) return ['created'=>[],'skipped'=>[['reason'=>'executive_commissions_missing']]];
  $params=[]; $where="status='active'";
  if($execOnly){ $where.=' AND executive_key=?'; $params[]=$execOnly; }
  $missions=g753_all("SELECT * FROM executive_missions WHERE {$where} ORDER BY priority DESC, last_dispatched_at IS NULL DESC, last_dispatched_at ASC LIMIT 100",$params);
  foreach($missions as $m){
    if(count($created)>=$limit) break;
    $exec=$m['executive_key'];
    if(!$force && g753_active_count($exec)>0){ $skipped[]=['executive'=>$exec,'mission'=>$m['title'],'reason'=>'executive_has_active_work']; continue; }
    if(!$force && !g753_cooldown_ok($m)){ $skipped[]=['executive'=>$exec,'mission'=>$m['title'],'reason'=>'cadence_wait']; continue; }
    if(!$force && g753_daily_count((int)$m['id']) >= (int)$m['max_daily_commissions']){ $skipped[]=['executive'=>$exec,'mission'=>$m['title'],'reason'=>'daily_cap']; continue; }
    $cid=g753_create_commission($m);
    if($cid) $created[]=['commission_id'=>$cid,'executive'=>$exec,'department'=>$m['department']??null,'mission'=>$m['title']];
    else $skipped[]=['executive'=>$exec,'mission'=>$m['title'],'reason'=>'insert_failed'];
  }
  return ['created'=>$created,'skipped'=>$skipped];
}
function g753_award_daily($date=null){
  g753_install_schema(); $date=$date ?: date('Y-m-d');
  $existing=g753_one('SELECT * FROM executive_awards WHERE award_type=? AND award_date=? LIMIT 1',['daily_mvp',$date]); if($existing) return $existing;
  $execs=array_keys(g753_default_missions()); $scores=[];
  foreach($execs as $e){
    $deliveries=g753_table('goliath_worker_completions')?g753_one("SELECT COUNT(*) c FROM goliath_worker_completions WHERE LOWER(executive)=? AND DATE(created_at)=?",[$e,$date]):['c'=>0];
    $reviews=g753_table('goliath_review_queue')?g753_one("SELECT COUNT(*) c FROM goliath_review_queue WHERE LOWER(executive)=? AND DATE(created_at)=?",[$e,$date]):['c'=>0];
    $opps=g753_table('executive_opportunities')?g753_one("SELECT COUNT(*) c, COALESCE(SUM(value_score+initiative_score+collaboration_score),0) s FROM executive_opportunities WHERE executive_key=? AND DATE(created_at)=?",[$e,$date]):['c'=>0,'s'=>0];
    $d=(int)($deliveries['c']??0); $r=(int)($reviews['c']??0); $o=(int)($opps['c']??0); $os=(int)($opps['s']??0);
    $scores[$e]=['score'=>($d*35)+($r*12)+($o*20)+min(100,$os),'deliveries'=>$d,'reviews'=>$r,'opps'=>$o,'opp_score'=>$os];
  }
  arsort($scores); $winner=array_key_first($scores); $s=$scores[$winner];
  g753_exec("UPDATE executive_awards SET trophy_active=0 WHERE award_type='daily_mvp'");
  $id=g753_insert_filtered('executive_awards',[
    'award_uid'=>gdb_uid('award'),'award_type'=>'daily_mvp','award_date'=>$date,'executive_key'=>$winner,'title'=>'Executive of the Day: '.ucfirst($winner),
    'reason'=>'Awarded for highest measured business value: deliverables, review-ready work, initiatives, and collaboration. Trophy moves to this executive workspace for the next day.',
    'impact_score'=>(int)$s['score'],'initiative_score'=>(int)$s['opp_score'],'deliverables_count'=>(int)$s['deliveries'],'opportunities_count'=>(int)$s['opps'],'trophy_active'=>1,'metadata'=>g753_json(['scores'=>$scores,'source'=>'v75_3_daily_award'])
  ]);
  return g753_one('SELECT * FROM executive_awards WHERE id=?',[$id]);
}
?>
