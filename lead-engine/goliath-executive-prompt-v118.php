<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function fp118_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function fp118_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function fp118_insert_safe(string $table,array $row):int{
 $cols=fp118_cols($table);$safe=[];
 foreach($row as $key=>$value)if(isset($cols[$key]))$safe[$key]=$value;
 if(!$safe)throw new RuntimeException("No compatible columns for $table.");
 return (int)gdb_insert($table,$safe);
}
function fp118_uid(string $prefix):string{
 return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5));
}
function fp118_originator(string $prompt):string{
 $lower=mb_strtolower($prompt);
 foreach(['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'] as $name){
  if(str_contains($lower,'@'.$name)||preg_match('/\b'.preg_quote($name,'/').'\b/u',$lower))return $name;
 }
 if(preg_match('/\b(video|reel|short|film|thumbnail|comfy|image|visual|edit)\b/u',$lower))return 'scorsese';
 if(preg_match('/\b(blog|article|town page|copy|email copy|script|write|seo page)\b/u',$lower))return 'shakespeare';
 if(preg_match('/\b(lead|expired|fsbo|phone|owner|contact|seller opportunity)\b/u',$lower))return 'scout';
 if(preg_match('/\b(verify|ownership|llc|probate|trust|public record)\b/u',$lower))return 'sherlock';
 if(preg_match('/\b(seo|aeo|schema|ranking|keyword|backlink|analytics)\b/u',$lower))return 'einstein';
 if(preg_match('/\b(archive|youtube|chapter|timestamp|old video|old content)\b/u',$lower))return 'columbo';
 if(preg_match('/\b(audio|song|music|master|stem|vocal|soundtrack)\b/u',$lower))return 'mozart';
 if(preg_match('/\b(speaking|venue|podcast|sponsor|partnership|outreach|opportunity)\b/u',$lower))return 'prospector';
 if(preg_match('/\b(email|follow-up|follow up|calendar|appointment|crm|relationship)\b/u',$lower))return 'jessica';
 if(preg_match('/\b(revenue|roi|price|monetize|conversion|commission)\b/u',$lower))return 'rockefeller';
 if(preg_match('/\b(idea|creative|campaign angle|viral|hook|expand)\b/u',$lower))return 'pandora';
 return 'goliath';
}
function fp118_route(string $originator):array{
 $team=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 if($originator==='goliath'){
  $route=$team;
 }else{
  $route=[$originator];
  foreach($team as $exec)if($exec!==$originator)$route[]=$exec;
 }
 $route[]=$originator==='goliath'?'shakespeare':$originator;
 $route[]='goliath';
 return $route;
}
function fp118_stage(string $exec,bool $finalOriginator=false):array{
 if($finalOriginator){
  return ['originator_final_review','Originator final review',
   'Review the complete shared artifact after every Executive contribution. Preserve Mark’s request and the strongest work. Return the entire approved deliverable, not a summary.'];
 }
 $map=[
  'jessica'=>['relationship_campaign','Human Touch and CRM pass','Add the precise audience, Mark-voice outreach, CRM actions, follow-up timing and founder notification. Do not send without approval.'],
  'scout'=>['competitive_intelligence','Lead and opportunity intelligence','Add verified opportunities, contacts, public sources, market intelligence and practical next actions.'],
  'sherlock'=>['verification','Verification and evidence','Verify material facts, ownership, public records and evidence. Correct anything unsupported.'],
  'einstein'=>['seo_aeo','SEO, AEO and compounding','Improve search intent, schema, FAQs, entities, internal links, backlinks, analytics and post-publication compounding.'],
  'shakespeare'=>['authority_content','Authority content and publishing','Create or improve the complete publish-ready written asset in Mark Pires’ voice. Include structure, CTA and exact publishing guidance.'],
  'columbo'=>['archive_enrichment','Archive enrichment','Find exact Mark Pires or Discover Connecticut archive material, titles, URLs, timestamps and repurpose opportunities.'],
  'scorsese'=>['visual_media','Visual and media package','Create the complete visual/video package, thumbnail, captions, formats, placements and production path.'],
  'mozart'=>['audio_package','Music, voice and audio package','Create useful narration, sound, music, cleanup, mastering or audio derivatives when they materially improve the asset.'],
  'prospector'=>['distribution','Distribution and outreach','Add real media, social, partner, speaking, backlink, sponsor and outreach opportunities.'],
  'rockefeller'=>['roi_conversion','ROI and conversion','Improve business value, CTA, priority, conversion and monetization without reducing trust.'],
  'pandora'=>['creative_enrichment','Creative expansion','Add memorable hooks, emotional angles and valuable derivatives without sacrificing accuracy.'],
  'goliath'=>['goliath_publish_deliver','Founder delivery and exposure','Preserve the originator-approved deliverable exactly. Route it to Founder review, markpires.com/blogs or the correct workspace, Goliath Social, repurposing and ongoing compounding. Never replace it with an overview.']
 ];
 return $map[$exec]??['enrichment','Executive improvement','Improve the complete shared artifact and return it intact.'];
}

$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))$input=array_merge($_GET,$_POST);

$key=trim((string)($input['key']??''));
if(!hash_equals(fp118_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $prompt=trim((string)($input['prompt']??''));
 if($prompt===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_prompt']);exit;}

 $originator=fp118_originator($prompt);
 $title=trim((string)($input['title']??''));
 if($title==='')$title='Founder Priority: '.mb_substr(preg_replace('/\s+/u',' ',$prompt),0,100);
 $priority=max(5000,(int)($input['priority']??5000));
 $missionUid=fp118_uid('founder_priority');

 gdb()->beginTransaction();
 try{
  $missionId=fp118_insert_safe('goliath_v112_missions',[
   'mission_uid'=>$missionUid,
   'mission_type'=>'founder_priority',
   'title'=>$title,
   'originator_key'=>$originator,
   'status'=>'queued',
   'priority'=>$priority,
   'current_stage_no'=>1,
   'source_payload_json'=>gdb_json([
    'directive'=>$prompt,
    'source'=>'mission_control_share_with_team',
    'founder_priority'=>true,
    'requested_by'=>'Mark Pires',
    'created_by'=>'v118_priority_intake'
   ]),
   'created_at'=>gdb_now(),
   'updated_at'=>gdb_now()
  ]);

  $route=fp118_route($originator);$total=count($route);
  foreach($route as $index=>$exec){
   $finalOriginator=($index===$total-2);
   [$stageKey,$stageTitle,$instructions]=fp118_stage($exec,$finalOriginator);
   fp118_insert_safe('goliath_v112_stages',[
    'mission_id'=>$missionId,
    'stage_no'=>$index+1,
    'executive_key'=>$exec,
    'stage_key'=>$stageKey,
    'title'=>$stageTitle,
    'instructions'=>$instructions,
    'status'=>$index===0?'ready':'waiting',
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);
  }

  fp118_insert_safe('goliath_v112_events',[
   'mission_id'=>$missionId,
   'executive_key'=>$originator,
   'event_type'=>'founder_priority_created',
   'title'=>$title,
   'details'=>'Mark shared a priority request with the Executive Team. It was placed at the top of the V112 mission queue.',
   'url'=>'/dashboard/goliath-mission-control.php',
   'created_at'=>gdb_now()
  ]);

  if(fp118_cols('goliath_notifications')){
   fp118_insert_safe('goliath_notifications',[
    'executive'=>ucfirst($originator),
    'title'=>'Founder priority mission',
    'message'=>$prompt,
    'priority'=>'urgent',
    'metadata'=>gdb_json(['mission_id'=>$missionId,'mission_uid'=>$missionUid])
   ]);
  }

  gdb()->commit();
 }catch(Throwable $transactionError){
  if(gdb()->inTransaction())gdb()->rollBack();
  throw $transactionError;
 }

 // Best-effort immediate dispatch. Failure here does not lose the mission.
 $dispatch=null;
 try{
  $host='https://'.($_SERVER['HTTP_HOST']??'www.markpires.com');
  $url=$host.'/lead-engine/goliath-v115-1-sequential-engine.php?key='.rawurlencode($key);
  $context=stream_context_create(['http'=>['timeout'=>25,'ignore_errors'=>true]]);
  $raw=@file_get_contents($url,false,$context);
  $decoded=json_decode((string)$raw,true);
  if(is_array($decoded))$dispatch=$decoded;
 }catch(Throwable $ignored){}

 echo json_encode([
  'ok'=>true,
  'version'=>'V118 Founder Priority Intake',
  'mission_id'=>$missionId,
  'mission_uid'=>$missionUid,
  'title'=>$title,
  'originator'=>$originator,
  'priority'=>$priority,
  'route'=>$route,
  'queue_position'=>'top_priority',
  'dispatch'=>$dispatch,
  'message'=>'Priority mission added without leaving Mission Control.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V118 Founder Priority Intake','error'=>'caught_exception',
  'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>