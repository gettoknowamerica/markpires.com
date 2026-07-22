<?php
declare(strict_types=1);
ini_set('display_errors','0');
set_time_limit(120);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function n115_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function n115_one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
function n115_ring():array{
 // Pandora is last; the next station after Pandora is Jessica.
 return ['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
}
function n115_route(string $originator):array{
 $ring=n115_ring();
 $idx=array_search($originator,$ring,true);
 if($idx===false){$idx=0;$originator=$ring[0];}
 $route=[];
 for($i=0;$i<count($ring);$i++)$route[]=$ring[($idx+$i)%count($ring)];
 $route[]=$originator; // originator final review
 $route[]='goliath';  // CEO delivery
 return $route;
}
function n115_stage(string $exec,bool $finalOriginator=false):array{
 if($finalOriginator)return ['originator_final_review','Originator final review','Review the complete shared artifact after all eleven stations. Preserve the original intent, merge the strongest additions, request a targeted revision only if essential, then approve the complete artifact for Goliath.'];
 $map=[
  'jessica'=>['relationship_campaign','Relationship and campaign review','Review as Mark Pires. Add CRM segmentation, personalization, follow-up and approved-message guidance. If not applicable, return the shared artifact unchanged with pass_through=true.'],
  'scout'=>['competitive_intelligence','Competitive and opportunity intelligence','Research competitors, local examples, source URLs, backlinks, leads and opportunity gaps. Improve the shared artifact directly.'],
  'sherlock'=>['verification','Verification and hidden details','Verify material claims and investigate relevant ownership, LLC, probate, trust, tax, public-record or evidence issues. Correct the shared artifact directly.'],
  'einstein'=>['seo_aeo','SEO, AEO and intelligence optimization','Improve search intent, entities, keywords, schema, snippets, FAQs, internal links, analytics and conversion. Return the complete artifact.'],
  'shakespeare'=>['authority_content','Authority content and narrative','Research and write or improve the full authority asset. Return the complete revised artifact, never a brief.'],
  'columbo'=>['archive_enrichment','Archive enrichment','Find exact Mark Pires archive material, clips, titles and timestamps that strengthen the project. If none apply, pass through without inventing.'],
  'scorsese'=>['visual_media','Visual and media production','Create or specify useful imagery, thumbnails, 16:9 and 9:16 media, shorts, captions and placements. One ComfyUI job at a time. If media adds no value, pass through.'],
  'mozart'=>['audio_package','Audio treatment','Add narration, audio cleanup, music or sonic branding only when useful. Otherwise pass through without blocking.'],
  'prospector'=>['distribution','Distribution and outreach','Add concrete media, backlink, sponsor, partner, venue, podcast, newsletter and social distribution opportunities.'],
  'rockefeller'=>['roi_conversion','ROI and conversion','Improve commercial value, CTA, prioritization, monetization and effort-to-return while preserving trust.'],
  'pandora'=>['creative_enrichment','Creative and emotional enrichment','Strengthen hooks, story, emotional relevance, campaign angles and memorability without sacrificing accuracy.'],
  'goliath'=>['goliath_publish_deliver','CEO delivery','Confirm the originator approved the complete artifact. Publish or deliver the real asset, save its URL/path, and create exactly one finished deliverable.']
 ];
 return $map[$exec]??['enrichment','Executive enrichment','Improve the shared artifact or return it unchanged with pass_through=true.'];
}

$key=(string)($_GET['key']??'');
if(!hash_equals(n115_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$lock=n115_one("SELECT GET_LOCK('goliath_v1151_normalize',0) acquired");
if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'status'=>'locked']);exit;}

try{
 // Stop the duplicate storm.
 $archived=0;
 try{$archived=(int)gdb()->exec("UPDATE local_ai_tasks SET status='archived',workflow_state='archived',updated_at=NOW() WHERE task_type='goliath_v112_stage' AND status IN ('queued','working')");}catch(Throwable $e){
  try{$archived=(int)gdb()->exec("UPDATE local_ai_tasks SET status='archived',updated_at=NOW() WHERE task_type='goliath_v112_stage' AND status IN ('queued','working')");}catch(Throwable $ignored){}
 }

 $missions=gdb_all("SELECT * FROM goliath_v112_missions WHERE status IN ('queued','working') ORDER BY id")?:[];
 $rebuilt=[];
 foreach($missions as $m){
  $mid=(int)$m['id'];$originator=strtolower((string)$m['originator_key']);
  if(!in_array($originator,n115_ring(),true))$originator='jessica';

  // Remove only unfinished orchestration material. No delivered assets exist in the supplied truth report.
  gdb()->prepare("DELETE FROM goliath_v112_artifacts WHERE mission_id=? AND delivered_by_goliath=0")->execute([$mid]);
  gdb()->prepare("DELETE FROM goliath_v112_stages WHERE mission_id=?")->execute([$mid]);

  $route=n115_route($originator);$total=count($route);
  foreach($route as $i=>$exec){
   $finalOriginator=($i===$total-2);
   $s=n115_stage($exec,$finalOriginator);
   gdb_insert('goliath_v112_stages',[
    'mission_id'=>$mid,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$s[0],
    'title'=>$s[1],'instructions'=>$s[2],
    'status'=>$i===0?'ready':'waiting','local_task_id'=>null,
    'input_artifact_id'=>null,'output_artifact_id'=>null,'attempt_count'=>0,
    'started_at'=>null,'completed_at'=>null,'last_error'=>null,
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }
  gdb_update('goliath_v112_missions',[
   'status'=>'queued','current_stage_no'=>1,'final_artifact_id'=>null,
   'completed_at'=>null,'delivered_at'=>null,'updated_at'=>gdb_now()
  ],'id=:id',['id'=>$mid]);
  gdb_insert('goliath_v112_events',[
   'mission_id'=>$mid,'executive_key'=>$originator,'event_type'=>'route_normalized',
   'title'=>'V115.1 sequential ring installed',
   'details'=>implode(' → ',$route),'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
  ]);
  $rebuilt[]=['mission_id'=>$mid,'originator'=>$originator,'route'=>$route];
 }
 echo json_encode([
  'ok'=>true,'version'=>'V115.1 Sequential Ring Normalizer',
  'queued_stage_tasks_archived'=>$archived,
  'missions_rebuilt'=>$rebuilt,
  'rule'=>'Each mission moves to the next station in the circular list, returns to its originator, then goes to Goliath.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{try{n115_one("SELECT RELEASE_LOCK('goliath_v1151_normalize') released");}catch(Throwable $e){}}
?>