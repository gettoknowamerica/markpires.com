<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function p1181_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function p1181_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function p1181_insert(string $table,array $row):int{
 $cols=p1181_cols($table);$safe=[];
 foreach($row as $key=>$value)if(isset($cols[$key]))$safe[$key]=$value;
 if(!$safe)throw new RuntimeException("No compatible columns for $table.");
 return (int)gdb_insert($table,$safe);
}
function p1181_uid(string $prefix):string{
 $micro=str_replace('.','',sprintf('%.6f',microtime(true)));
 return $prefix.'_'.$micro.'_'.bin2hex(random_bytes(10));
}
function p1181_originator(string $prompt):string{
 $lower=mb_strtolower($prompt);
 $names=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 foreach($names as $name)if(preg_match('/(?:@|\b)'.preg_quote($name,'/').'\b/u',$lower))return $name;
 if(preg_match('/\b(video|reel|short|film|thumbnail|comfy|visual|edit)\b/u',$lower))return 'scorsese';
 if(preg_match('/\b(blog|article|copy|script|write|page)\b/u',$lower))return 'shakespeare';
 if(preg_match('/\b(lead|expired|fsbo|owner|contact|phone)\b/u',$lower))return 'scout';
 if(preg_match('/\b(email|follow[- ]?up|calendar|crm|relationship)\b/u',$lower))return 'jessica';
 if(preg_match('/\b(audio|song|music|master|voice)\b/u',$lower))return 'mozart';
 if(preg_match('/\b(opportunity|speaking|venue|partner|sponsor|podcast)\b/u',$lower))return 'prospector';
 if(preg_match('/\b(seo|aeo|schema|ranking|analytics)\b/u',$lower))return 'einstein';
 if(preg_match('/\b(verify|ownership|llc|probate|record)\b/u',$lower))return 'sherlock';
 if(preg_match('/\b(archive|youtube|timestamp|chapter)\b/u',$lower))return 'columbo';
 if(preg_match('/\b(revenue|roi|conversion|monetize)\b/u',$lower))return 'rockefeller';
 return 'pandora';
}
function p1181_route(string $originator):array{
 $ring=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 $start=array_search($originator,$ring,true);
 if($start===false)$start=0;
 $route=[];
 for($i=0;$i<count($ring);$i++)$route[]=$ring[($start+$i)%count($ring)];
 $route[]=$originator;
 $route[]='goliath';
 return $route;
}
function p1181_stage(string $exec,bool $originatorReview=false):array{
 if($originatorReview)return [
  'originator_final_review',
  'Originator final review',
  'Review the entire evolved deliverable. Return the complete approved work product, not a report about it. Preserve the strongest earlier version if a later change reduced quality.'
 ];
 $map=[
  'jessica'=>['relationship_campaign','Human Touch and CRM','Improve audience personalization, Mark-voice communication, follow-up, CRM and response handling. Return the full evolved deliverable plus a concise change note.'],
  'scout'=>['competitive_intelligence','Lead and market intelligence','Add verified opportunity, audience and market intelligence. Return the full evolved deliverable plus a concise change note.'],
  'sherlock'=>['verification','Verification and evidence','Verify facts and correct unsupported claims. Return the full evolved deliverable plus a concise change note.'],
  'einstein'=>['seo_aeo','SEO, AEO and compounding','Improve search, schema, FAQs, analytics and compounding. Return the full evolved deliverable plus a concise change note.'],
  'shakespeare'=>['authority_content','Authority content','Improve the complete publish-ready writing in Mark Pires voice. Return the full evolved deliverable plus a concise change note.'],
  'columbo'=>['archive_enrichment','Archive enrichment','Add exact archive sources, URLs, titles, timestamps and repurpose material. Return the full evolved deliverable plus a concise change note.'],
  'scorsese'=>['visual_media','Visual and media','Add the complete visual/video package and production assets. Return the full evolved deliverable plus a concise change note.'],
  'mozart'=>['audio_package','Audio and music','Add useful audio, narration, music or mastering. Return the full evolved deliverable plus a concise change note.'],
  'prospector'=>['distribution','Distribution and opportunity','Add concrete distribution, outreach and partnership paths. Return the full evolved deliverable plus a concise change note.'],
  'rockefeller'=>['roi_conversion','ROI and conversion','Improve CTA, business value and monetization. Return the full evolved deliverable plus a concise change note.'],
  'pandora'=>['creative_enrichment','Creative expansion','Add memorable ideas, hooks and derivatives. Return the full evolved deliverable plus a concise change note.'],
  'goliath'=>['goliath_publish_deliver','Founder delivery','Preserve the originator-approved deliverable exactly. Route it to Founder review, publishing, Goliath Social and repurposing. Never replace it with an executive overview.']
 ];
 return $map[$exec]??['enrichment','Executive enrichment','Return the complete evolved deliverable plus a concise change note.'];
}

$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))$input=array_merge($_GET,$_POST);
$key=trim((string)($input['key']??''));
if(!hash_equals(p1181_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $prompt=trim((string)($input['prompt']??''));
 if($prompt==='')throw new RuntimeException('Please enter a priority request.');

 $originator=p1181_originator($prompt);
 $priority=max(5000,(int)($input['priority']??5000));
 $title=trim((string)($input['title']??''));
 if($title==='')$title='Founder Priority — '.mb_substr(preg_replace('/\s+/u',' ',$prompt),0,100);

 $missionId=0;$uid='';$lastError=null;
 for($attempt=1;$attempt<=5;$attempt++){
  $uid=p1181_uid('founder_priority');
  try{
   gdb()->beginTransaction();
   $missionId=p1181_insert('goliath_v112_missions',[
    'mission_uid'=>$uid,
    'mission_type'=>'founder_priority',
    'title'=>$title,
    'originator_key'=>$originator,
    'status'=>'queued',
    'priority'=>$priority,
    'current_stage_no'=>1,
    'source_payload_json'=>gdb_json([
     'directive'=>$prompt,
     'requested_by'=>'Mark Pires',
     'source'=>'mission_control',
     'founder_priority'=>true,
     'request_nonce'=>bin2hex(random_bytes(12)),
     'created_at'=>date('c')
    ]),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);

   $route=p1181_route($originator);$total=count($route);
   foreach($route as $index=>$exec){
    [$stageKey,$stageTitle,$instructions]=p1181_stage($exec,$index===$total-2);
    p1181_insert('goliath_v112_stages',[
     'mission_id'=>$missionId,
     'stage_no'=>$index+1,
     'executive_key'=>$exec,
     'stage_key'=>$stageKey,
     'title'=>$stageTitle,
     'instructions'=>$instructions,
     'status'=>$index===0?'ready':'waiting',
     'local_task_id'=>null,
     'created_at'=>gdb_now(),
     'updated_at'=>gdb_now()
    ]);
   }

   p1181_insert('goliath_v112_events',[
    'mission_id'=>$missionId,
    'executive_key'=>$originator,
    'event_type'=>'founder_priority_created',
    'title'=>$title,
    'details'=>'A new independent Founder-priority request was placed at the top of the queue.',
    'url'=>'/dashboard/goliath-workflow-review-v118-1.php?mission_id='.$missionId.'&embed=1',
    'created_at'=>gdb_now()
   ]);

   gdb()->commit();
   break;
  }catch(PDOException $e){
   if(gdb()->inTransaction())gdb()->rollBack();
   $lastError=$e;
   if((string)$e->getCode()!=='23000')throw $e;
   usleep(30000*$attempt);
  }catch(Throwable $e){
   if(gdb()->inTransaction())gdb()->rollBack();
   throw $e;
  }
 }

 if($missionId<1){
  throw new RuntimeException('Could not create a unique mission after five attempts: '.($lastError?$lastError->getMessage():'unknown duplicate'));
 }

 $dispatch=null;
 try{
  $host='https://'.($_SERVER['HTTP_HOST']??'www.markpires.com');
  $raw=@file_get_contents($host.'/lead-engine/goliath-v115-1-sequential-engine.php?key='.rawurlencode($key),false,stream_context_create(['http'=>['timeout'=>25,'ignore_errors'=>true]]));
  $decoded=json_decode((string)$raw,true);if(is_array($decoded))$dispatch=$decoded;
 }catch(Throwable $ignored){}

 echo json_encode([
  'ok'=>true,
  'version'=>'V118.1 Repeatable Founder Priority',
  'mission_id'=>$missionId,
  'mission_uid'=>$uid,
  'originator'=>$originator,
  'title'=>$title,
  'priority'=>$priority,
  'route'=>$route,
  'review_url'=>'/dashboard/goliath-workflow-review-v118-1.php?mission_id='.$missionId.'&embed=1',
  'dispatch'=>$dispatch,
  'message'=>'A new independent priority mission was created. Additional requests can be submitted immediately.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,
  'version'=>'V118.1 Repeatable Founder Priority',
  'error'=>'mission_creation_failed',
  'message'=>$e->getMessage(),
  'file'=>basename($e->getFile()),
  'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>