<?php
declare(strict_types=1);ini_set('display_errors','0');set_time_limit(50);header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';require_once __DIR__.'/goliath-orchestration-lib-v114.php';
function b114_key():string{if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;return 'timetomakethedonuts';}
function b114_one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
$key=(string)($_GET['key']??'');if(!hash_equals(b114_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$lock=b114_one("SELECT GET_LOCK('goliath_v114_boot',0) acquired");if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'status'=>'locked']);exit;}
$created=[];$recovered=0;
try{
 $recovered=(int)gdb()->exec("UPDATE goliath_v112_stages SET status='ready',local_task_id=NULL,last_error='Recovered by V114 organization boot',updated_at=NOW() WHERE status IN ('queued_local','working') AND updated_at<DATE_SUB(NOW(),INTERVAL 45 MINUTE)");
 $active=(int)(b114_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE status IN ('queued','working')")['c']??0);
 if($active===0){
  $missionUid='daily_authority_'.date('Ymd');
  $exists=b114_one("SELECT id FROM goliath_v112_missions WHERE mission_uid=?",[$missionUid]);
  if(!$exists){
   $mid=(int)gdb_insert('goliath_v112_missions',['mission_uid'=>$missionUid,'mission_type'=>'daily_authority_research','title'=>'Daily Connecticut Authority Opportunity — '.date('F j, Y'),'originator_key'=>'einstein','status'=>'queued','priority'=>95,'current_stage_no'=>1,'source_payload_json'=>gdb_json(['directive'=>'Find one real search opportunity that can produce qualified Fairfield County buyer or seller traffic. Research current competitors and create one fully useful publishable asset.','created_by'=>'v114_morning_boot']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $seq=[
    ['einstein','opportunity_research','Find the strongest current search opportunity','Use OpenClaw web search, Newspaper24k and configured scrapers. Identify one real Fairfield County real-estate search opportunity, the pages currently winning, the user intent, content gaps, keywords, questions, source URLs and recommended asset. Produce a concrete research package.'],
    ['shakespeare','full_draft','Write the complete authority asset','Using the shared research artifact, write a complete, detailed, original, reader-friendly article or authority page. Include local relevance, examples, useful decisions, FAQs and natural calls to action. Return the entire HTML.'],
    ['scout','competitive_enrichment','Enrich with local and competitive intelligence','Research current local articles, community sources, discussions and missing questions. Improve the entire shared artifact directly and return it complete.'],
    ['sherlock','verification','Verify every material claim','Verify facts, statistics and Connecticut-specific claims against authoritative sources. Correct the complete artifact and preserve source evidence.'],
    ['pandora','creative_enrichment','Strengthen hooks and emotional relevance','Improve the opening, story, examples, transitions and memorability without sacrificing accuracy. Return the complete artifact.'],
    ['scorsese','visual_package','Create the complete visual package','Produce featured-image, section-image, infographic, social-card and thumbnail deliverables or production-ready prompts with exact placements and alt text.'],
    ['mozart','audio_package','Create useful audio derivatives','Create a concise narration or audio derivative only if it adds real value.'],
    ['prospector','distribution_package','Create real distribution opportunities','Find concrete backlink, media, partner, newsletter and social distribution targets with usable pitch angles.'],
    ['jessica','relationship_review','Prepare the relationship and CRM use','Review as Mark. Identify the exact audience, CRM segments, campaign message and follow-up path. Do not send until approved.'],
    ['rockefeller','roi_review','Finalize conversion and revenue value','Improve CTA, lead value, conversion path and priority without making the asset salesy.'],
    ['columbo','archive_enrichment','Find Mark archive material','Find exact existing Mark Pires or Discover CT content that can strengthen the asset, with titles and timestamps where available.'],
    ['einstein','originator_final_review','Originator final review','Merge the strongest contributions, ensure search intent is satisfied and approve the complete final artifact for Goliath.'],
    ['goliath','goliath_publish_deliver','Publish and deliver','Approve only a real finished asset. Publish through the proper template, update its index, store the URL and create one delivered asset.']
   ];
   foreach($seq as $i=>$s)gdb_insert('goliath_v112_stages',['mission_id'=>$mid,'stage_no'=>$i+1,'executive_key'=>$s[0],'stage_key'=>$s[1],'title'=>$s[2],'instructions'=>$s[3],'status'=>$i===0?'ready':'waiting','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $created[]=['mission_id'=>$mid,'title'=>'Daily Connecticut Authority Opportunity'];
  }
 }
 echo json_encode(['ok'=>true,'version'=>'V114.0 Organization Boot','active_missions'=>$active,'stale_stages_recovered'=>$recovered,'missions_created'=>$created,'constitutional_documents_loaded'=>true,'next'=>'goliath-v114-orchestration-engine.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{try{b114_one("SELECT RELEASE_LOCK('goliath_v114_boot') released");}catch(Throwable $e){}}
?>