<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function p1183_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function p1183_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
function p1183_insert(string $table,array $row):int{
 $cols=p1183_cols($table);$safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for $table");
 return (int)gdb_insert($table,$safe);
}
function p1183_uid(string $prefix):string{
 return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(20));
}
function p1183_originator(string $prompt):string{
 $p=mb_strtolower($prompt);
 $names=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 foreach($names as $n)if(preg_match('/(?:@|\b)'.preg_quote($n,'/').'\b/u',$p))return $n;
 if(preg_match('/\b(video|film|episode|documentary|reel|short|thumbnail|edit)\b/u',$p))return 'scorsese';
 if(preg_match('/\b(blog|article|letter|copy|script|page|write)\b/u',$p))return 'shakespeare';
 if(preg_match('/\b(lead|expired|fsbo|owner|phone|contact)\b/u',$p))return 'scout';
 if(preg_match('/\b(email|follow[- ]?up|calendar|appointment|crm)\b/u',$p))return 'jessica';
 if(preg_match('/\b(audio|song|music|master|voice)\b/u',$p))return 'mozart';
 if(preg_match('/\b(opportunity|speaking|venue|partner|sponsor|podcast)\b/u',$p))return 'prospector';
 if(preg_match('/\b(seo|aeo|schema|ranking|analytics)\b/u',$p))return 'einstein';
 if(preg_match('/\b(verify|ownership|llc|probate|record)\b/u',$p))return 'sherlock';
 if(preg_match('/\b(archive|youtube|timestamp|chapter)\b/u',$p))return 'columbo';
 if(preg_match('/\b(revenue|roi|conversion|monetize)\b/u',$p))return 'rockefeller';
 return 'pandora';
}
function p1183_route(string $originator):array{
 $ring=['jessica','scout','sherlock','einstein','shakespeare','columbo','scorsese','mozart','prospector','rockefeller','pandora'];
 $start=array_search($originator,$ring,true);if($start===false)$start=0;
 $route=[];for($i=0;$i<count($ring);$i++)$route[]=$ring[($start+$i)%count($ring)];
 $route[]=$originator;$route[]='goliath';return $route;
}
function p1183_stage(string $exec,bool $review=false):array{
 if($review)return ['originator_final_review','Originator final review','Review all preserved versions, select the strongest base and return the complete approved deliverable. Never return notes only.'];
 $map=[
 'jessica'=>['relationship_campaign','Human Touch and CRM','Apply Human Touch, audience, follow-up and CRM value directly to the complete artifact.'],
 'scout'=>['competitive_intelligence','Lead and market intelligence','Apply verified lead, audience and market intelligence directly to the complete artifact.'],
 'sherlock'=>['verification','Verification and evidence','Correct unsupported claims and apply evidence directly to the complete artifact.'],
 'einstein'=>['seo_aeo','SEO, AEO and compounding','Apply SEO, AEO, schema, analytics and compounding directly to the complete artifact.'],
 'shakespeare'=>['authority_content','Authority content','Rewrite and improve the complete publish-ready artifact in Mark Pires voice.'],
 'columbo'=>['archive_enrichment','Archive enrichment','Apply exact archive sources, URLs, titles, timestamps and reusable material directly to the artifact.'],
 'scorsese'=>['visual_media','Visual and media','Apply visual/media assets directly. For video, return a complete production object with script, scenes, takes, EDL, render/output references.'],
 'mozart'=>['audio_package','Audio and music','Apply narration, sound, music, stem and mastering assets directly to the artifact.'],
 'prospector'=>['distribution','Distribution and opportunity','Apply concrete distribution, outreach, partnership and exposure paths directly to the artifact.'],
 'rockefeller'=>['roi_conversion','ROI and conversion','Apply CTA, business value, conversion and monetization directly to the artifact.'],
 'pandora'=>['creative_enrichment','Creative expansion','Apply memorable hooks, emotional angles and useful derivatives directly to the artifact.'],
 'goliath'=>['goliath_publish_deliver','Founder delivery','Preserve the selected complete artifact exactly and route it to Founder review, publishing, social, repurposing and measurement.']
 ];
 return $map[$exec];
}

$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))$input=array_merge($_POST,$_GET);
$key=trim((string)($input['key']??''));
if(!hash_equals(p1183_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $prompt=trim((string)($input['prompt']??''));if($prompt==='')throw new RuntimeException('Please enter a request.');
 $originator=p1183_originator($prompt);$priority=max(5000,(int)($input['priority']??5000));
 $title=trim((string)($input['title']??''));if($title==='')$title='Founder Priority — '.mb_substr(preg_replace('/\s+/u',' ',$prompt),0,100);
 $requestUid=p1183_uid('request');$missionUid=p1183_uid('mission');$route=p1183_route($originator);

 gdb()->beginTransaction();
 try{
  $requestId=p1183_insert('goliath_v118_founder_requests',[
   'request_uid'=>$requestUid,'request_text'=>$prompt,'originator_key'=>$originator,
   'priority'=>$priority,'status'=>'creating','created_at'=>gdb_now()
  ]);
  $missionId=p1183_insert('goliath_v112_missions',[
   'mission_uid'=>$missionUid,'mission_type'=>'founder_priority','title'=>$title,'originator_key'=>$originator,
   'status'=>'queued','priority'=>$priority,'current_stage_no'=>1,
   'source_payload_json'=>gdb_json(['directive'=>$prompt,'request_uid'=>$requestUid,'artifact_contract'=>'v118.3','requested_by'=>'Mark Pires']),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  foreach($route as $i=>$exec){
   [$keyStage,$stageTitle,$instructions]=p1183_stage($exec,$i===count($route)-2);
   p1183_insert('goliath_v112_stages',[
    'mission_id'=>$missionId,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$keyStage,
    'title'=>$stageTitle,'instructions'=>$instructions,'status'=>$i===0?'ready':'waiting',
    'local_task_id'=>null,'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }
  p1183_insert('goliath_v112_events',[
   'mission_id'=>$missionId,'executive_key'=>$originator,'event_type'=>'founder_priority_created',
   'title'=>$title,'details'=>'Independent V118.3 evolving-asset mission created.',
   'url'=>'/dashboard/goliath-workflow-review-v118-3.php?mission_id='.$missionId.'&embed=1','created_at'=>gdb_now()
  ]);
  gdb_update('goliath_v118_founder_requests',['mission_id'=>$missionId,'status'=>'created'],'id=:id',['id'=>$requestId]);
  gdb()->commit();
 }catch(Throwable $e){if(gdb()->inTransaction())gdb()->rollBack();throw $e;}

 echo json_encode([
  'ok'=>true,'version'=>'V118.3 Unlimited Founder Priority','request_uid'=>$requestUid,
  'mission_id'=>$missionId,'mission_uid'=>$missionUid,'originator'=>$originator,'route'=>$route,
  'review_url'=>'/dashboard/goliath-workflow-review-v118-3.php?mission_id='.$missionId.'&embed=1',
  'message'=>'New independent mission created. Submit another immediately.','time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'version'=>'V118.3 Priority','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>