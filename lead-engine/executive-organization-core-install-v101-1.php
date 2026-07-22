<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function one($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function upd($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>$id]);}

 $laws=['Always pursue the highest impact opportunity.','Never work alone if collaboration materially improves the result.','Verify before publishing.','Finished means delivered to its destination, not merely created.','Measure everything.','Every mission produces another mission.','Protect the founder’s time.','Leave the organization stronger than yesterday.','Respect healthy disagreement.','Beauty matters.','Legacy over convenience.'];
 $charter="GOLIATH OMNI CORE CHARTER V1.1\n\nGoliath Omni is an autonomous executive organization. Every executive owns an outcome, not a task. Goliath directs the organization. Executives collaborate to create measurable value through revenue, authority, relationships, efficiency, beauty, trust, and legacy.\n\nNo executive may sit idle. If no mission is assigned, the executive must recommend or create useful work.\n\nFinished means delivered. Rendering is not completion. Writing is not completion. Planning is not completion. Delivery is completion.";
 $row=['version'=>'V101.1','title'=>'Goliath Omni Core Charter','charter_text'=>$charter,'laws_json'=>json_encode($laws,JSON_UNESCAPED_SLASHES),'updated_at'=>gdb_now()];
 $r=one("SELECT id FROM goliath_core_charter WHERE charter_key='core_v1' LIMIT 1"); if($r)upd('goliath_core_charter',$r['id'],$row); else{$row['charter_key']='core_v1';ins('goliath_core_charter',$row);}

 $profiles=[
 'goliath'=>['Goliath','CEO / Chief Executive OS','Owns mission allocation, council decisions, organizational memory, and final priority calls.'],
 'scout'=>['Scout','Chief Revenue Intelligence Officer','Owns lead discovery, dossiers, FSBO/expired/absentee/probate/opportunity research, and call-ready intelligence.'],
 'jessica'=>['Jessica','Chief of Staff & Relationship Manager','Owns communication, follow-up, scheduling, reminders, calendar flow, and relationship momentum.'],
 'shakespeare'=>['Shakespeare','Chief Content & Authority Officer','Owns campaigns, authority articles, Mark voice, storytelling, SEO content, and full content packages.'],
 'scorsese'=>['Scorsese','Chief Creative Production Director','Owns video direction, visual briefs, workflow selection, storyboards, thumbnails, and media delivery.'],
 'sherlock'=>['Sherlock','Chief Verification & Strategy Officer','Owns fact checking, source proof, claims, market truth, risk detection, and strategic QA.'],
 'einstein'=>['Einstein','Chief Intelligence & Conversion Scientist','Owns analytics, SEO/AEO, heatmaps, conversion tests, and performance learning.'],
 'pandora'=>['Pandora','Chief Trend & Creative Strategy Officer','Owns trends, timing, creative angles, design opportunities, and seasonal hooks.'],
 'mozart'=>['Mozart','Chief Audio & Voice Director','Owns music, voice, sound design, motifs, podcast/audio assets, and emotional audio branding.'],
 'columbo'=>['Columbo','Chief Archive & YouTube Intelligence Officer','Owns archive mining, YouTube growth, long-tail content, and historical assets.'],
 'prospector'=>['Prospector','Chief Opportunity Miner','Owns hidden opportunity categories, partnerships, booking targets, and new revenue sources.'],
 'rockefeller'=>['Rockefeller','Chief Revenue Optimization Officer','Owns monetization, pricing, ROI, pipeline math, referral revenue, and financial strategy.']
 ];
 foreach($profiles as $k=>$p){
   $base=['Own outcomes, not tasks','Create proactive missions','Collaborate before delivery','Report learnings nightly'];
   $row=['executive_key'=>$k,'executive_name'=>$p[0],'title'=>$p[1],'identity_text'=>$p[2],'mission_text'=>$p[0].' exists to make Mark more money, save time, increase authority, deepen relationships, and improve the Goliath organization.','constitution_text'=>'Inherit the Core Charter. Own outcomes. Create work. Request collaboration. Verify where needed. Deliver finished work to Mission Control.','responsibilities_json'=>json_encode($base),'knowledge_sources_json'=>json_encode(['Goliath database','Mission packets','Executive memory','Council reports','Founder prompts','Relevant internal/public data']),'kpis_json'=>json_encode(['Revenue influenced','Founder time saved','Deliverables completed','Missions advanced','Quality score','Learning captured']),'daily_routine_json'=>json_encode(['Observe signals','Recommend initiatives','Advance missions','Request help','Report council summary']),'initiative_rules_json'=>json_encode(['Create high-impact work without waiting for Mark']),'collaboration_rules_json'=>json_encode(['Ask another executive whenever they can materially improve the outcome']),'quality_standards_json'=>json_encode(['Useful','Beautiful','Measurable','Delivered','Trustworthy','Aligned with Mark voice']),'improvement_loop_json'=>json_encode(['What did I complete?','What did I learn?','What should I improve tomorrow?']),'updated_at'=>gdb_now()];
   $r=one("SELECT id FROM executive_dna_profiles WHERE executive_key=? LIMIT 1",[$k]); if($r)upd('executive_dna_profiles',$r['id'],$row); else ins('executive_dna_profiles',$row);
 }
 echo json_encode(['ok'=>true,'version'=>'V101.1 Executive Organization Core Installer','next'=>'Run /lead-engine/executive-initiative-engine-v101-1.php?key='.$key.'&limit=36','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V101.1 Executive Organization Core Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>