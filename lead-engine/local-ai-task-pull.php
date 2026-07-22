<?php
/**
 * Goliath V80 — Local AI Task Pull
 * Local tasks first, old V75 production commission loops ignored.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v77-1-knowledge-loader.php')) require_once __DIR__.'/goliath-v77-1-knowledge-loader.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

function v80p_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80p_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80p_json($v){if(is_string($v)){$j=json_decode($v,true);return is_array($j)?$j:[];}return is_array($v)?$v:[];}
function v80p_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(v80p_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
function v80p_prompt($exec,$title,$prompt,$meta){
  if(function_exists('gv771_prompt')) return gv771_prompt($exec,$title,$prompt,$meta);
  return $prompt;
}

if(!gdb_enabled()){echo json_encode(['success'=>false,'error'=>'db_not_enabled','task'=>null],JSON_PRETTY_PRINT);exit;}

if(v80p_table('local_ai_tasks')){
  $task=gdb_one("SELECT * FROM local_ai_tasks
    WHERE status IN ('queued','working')
    ORDER BY
      CASE WHEN task_type IN ('scout_internal_crm_contact_research','v78_mission_assignment','v77_1_commission','v76_priority_mission') THEN 0 ELSE 1 END,
      priority DESC, updated_at ASC, id ASC
    LIMIT 1");
  if($task){
    $exec=$task['agent']??'Goliath';
    $title=$task['title']??($task['task_type']??'Goliath task');
    $metadata=v80p_json($task['metadata']??[]);
    $prompt=$task['prompt']??$title;

    $contract="\n\nV80 ASSET CONTRACT — DO NOT RETURN AN EXECUTIVE SUMMARY.\n".
      "Create a usable business asset. Start output exactly with these fields:\n".
      "ASSET_TYPE:\nEXECUTIVE:\nBUSINESS_GOAL:\nTARGET_AUDIENCE:\nACTIONABLE_ASSET:\nEVIDENCE:\nCLICKABLE_OUTPUTS:\nQUALITY_SCORE:\nBUSINESS_IMPACT_SCORE:\nHANDOFFS:\nNEXT_ACTION:\n\n".
      "Rules: no fake facts, no fake contacts, no fake stats, no generic strategy. If a real asset cannot be created, output NEEDS_DATA_REPORT with the exact missing tool/data/source.";

    $task['prompt']=v80p_prompt($exec,$title,$prompt.$contract,$metadata);
    $task['status']='working';
    v80p_update('local_ai_tasks',(int)$task['id'],['status'=>'working','progress'=>max(10,(int)($task['progress']??0)),'prompt'=>$task['prompt'],'updated_at'=>gdb_now()]);
    echo json_encode(['success'=>true,'task'=>$task,'source'=>'local_ai_tasks_first','intelligence'=>'V80 Asset Contract'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
  }
}

if(v80p_table('executive_commissions')){
  $commission=gdb_one("SELECT * FROM executive_commissions
    WHERE status IN ('queued','claimed','in_progress','processing','working')
      AND COALESCE(progress,0)<100
      AND title NOT LIKE 'Production Mission:%'
    ORDER BY priority DESC, updated_at ASC, id ASC
    LIMIT 1");
  if($commission){
    $cid=(int)$commission['id'];
    $exec=$commission['executive_key']??($commission['executive']??'Goliath');
    $title=$commission['title']??($commission['current_task']??'Goliath commission');
    $metadata=v80p_json($commission['metadata']??[]);
    $metadata['agent']=$exec; $metadata['commission_id']=$cid; $metadata['title']=$title; $metadata['source']='executive_commissions';
    $prompt=v80p_prompt($exec,$title,$commission['prompt']??($commission['description']??($commission['current_task']??$title)),$metadata);
    v80p_update('executive_commissions',$cid,['status'=>'working','progress'=>max(22,(int)($commission['progress']??0)),'current_step'=>'Pulled by V80 Asset Contract Worker','updated_at'=>gdb_now()]);
    echo json_encode(['success'=>true,'task'=>['id'=>$cid,'commission_id'=>$cid,'agent'=>ucfirst(strtolower($exec)),'title'=>$title,'task_type'=>$commission['mission_type']??'goliath_commission','model'=>'goliath-local-worker','prompt'=>$prompt,'status'=>'working','priority'=>(int)($commission['priority']??80),'metadata'=>$metadata],'source'=>'executive_commissions_fallback','intelligence'=>'V80 Asset Contract'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
  }
}

echo json_encode(['success'=>true,'task'=>null,'message'=>'No V80 tasks waiting.'],JSON_PRETTY_PRINT);
?>