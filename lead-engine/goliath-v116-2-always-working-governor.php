<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
set_time_limit(55);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function aw1162_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function aw1162_one(string $sql,array $params=[]):array{
 try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function aw1162_all(string $sql,array $params=[]):array{
 try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function aw1162_cols(string $table):array{
 $rows=aw1162_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
function aw1162_insert_safe(string $table,array $row):int{
 $cols=aw1162_cols($table);$safe=[];
 foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for ".$table);
 return (int)gdb_insert($table,$safe);
}
function aw1162_ring():array{
 return ['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
}
function aw1162_route(string $originator):array{
 $ring=aw1162_ring();
 $idx=array_search($originator,$ring,true);
 if($idx===false)$idx=0;
 $route=[];
 for($i=0;$i<count($ring);$i++)$route[]=$ring[($idx+$i)%count($ring)];
 $route[]=$originator;
 $route[]='goliath';
 return $route;
}
function aw1162_stage(string $exec,bool $originatorFinal=false):array{
 if($originatorFinal){
  return ['originator_final_review','Originator final review',
   'Review the complete shared artifact after all Executive stations. Preserve the original mission, merge the strongest additions, correct anything necessary, and approve the complete tangible artifact for Goliath. Return the entire final artifact.'];
 }
 $map=[
  'jessica'=>['relationship_campaign','Relationship and campaign review','Add specific CRM segmentation, personalized Human Touch messaging, follow-up timing, response handling, and relationship next actions. Improve the complete artifact.'],
  'scout'=>['competitive_intelligence','Competitive and opportunity intelligence','Research real current opportunities, public sources, owners, contact paths, competitors, forums, property intelligence, source URLs, and missing information. Add only verified useful value.'],
  'sherlock'=>['verification','Verification and hidden details','Verify material claims and investigate public-record, ownership, LLC, probate, trust, tax, legal, and evidence details where relevant. Correct the complete artifact.'],
  'einstein'=>['seo_aeo','SEO, AEO and intelligence optimization','Improve search intent, entities, keywords, schema, snippets, FAQs, internal links, analytics, authority, and conversion while preserving quality.'],
  'shakespeare'=>['authority_content','Authority content and narrative','Write or improve the complete authority asset in Mark Pires voice. Make it original, useful, human, visually structured, and emotionally intelligent. Return the entire artifact.'],
  'columbo'=>['archive_enrichment','Archive enrichment','Find exact Mark Pires, Discover Connecticut, House Detective, music, speaking, or LegacySaved archive material that strengthens the project. Include titles, URLs, timestamps, and repurpose opportunities.'],
  'scorsese'=>['visual_media','Visual and media production','Create or specify the complete visual package: hero image, section imagery, thumbnail, video, shorts, captions, aspect ratios, placements, and production instructions.'],
  'mozart'=>['audio_package','Audio treatment','Create narration, audio cleanup, music treatment, sonic branding, mastering, or audio derivatives only when useful. Otherwise pass through without blocking.'],
  'prospector'=>['distribution','Distribution and outreach','Find concrete media, backlink, sponsor, referral, partner, venue, speaking, podcast, newsletter, and social distribution opportunities with usable outreach angles.'],
  'rockefeller'=>['roi_conversion','ROI and conversion','Improve business value, prioritization, CTA, monetization, conversion path, and effort-to-return while preserving trust and relationship quality.'],
  'pandora'=>['creative_enrichment','Creative and emotional enrichment','Expand the idea into stronger hooks, campaigns, stories, emotional relevance, surprising angles, and additional high-value derivatives without reducing accuracy.'],
  'goliath'=>['goliath_publish_deliver','CEO delivery','Confirm the originator approved the complete artifact. Package the final deliverable, save its review URL or path, notify Mark, and create exactly one finished deliverable ready for publishing or approval.']
 ];
 return $map[$exec]??['enrichment','Executive enrichment','Improve the complete shared artifact or pass through without blocking.'];
}
function aw1162_directive(string $exec):array{
 $date=date('F j, Y');
 $map=[
  'scout'=>['Scout Revenue Discovery Mission — '.$date,'Find and enrich the strongest current Fairfield County revenue opportunities. Work expired listings, FSBO, Connecticut foreclosure data, owner/contact research, phone and email paths, scoring, and a call-ready dossier. Produce real records and one prioritized founder call list.'],
  'sherlock'=>['Sherlock Verification Mission — '.$date,'Take the highest-value unresolved property or lead intelligence and verify ownership, public records, LLC/trust/probate details, timeline, evidence, and hidden risks. Produce a verified dossier that another Executive can act on.'],
  'einstein'=>['Einstein Authority Growth Mission — '.$date,'Identify one real Fairfield County search, AEO, backlink, authority, or conversion opportunity and create a complete optimization package tied to measurable business value.'],
  'shakespeare'=>['Shakespeare Publishing Mission — '.$date,'Create one beautiful, publishable, deeply useful authority asset for a real buyer, seller, relocation, expired, luxury, town, or lead-specific need. Include visual placements, FAQs, schema guidance, internal links, and Mark Pires calls to action.'],
  'columbo'=>['Columbo Archive Restoration Mission — '.$date,'Restore and enrich one valuable archive item from Mark insPires the World or Discover Connecticut. Find chapters, viral moments, title/description improvements, clip opportunities, thumbnails, and handoffs to Scorsese or Mozart.'],
  'scorsese'=>['Scorsese Media Production Mission — '.$date,'Create one review-ready media package from the best available queued footage, archive moment, lead need, blog, or brand campaign. Include the main production, derivatives, thumbnail, captions, and Director Log.'],
  'mozart'=>['Mozart Music and Audio Mission — '.$date,'Take the strongest available live performance, voice track, LegacySaved audio, narration, or media project and create a studio-quality audio treatment, derivative, or production plan with real deliverables.'],
  'prospector'=>['Prospector Opportunity Mission — '.$date,'Find and prepare concrete outreach for the strongest real-estate, referral, speaking, media, venue, sponsor, backlink, partnership, or paid opportunity available today.'],
  'jessica'=>['Jessica Human Touch Mission — '.$date,'Select the highest-priority lead, relationship, sphere contact, or response needing attention. Create a personalized Human Touch follow-up package, CRM actions, response path, and founder notification.'],
  'rockefeller'=>['Rockefeller Revenue Mission — '.$date,'Audit the highest-value active opportunity, campaign, lead, content asset, or workflow and create a concrete ROI, conversion, prioritization, and monetization improvement package.'],
  'pandora'=>['Pandora Expansion Mission — '.$date,'Take one current lead, content asset, campaign, business need, or archive discovery and expand it into multiple original high-value ideas, hooks, derivatives, and business opportunities.']
 ];
 return $map[$exec]??[ucfirst($exec).' Daily Mission — '.$date,'Create one tangible, high-value deliverable aligned with the Executive Constitution.'];
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(aw1162_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$maxWaves=max(1,min(24,(int)($_GET['max_waves']??8)));
$targetPerExec=max(1,min(2,(int)($_GET['target']??1)));

$lock=aw1162_one("SELECT GET_LOCK('goliath_v1162_always_working',0) acquired");
if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'version'=>'V116.2 Always Working Governor','status'=>'skipped_overlap']);exit;}

$created=[];$recovered=0;$activeBefore=[];$activeAfter=[];$errors=[];
try{
 $recovered=(int)gdb()->exec("UPDATE goliath_v112_stages SET status='ready',local_task_id=NULL,last_error='Recovered by V116.2 Always Working Governor',updated_at=NOW() WHERE status IN ('dispatching','queued_local','working') AND updated_at<DATE_SUB(NOW(),INTERVAL 45 MINUTE)");

 foreach(aw1162_ring() as $exec){
  $active=(int)(aw1162_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE LOWER(originator_key)=? AND status IN ('queued','working')",[$exec])['c']??0);
  $activeBefore[$exec]=$active;
  $todayCount=(int)(aw1162_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE LOWER(originator_key)=? AND DATE(created_at)=CURDATE() AND mission_uid LIKE ?",[$exec,'v1162_'.$exec.'_'.date('Ymd').'%'])['c']??0);

  while($active<$targetPerExec && $todayCount<$maxWaves){
   $wave=$todayCount+1;
   $uid='v1162_'.$exec.'_'.date('Ymd').'_w'.str_pad((string)$wave,2,'0',STR_PAD_LEFT);
   if(aw1162_one("SELECT id FROM goliath_v112_missions WHERE mission_uid=? LIMIT 1",[$uid])){$todayCount++;continue;}

   [$title,$directive]=aw1162_directive($exec);
   if($wave>1)$title.=' — Wave '.$wave;

   try{
    gdb()->beginTransaction();
    $missionId=aw1162_insert_safe('goliath_v112_missions',[
     'mission_uid'=>$uid,'mission_type'=>'proactive_daily_production','title'=>$title,'originator_key'=>$exec,
     'status'=>'queued','priority'=>1000-$wave,'current_stage_no'=>1,
     'source_payload_json'=>gdb_json([
      'directive'=>$directive,'created_by'=>'v116.2_always_working_governor','wave'=>$wave,'date'=>date('Y-m-d'),
      'law'=>'Create tangible work, pass sequentially through all Executive stations, return to originator, then Goliath.'
     ]),
     'created_at'=>gdb_now(),'updated_at'=>gdb_now()
    ]);

    $route=aw1162_route($exec);$total=count($route);
    foreach($route as $i=>$station){
     $originatorFinal=($i===$total-2);
     [$stageKey,$stageTitle,$instructions]=aw1162_stage($station,$originatorFinal);
     aw1162_insert_safe('goliath_v112_stages',[
      'mission_id'=>$missionId,'stage_no'=>$i+1,'executive_key'=>$station,'stage_key'=>$stageKey,
      'title'=>$stageTitle,'instructions'=>$instructions,'status'=>$i===0?'ready':'waiting',
      'local_task_id'=>null,'input_artifact_id'=>null,'output_artifact_id'=>null,'attempt_count'=>0,
      'created_at'=>gdb_now(),'updated_at'=>gdb_now()
     ]);
    }

    aw1162_insert_safe('goliath_v112_events',[
     'mission_id'=>$missionId,'executive_key'=>$exec,'event_type'=>'mission_created','title'=>$title,
     'details'=>'V116.2 proactive mission created. Route: '.implode(' → ',$route),
     'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
    ]);
    gdb()->commit();

    $created[]=['mission_id'=>$missionId,'originator'=>$exec,'wave'=>$wave,'title'=>$title];
    $active++;$todayCount++;
   }catch(Throwable $e){
    if(gdb()->inTransaction())gdb()->rollBack();
    $errors[]=['executive'=>$exec,'message'=>$e->getMessage()];
    break;
   }
  }

  $activeAfter[$exec]=(int)(aw1162_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE LOWER(originator_key)=? AND status IN ('queued','working')",[$exec])['c']??0);
 }

 echo json_encode([
  'ok'=>true,'version'=>'V116.2 Always Working Governor','status'=>'complete',
  'target_active_per_executive'=>$targetPerExec,'max_daily_waves_per_executive'=>$maxWaves,
  'stale_stages_recovered'=>$recovered,'active_before'=>$activeBefore,'missions_created'=>$created,
  'active_after'=>$activeAfter,'errors'=>$errors,
  'next'=>'Run goliath-v115-1-sequential-engine.php. The local runtime will claim the resulting stage tasks.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{
 try{aw1162_one("SELECT RELEASE_LOCK('goliath_v1162_always_working') released");}catch(Throwable $e){}
}
?>