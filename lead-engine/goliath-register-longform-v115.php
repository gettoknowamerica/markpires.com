<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$key=(string)($_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$url=trim((string)($_POST['url']??''));$filename=trim((string)($_POST['filename']??''));$title=trim((string)($_POST['title']??$filename));$brand=trim((string)($_POST['brand_key']??'discover_ct'));$instructions=trim((string)($_POST['instructions']??''));
if($url===''||$filename===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_uploaded_file']);exit;}
$uid='v115_media_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
$mid=(int)gdb_insert('goliath_v112_missions',[
 'mission_uid'=>$uid,'mission_type'=>'longform_media_edit','title'=>$title,'originator_key'=>'scorsese','status'=>'queued','priority'=>100,'current_stage_no'=>1,
 'source_url'=>$url,'source_payload_json'=>gdb_json(['filename'=>$filename,'url'=>$url,'brand'=>$brand,'instructions'=>$instructions,'outputs'=>['16:9 master','9:16 vertical','shorts','captions','chapters','three thumbnails','title','description','tags'],'preserve_original'=>true]),
 'created_at'=>gdb_now(),'updated_at'=>gdb_now()
]);
$route=['scorsese','columbo','shakespeare','einstein','pandora','mozart','prospector','jessica','rockefeller','scout','sherlock','scorsese','goliath'];
foreach($route as $i=>$exec){
 $final=($i===count($route)-2);
 $instructionsByExec=[
  'scorsese'=>$final?'Review all additions, preserve source quality, finalize edit decision list and approve the complete package for Goliath.':'Analyze the entire long-form source non-destructively. Produce exact scenes, hooks, cuts, 16:9 master plan, 9:16 shorts, captions, chapters and three bold click-worthy thumbnail concepts. AI generation length is not an editing limit.',
  'columbo'=>'Find the strongest moments, hooks, stories, comedy, motivation and archive connections with exact time ranges.',
  'shakespeare'=>'Create titles, descriptions, chapter wording, captions and supporting story copy.',
  'einstein'=>'Optimize titles, metadata, search intent, AEO, keywords and discoverability.',
  'pandora'=>'Strengthen hooks, retention, emotional arc and platform-specific creative angles.',
  'mozart'=>'Plan audio cleanup, leveling, music and narration where useful.',
  'prospector'=>'Identify distribution, collaboration, sponsor and media opportunities.',
  'jessica'=>'Prepare CRM audiences, approved social/email use and follow-up. Do not publish without approval.',
  'rockefeller'=>'Prioritize clips and formats by likely business and audience value.',
  'scout'=>'Research current platform trends and competing successful videos for this topic.',
  'sherlock'=>'Verify factual claims and flag anything risky or unsupported.',
  'goliath'=>'Confirm Scorsese approved the completed package, then deliver the actual media outputs and schedule approved distribution.'
 ];
 gdb_insert('goliath_v112_stages',['mission_id'=>$mid,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$final?'originator_final_review':($exec==='goliath'?'goliath_publish_deliver':'media_'.$exec),'title'=>ucfirst($exec).' media production stage','instructions'=>$instructionsByExec[$exec]??'Improve the shared media artifact.','status'=>$i===0?'ready':'waiting','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
}
echo json_encode(['ok'=>true,'mission_id'=>$mid,'mission_uid'=>$uid,'status'=>'queued'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>