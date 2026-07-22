<?php
/**
 * V100 Scorsese Force Push
 * Ensures Scorsese requests sitting in queues become real scorsese_comfy_jobs for the local Comfy worker.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 if(file_exists(__DIR__.'/scorsese-comfy-bridge.php')) require_once __DIR__.'/scorsese-comfy-bridge.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function sf100_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function sf100_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function sf100_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function sf100_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(sf100_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function sf100_job($packageId,$title,$instructions,$sourceTable,$sourceId,$priority=96){
   if(!sf100_table('scorsese_comfy_jobs')) return null;
   $exists=null;
   if($packageId) $exists=gdb_one("SELECT id FROM scorsese_comfy_jobs WHERE production_package_id=? AND status IN ('queued','working','rendering','complete','completed') LIMIT 1",[$packageId]);
   if($exists) return ['id'=>(int)$exists['id'],'created'=>false,'reason'=>'already_exists'];
   $positive="Cinematic premium real estate / brand video for Mark Pires. {$title}. {$instructions}. High-end commercial lighting, luxury Connecticut feel, emotional story, smooth camera movement, polished finish, no text artifacts, no distorted faces.";
   $workflow=null;
   if(function_exists('scb_build_wan_workflow')) $workflow=scb_build_wan_workflow($positive,null,$title);
   $id=sf100_insert('scorsese_comfy_jobs',[
    'job_uid'=>sf100_uid('comfy'),
    'source_completion_id'=>null,
    'source_commission_id'=>null,
    'production_package_id'=>$packageId?:null,
    'title'=>$title,
    'prompt'=>$positive,
    'workflow_json'=>$workflow?json_encode($workflow,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,
    'status'=>'queued',
    'priority'=>$priority,
    'progress'=>0,
    'media_type'=>'video',
    'metadata'=>json_encode(['source'=>'v100_force_push','source_table'=>$sourceTable,'source_id'=>$sourceId,'workflow_injected'=>(bool)$workflow],JSON_UNESCAPED_SLASHES),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);
   return ['id'=>$id,'created'=>true,'workflow_injected'=>(bool)$workflow];
 }

 $limit=max(1,min(100,(int)($_GET['limit']??50)));
 $created=[];$skipped=[];

 if(sf100_table('executive_collaboration_tasks')){
   $tasks=gdb_all("SELECT * FROM executive_collaboration_tasks WHERE to_executive='scorsese' AND status IN ('queued','working') ORDER BY priority DESC,created_at ASC LIMIT {$limit}")?:[];
   foreach($tasks as $t){
    $r=sf100_job((int)($t['package_id']??0),$t['title']?:'Scorsese Video',$t['instructions']??'Create video.',$t['source_table']??'executive_collaboration_tasks',(int)$t['id'],(int)($t['priority']??96));
    if($r && !empty($r['created'])) $created[]=['task_id'=>(int)$t['id'],'job_id'=>$r['id'],'title'=>$t['title'],'workflow_injected'=>$r['workflow_injected']??false];
    else $skipped[]=['task_id'=>(int)$t['id'],'reason'=>$r['reason']??'not_created'];
   }
 }

 // Legacy safety: Scorsese Studio Pro creates local_ai_tasks first. Convert those too.
 if(sf100_table('local_ai_tasks')){
   $rows=gdb_all("SELECT * FROM local_ai_tasks WHERE LOWER(agent)='scorsese' AND status IN ('queued','working','complete','completed') ORDER BY created_at DESC LIMIT {$limit}")?:[];
   foreach($rows as $t){
    $title=$t['title']??('Scorsese local task #'.$t['id']);
    $instructions=$t['prompt']??'Create video from Scorsese task.';
    $r=sf100_job(null,$title,$instructions,'local_ai_tasks',(int)$t['id'],90);
    if($r && !empty($r['created'])) $created[]=['local_ai_task_id'=>(int)$t['id'],'job_id'=>$r['id'],'title'=>$title,'workflow_injected'=>$r['workflow_injected']??false];
   }
 }

 // Existing bridge seed stays available too.
 $legacy_seed=null;
 if(file_exists(__DIR__.'/goliath-comfy-seed-from-scorsese.php') && function_exists('gc55_seed_from_scorsese')){
   try{$legacy_seed=gc55_seed_from_scorsese($limit);}catch(Throwable $e){$legacy_seed='error: '.$e->getMessage();}
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V100 Scorsese Force Push',
  'created_count'=>count($created),
  'created'=>$created,
  'skipped'=>$skipped,
  'legacy_seed'=>$legacy_seed,
  'next'=>'Run your local Scorsese worker PowerShell so it pulls /lead-engine/scorsese-comfy-pull.php and sends jobs to ComfyUI.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100 Scorsese Force Push','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>