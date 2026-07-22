<?php
declare(strict_types=1);
ini_set('display_errors','0');
set_time_limit(55);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-orchestration-lib-v115.php';

function b115_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function b115_one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
function b115_route(string $originator):array{
 $canonical=['shakespeare','scout','sherlock','einstein','pandora','scorsese','mozart','prospector','jessica','rockefeller','columbo'];
 $route=[$originator];
 foreach($canonical as $e)if($e!==$originator)$route[]=$e;
 $route[]=$originator;
 $route[]='goliath';
 return $route;
}
function b115_stage(string $exec,int $position,int $total,string $originator):array{
 $map=[
  'shakespeare'=>['authority_content','Research and write the complete authority asset','Use OpenClaw, Newspaper24k, scrapers and authoritative sources. Work directly on the shared artifact. Return the complete revised artifact, never a brief.'],
  'scout'=>['competitive_intelligence','Add competitive and opportunity intelligence','Find competing content, missing questions, real local examples, source URLs, backlinks and opportunity gaps. Improve the shared artifact directly.'],
  'sherlock'=>['verification','Verify facts and hidden details','Verify all material claims and investigate relevant ownership, LLC, probate, trust, tax or public-record details. Correct the shared artifact directly.'],
  'einstein'=>['seo_aeo','Improve SEO, AEO and search intent','Analyze current winners, keywords, entities, schema, snippets, FAQs, internal links and conversion. Return the complete improved artifact.'],
  'pandora'=>['creative_enrichment','Make the project memorable','Strengthen hooks, story, emotional relevance, campaign angles and shareability without sacrificing truth.'],
  'scorsese'=>['visual_media','Create the visual and media package','Create or specify the featured image, graphics, 16:9 and 9:16 media, shorts, thumbnail concepts, captions and exact placements. Use ComfyUI only one job at a time.'],
  'mozart'=>['audio_package','Create useful audio treatment','Create narration, audio cleanup, music or sonic-branding assets only where they materially improve the project.'],
  'prospector'=>['distribution','Create concrete distribution opportunities','Find real media, backlink, partner, sponsor, speaking, venue, newsletter and social distribution opportunities with usable pitches.'],
  'jessica'=>['relationship_campaign','Prepare CRM and campaign use','Review as Mark Pires. Prepare audience segments, approved-message draft, personalization and drip path. Do not send without campaign approval.'],
  'rockefeller'=>['roi_conversion','Improve ROI and conversion','Improve revenue value, CTA, conversion path, effort and priority without making the asset salesy.'],
  'columbo'=>['archive_enrichment','Find Mark archive gold','Find exact existing Mark Pires, Discover CT, House Detective, music, comedy or motivational content with titles and timestamps that can enrich the project.'],
  'goliath'=>['deliver','CEO final gate and delivery','Confirm the originator approved the complete artifact. Publish or deliver the real asset, save its URL/path and create exactly one finished deliverable.']
 ];
 $x=$map[$exec]??['enrichment','Improve the shared artifact','Make a concrete improvement and return the complete artifact.'];
 if($exec===$originator && $position===$total-1){
  return ['originator_final_review','Originator final review','Review everything the organization added. Preserve the original idea, merge the strongest improvements, request another pass only if required, then return the complete approved artifact to Goliath.'];
 }
 return $x;
}

$key=(string)($_GET['key']??'');
if(!hash_equals(b115_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$lock=b115_one("SELECT GET_LOCK('goliath_v115_boot',0) acquired");
if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'version'=>'V115 Clockwork Boot','status'=>'locked']);exit;}

$created=[];$recovered=0;$archived=0;
try{
 $recovered=(int)gdb()->exec("UPDATE goliath_v112_stages SET status='ready',local_task_id=NULL,last_error='Recovered by V115 boot',updated_at=NOW() WHERE status IN ('queued_local','working') AND updated_at<DATE_SUB(NOW(),INTERVAL 60 MINUTE)");
 // Archive old queued initiative spam, but never V112/V115 production stages.
 try{
  $archived=(int)gdb()->exec("UPDATE local_ai_tasks SET status='archived',workflow_state='archived',updated_at=NOW() WHERE status='queued' AND task_type NOT IN ('goliath_v112_stage','ask_goliath_live_v111','goliath_v113_media_edit') AND created_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
 }catch(Throwable $ignored){}

 $directives=[
  'shakespeare'=>'Find and build the strongest Connecticut authority article opportunity.',
  'scout'=>'Find a qualified lead, sponsor, backlink or local business opportunity and build a verified dossier.',
  'sherlock'=>'Find a hidden property opportunity using ownership, LLC, probate, trust, tax or repeat-expiration research.',
  'einstein'=>'Find the highest-value SEO/AEO ranking opportunity across Mark’s websites.',
  'pandora'=>'Find a timely, emotional or viral campaign idea for one of Mark’s brands.',
  'scorsese'=>'Find the highest-value media project from the upload queue, content queue or current campaigns.',
  'mozart'=>'Find one audio, narration, podcast, music or sonic-branding opportunity that supports an active brand.',
  'prospector'=>'Find one real sponsorship, partnership, speaking, venue, winery, podcast or media opportunity.',
  'jessica'=>'Find the highest-value CRM follow-up or approved campaign opportunity requiring Mark’s voice.',
  'rockefeller'=>'Find the strongest near-term revenue or asset-compounding opportunity.',
  'columbo'=>'Find one valuable piece of Mark’s existing archive that should be revived and repurposed.'
 ];

 foreach($directives as $originator=>$directive){
  $missionUid='v115_'.$originator.'_'.date('Ymd');
  if(b115_one("SELECT id FROM goliath_v112_missions WHERE mission_uid=?",[$missionUid]))continue;
  // Only one unfinished proactive mission per originator.
  $active=b115_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE originator_key=? AND status IN ('queued','working')",[$originator]);
  if((int)($active['c']??0)>0)continue;

  $mid=(int)gdb_insert('goliath_v112_missions',[
   'mission_uid'=>$missionUid,'mission_type'=>'v115_proactive_'.$originator,
   'title'=>ucfirst($originator).' Daily Production Mission — '.date('F j, Y'),
   'originator_key'=>$originator,'status'=>'queued','priority'=>80,'current_stage_no'=>1,
   'source_payload_json'=>gdb_json(['directive'=>$directive,'created_by'=>'v115_clockwork_boot','fixed_funnel'=>true]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  $route=b115_route($originator);$total=count($route);
  foreach($route as $i=>$exec){
   $s=b115_stage($exec,$i,$total,$originator);
   gdb_insert('goliath_v112_stages',[
    'mission_id'=>$mid,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$s[0],
    'title'=>$s[1],'instructions'=>$s[2],'status'=>$i===0?'ready':'waiting',
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }
  $created[]=['mission_id'=>$mid,'originator'=>$originator,'stages'=>$total];
 }
 echo json_encode(['ok'=>true,'version'=>'V115.0 Clockwork Organization Boot','missions_created'=>$created,'stale_recovered'=>$recovered,'legacy_tasks_archived'=>$archived,'rule'=>'Fixed funnel; originator starts and receives final review before Goliath.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{try{b115_one("SELECT RELEASE_LOCK('goliath_v115_boot') released");}catch(Throwable $e){}}
?>