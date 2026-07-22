<?php
/**
 * Goliath V75.5 — Scorsese → ComfyUI Real Render Bridge
 * Converts Scorsese text deliverables into real ComfyUI WAN workflow jobs.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if (file_exists(__DIR__.'/goliath-action-ledger.php')) require_once __DIR__.'/goliath-action-ledger.php';

function scb_key_ok(){
  $key=$_GET['key']??($_POST['key']??'');
  $raw=@file_get_contents('php://input');
  if($raw){$j=json_decode($raw,true); if(is_array($j)&&isset($j['key'])) $key=$j['key'];}
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
  return hash_equals($expected,(string)$key);
}
function scb_table($table){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function scb_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function scb_json($v){return gdb_json(is_array($v)?$v:[]);} 
function scb_out($arr,$code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($arr,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;}
function scb_workflow_path(){ return __DIR__.'/../goliath-core/comfy-workflows/text_to_video_wan.json'; }
function scb_template_loaded(){ return is_file(scb_workflow_path()); }
function scb_health(){
  $tables=['scorsese_comfy_jobs','goliath_worker_completions','executive_commissions','goliath_review_queue','goliath_notifications'];
  $out=[];foreach($tables as $t)$out[$t]=scb_table($t);
  $counts=[];
  if(gdb_enabled()){
    $counts['queued']=scb_table('scorsese_comfy_jobs')?(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status='queued'")?:['c'=>0])['c']):0;
    $counts['working']=scb_table('scorsese_comfy_jobs')?(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('working','rendering')")?:['c'=>0])['c']):0;
    $counts['complete']=scb_table('scorsese_comfy_jobs')?(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('complete','completed')")?:['c'=>0])['c']):0;
    $counts['failed']=scb_table('scorsese_comfy_jobs')?(int)((gdb_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('failed','error')")?:['c'=>0])['c']):0;
    $counts['scorsese_completions']=scb_table('goliath_worker_completions')?(int)((gdb_one("SELECT COUNT(*) c FROM goliath_worker_completions WHERE LOWER(executive)='scorsese'")?:['c'=>0])['c']):0;
  }
  return ['ok'=>gdb_enabled()&&!in_array(false,$out,true),'configured'=>gdb_enabled(),'workflow_template'=>scb_template_loaded()?scb_workflow_path():null,'tables'=>$out,'counts'=>$counts,'time'=>date('c')];
}
function scb_extract_director_prompt($title,$output){
  $text=trim((string)$output);
  // Keep a compact, production-ready prompt. Avoid giant URLs and generic report sections.
  $text=preg_replace('/\s+/',' ',$text);
  $text=mb_substr($text,0,1800);
  $prompt="Cinematic professional marketing video for Mark Pires / Goliath Omni. {$title}. {$text}. High-end real estate and media production look, dynamic camera movement, emotional visual storytelling, polished lighting, no text artifacts, no distorted people, premium commercial finish.";
  return mb_substr($prompt,0,2400);
}
function scb_build_wan_workflow($positivePrompt,$jobId=null,$title='scorsese'){
  $path=scb_workflow_path();
  if(!is_file($path)) return null;
  $raw=file_get_contents($path);
  $wf=json_decode($raw,true);
  if(!is_array($wf)) return null;
  if(isset($wf['6']['inputs']['text'])) $wf['6']['inputs']['text']=$positivePrompt;
  if(isset($wf['7']['inputs']['text'])) $wf['7']['inputs']['text']='low quality, blurry, distorted face, distorted hands, watermark, text artifacts, captions, jpeg artifacts, extra limbs, malformed body, static boring shot, overexposed, underexposed, bad composition, noisy, duplicate people';
  if(isset($wf['3']['inputs']['seed'])) $wf['3']['inputs']['seed']=random_int(100000000,999999999999);
  if(isset($wf['51']['inputs'])){
    $wf['51']['inputs']['prompt']=$positivePrompt;
    $wf['51']['inputs']['negative_prompt']='low quality, blurry, watermark, text artifacts, distorted people';
    $wf['51']['inputs']['seed']=random_int(100000000,999999999);
  }
  if(isset($wf['53']['inputs'])){
    $wf['53']['inputs']['prompt']=$positivePrompt;
    $wf['53']['inputs']['seed']=random_int(100000000,999999999999);
  }
  if(isset($wf['50']['inputs']['filename_prefix'])){
    $safe=preg_replace('/[^a-zA-Z0-9_\-]+/','_', strtolower((string)$title));
    $safe=trim($safe,'_'); if(!$safe) $safe='scorsese_render';
    $wf['50']['inputs']['filename_prefix']='video/goliath_scorsese_'.($jobId?:'job').'_'.$safe;
  }
  return $wf;
}
function scb_insert_job($completion){
  $title=$completion['title']??'Scorsese Media Render';
  $output=$completion['output']??($completion['result']??'');
  $positive=scb_extract_director_prompt($title,$output);
  $workflow=scb_build_wan_workflow($positive, $completion['id']??null, $title);
  $prompt="SCORSESE PRODUCTION PACKAGE → COMFYUI\n\nTitle: {$title}\n\nPositive prompt:\n{$positive}\n\nSource Scorsese deliverable:\n{$output}";
  return gdb_insert('scorsese_comfy_jobs',[
    'job_uid'=>gdb_uid('comfy'),
    'source_completion_id'=>$completion['id']??null,
    'source_commission_id'=>$completion['commission_id']??null,
    'title'=>$title,
    'prompt'=>$prompt,
    'workflow_json'=>$workflow?json_encode($workflow,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,
    'status'=>'queued',
    'priority'=>95,
    'progress'=>0,
    'media_type'=>'video',
    'metadata'=>gdb_json(['source'=>'v75_5_scorsese_comfy_bridge','completion_id'=>$completion['id']??null,'workflow_template'=>scb_workflow_path(),'workflow_injected'=>(bool)$workflow])
  ]);
}
function scb_seed_from_scorsese_completions($limit=50){
  if(!gdb_enabled()||!scb_table('goliath_worker_completions')||!scb_table('scorsese_comfy_jobs')) return 0;
  $rows=gdb_all("SELECT * FROM goliath_worker_completions wc WHERE LOWER(wc.executive)='scorsese' AND NOT EXISTS (SELECT 1 FROM scorsese_comfy_jobs j WHERE j.source_completion_id=wc.id) ORDER BY wc.created_at DESC LIMIT ".(int)$limit);
  $made=0;
  foreach($rows as $r){ if(scb_insert_job($r)) $made++; }
  return $made;
}
function scb_pull_next(){
  if(!gdb_enabled()||!scb_table('scorsese_comfy_jobs')) return null;
  $job=gdb_one("SELECT * FROM scorsese_comfy_jobs WHERE status IN ('queued','retry') ORDER BY priority DESC, created_at ASC LIMIT 1");
  if(!$job) return null;
  // If old job has no workflow_json, inject it now.
  if(empty($job['workflow_json'])){
    $workflow=scb_build_wan_workflow($job['prompt']??$job['title']??'Scorsese render', $job['id']??null, $job['title']??'scorsese');
    if($workflow){
      $job['workflow_json']=json_encode($workflow,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      gdb_update('scorsese_comfy_jobs',['workflow_json'=>$job['workflow_json'],'metadata'=>gdb_json(['source'=>'v75_5_late_workflow_injection'])],'id=:id',['id'=>(int)$job['id']]);
    }
  }
  gdb_update('scorsese_comfy_jobs',['status'=>'working','progress'=>10,'claimed_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>(int)$job['id']]);
  $job['status']='working'; $job['progress']=10;
  return $job;
}
function scb_complete($in){
  $id=(int)($in['id']??($in['job_id']??0));
  if(!$id) scb_out(['success'=>false,'error'=>'missing_job_id','received_keys'=>array_keys($in)],400);
  $status=$in['status']??'complete';
  $outputUrl=$in['output_url']??($in['file_url']??null);
  $outputPath=$in['output_path']??($in['file_path']??null);
  $thumb=$in['thumbnail_url']??null;
  $error=$in['error_message']??($in['error']??null);
  $progress=in_array($status,['complete','completed'])?100:(int)($in['progress']??50);
  $job=gdb_one('SELECT * FROM scorsese_comfy_jobs WHERE id=? LIMIT 1',[$id]);
  if(!$job) scb_out(['success'=>false,'error'=>'job_not_found'],404);
  gdb_update('scorsese_comfy_jobs',[
    'status'=>$status,
    'progress'=>$progress,
    'output_url'=>$outputUrl,
    'output_path'=>$outputPath,
    'thumbnail_url'=>$thumb,
    'error_message'=>$error,
    'completed_at'=>in_array($status,['complete','completed'])?gdb_now():null,
    'updated_at'=>gdb_now(),
    'metadata'=>gdb_json(['source'=>'v75_5_scorsese_comfy_update','worker_payload'=>$in])
  ],'id=:id',['id'=>$id]);
  $reviewId=null;
  if(in_array($status,['complete','completed']) && scb_table('goliath_review_queue')){
    try{$reviewId=gdb_insert('goliath_review_queue',[
      'review_uid'=>gdb_uid('review'),
      'executive'=>'Scorsese',
      'source_type'=>'scorsese_comfy_media',
      'source_id'=>(string)$id,
      'title'=>$job['title']??'Scorsese Media Render',
      'summary'=>'ComfyUI media render completed. '.($outputUrl?:$outputPath?:'Open Scorsese Media Center to review.'),
      'review_status'=>'ready',
      'recommended_action'=>'Preview the media, approve, request revisions, or send to publishing.',
      'review_url'=>'/dashboard/scorsese-media-center.php?job_id='.$id,
      'metadata'=>gdb_json(['comfy_job_id'=>$id,'output_url'=>$outputUrl,'output_path'=>$outputPath,'thumbnail_url'=>$thumb])
    ]);}catch(Throwable $e){}
  }
  if(function_exists('gal_action')) @gal_action('Scorsese','comfy_media_completed',$job['title']??'Scorsese media render','ComfyUI returned a media asset for review.','complete',100,['comfy_job_id'=>$id,'output_url'=>$outputUrl,'output_path'=>$outputPath]);
  if(function_exists('gal_notify')) @gal_notify('Scorsese','Media ready for review',$job['title']??'Scorsese media render','high','/dashboard/scorsese-media-center.php?job_id='.$id,['comfy_job_id'=>$id]);
  return ['success'=>true,'job_id'=>$id,'review_id'=>$reviewId,'message'=>'Scorsese ComfyUI media attached and queued for review.'];
}
