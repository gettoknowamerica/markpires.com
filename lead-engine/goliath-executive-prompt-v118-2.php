<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function p1182_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function p1182_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
function p1182_insert(string $table,array $row):int{
 $cols=p1182_cols($table);$safe=[];
 foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for $table");
 return (int)gdb_insert($table,$safe);
}
function p1182_uid(string $prefix):string{
 return $prefix.'_'.date('YmdHis').'_'.sprintf('%06d',(int)((microtime(true)-floor(microtime(true)))*1000000)).'_'.bin2hex(random_bytes(16));
}
function p1182_originator(string $prompt):string{
 $p=mb_strtolower($prompt);
 $names=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 foreach($names as $name)if(preg_match('/(?:@|\b)'.preg_quote($name,'/').'\b/u',$p))return $name;
 if(preg_match('/\b(video|reel|short|film|thumbnail|visual|edit|documentary)\b/u',$p))return 'scorsese';
 if(preg_match('/\b(blog|article|letter|copy|script|write|page)\b/u',$p))return 'shakespeare';
 if(preg_match('/\b(lead|expired|fsbo|owner|contact|phone)\b/u',$p))return 'scout';
 if(preg_match('/\b(email|follow[- ]?up|calendar|crm|relationship)\b/u',$p))return 'jessica';
 if(preg_match('/\b(audio|song|music|master|voice)\b/u',$p))return 'mozart';
 if(preg_match('/\b(opportunity|speaking|venue|partner|sponsor|podcast)\b/u',$p))return 'prospector';
 if(preg_match('/\b(seo|aeo|schema|ranking|analytics)\b/u',$p))return 'einstein';
 if(preg_match('/\b(verify|ownership|llc|probate|record)\b/u',$p))return 'sherlock';
 if(preg_match('/\b(archive|youtube|timestamp|chapter)\b/u',$p))return 'columbo';
 if(preg_match('/\b(revenue|roi|conversion|monetize)\b/u',$p))return 'rockefeller';
 return 'pandora';
}
function p1182_route(string $originator):array{
 $ring=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 $start=array_search($originator,$ring,true);if($start===false)$start=0;
 $route=[];for($i=0;$i<count($ring);$i++)$route[]=$ring[($start+$i)%count($ring)];
 $route[]=$originator;
 $route[]='goliath';
 return $route;
}
function p1182_stage(string $exec,bool $originatorReview=false):array{
 if($originatorReview)return ['originator_final_review','Originator final review',
  'Review the fully evolved artifact. Return the complete approved deliverable. You may select and restore any earlier version if it is stronger. Never return only notes.'];
 $map=[
  'jessica'=>['relationship_campaign','Human Touch and CRM','Apply audience personalization, Mark-voice communication, follow-up and CRM improvements directly to the artifact.'],
  'scout'=>['competitive_intelligence','Lead and market intelligence','Apply verified market, audience, contact and opportunity intelligence directly to the artifact.'],
  'sherlock'=>['verification','Verification and evidence','Correct unsupported claims and apply verified evidence directly inside the artifact.'],
  'einstein'=>['seo_aeo','SEO, AEO and compounding','Apply SEO, AEO, schema, FAQs, analytics and compounding improvements directly inside the artifact.'],
  'shakespeare'=>['authority_content','Authority content','Rewrite and improve the complete publish-ready artifact directly in Mark Pires voice.'],
  'columbo'=>['archive_enrichment','Archive enrichment','Apply exact archive links, titles, timestamps and source material directly inside the artifact.'],
  'scorsese'=>['visual_media','Visual and media','Apply visual structure, images, thumbnails, scene plans, video assets and media references directly to the artifact.'],
  'mozart'=>['audio_package','Audio and music','Apply narration, sound, music, cleanup or mastering assets directly to the artifact when useful.'],
  'prospector'=>['distribution','Distribution and opportunity','Apply concrete distribution, outreach, partnership and exposure opportunities directly to the artifact.'],
  'rockefeller'=>['roi_conversion','ROI and conversion','Apply CTA, business value, conversion and monetization improvements directly to the artifact.'],
  'pandora'=>['creative_enrichment','Creative expansion','Apply memorable hooks, emotional angles and derivative concepts directly to the artifact.'],
  'goliath'=>['goliath_publish_deliver','Founder delivery','Preserve the originator-approved artifact exactly. Select the strongest version if needed and route it to Founder review, publishing, social distribution and repurposing.']
 ];
 return $map[$exec]??['enrichment','Executive enrichment','Apply improvements directly to the full artifact.'];
}

$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))$input=array_merge($_POST,$_GET);
$key=trim((string)($input['key']??''));
if(!hash_equals(p1182_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $prompt=trim((string)($input['prompt']??''));
 if($prompt==='')throw new RuntimeException('Please enter a request.');
 $originator=p1182_originator($prompt);
 $priority=max(5000,(int)($input['priority']??5000));
 $title=trim((string)($input['title']??''));
 if($title==='')$title='Founder Priority — '.mb_substr(preg_replace('/\s+/u',' ',$prompt),0,100);

 $requestUid=p1182_uid('founder_request');
 $missionUid=p1182_uid('founder_mission');
 $route=p1182_route($originator);

 gdb()->beginTransaction();
 try{
  $requestId=p1182_insert('goliath_v118_founder_requests',[
   'request_uid'=>$requestUid,'request_text'=>$prompt,'originator_key'=>$originator,
   'priority'=>$priority,'status'=>'creating','created_at'=>gdb_now()
  ]);

  $missionId=p1182_insert('goliath_v112_missions',[
   'mission_uid'=>$missionUid,'mission_type'=>'founder_priority','title'=>$title,
   'originator_key'=>$originator,'status'=>'queued','priority'=>$priority,'current_stage_no'=>1,
   'source_payload_json'=>gdb_json([
    'directive'=>$prompt,'request_uid'=>$requestUid,'founder_priority'=>true,
    'requested_by'=>'Mark Pires','artifact_contract'=>'v118.2-evolving-asset'
   ]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);

  foreach($route as $i=>$exec){
   [$stageKey,$stageTitle,$instructions]=p1182_stage($exec,$i===count($route)-2);
   p1182_insert('goliath_v112_stages',[
    'mission_id'=>$missionId,'stage_no'=>$i+1,'executive_key'=>$exec,
    'stage_key'=>$stageKey,'title'=>$stageTitle,'instructions'=>$instructions,
    'status'=>$i===0?'ready':'waiting','local_task_id'=>null,
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }

  p1182_insert('goliath_v112_events',[
   'mission_id'=>$missionId,'executive_key'=>$originator,
   'event_type'=>'founder_priority_created','title'=>$title,
   'details'=>'New independent Founder-priority evolving-asset mission created.',
   'url'=>'/dashboard/goliath-workflow-review-v118-2.php?mission_id='.$missionId.'&embed=1',
   'created_at'=>gdb_now()
  ]);

  gdb_update('goliath_v118_founder_requests',[
   'mission_id'=>$missionId,'status'=>'created'
  ],'id=:id',['id'=>$requestId]);

  gdb()->commit();
 }catch(Throwable $tx){
  if(gdb()->inTransaction())gdb()->rollBack();
  throw $tx;
 }

 echo json_encode([
  'ok'=>true,'version'=>'V118.2 Unlimited Founder Priority',
  'request_uid'=>$requestUid,'mission_id'=>$missionId,'mission_uid'=>$missionUid,
  'originator'=>$originator,'priority'=>$priority,'route'=>$route,
  'review_url'=>'/dashboard/goliath-workflow-review-v118-2.php?mission_id='.$missionId.'&embed=1',
  'message'=>'New independent priority mission created. You can submit another immediately.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V118.2 Unlimited Founder Priority',
  'error'=>'mission_creation_failed','message'=>$e->getMessage(),
  'file'=>basename($e->getFile()),'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>