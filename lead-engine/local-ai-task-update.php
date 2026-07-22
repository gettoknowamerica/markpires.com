<?php
/**
 * Goliath V80 — Local AI Task Update / Asset Contract Saver
 * POST JSON recommended. GET still accepted for compatibility.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');

function v80_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function v80_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function v80_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function v80_insert($table,$row){
  $safe=[]; foreach($row as $k=>$v){ if(v80_col($table,$k)) $safe[$k]=$v; }
  return $safe?gdb_insert($table,$safe):null;
}
function v80_update($table,$id,$row){
  $safe=[]; foreach($row as $k=>$v){ if(v80_col($table,$k)) $safe[$k]=$v; }
  if($safe) gdb_update($table,$safe,'id=:id',['id'=>(int)$id]);
  return (bool)$safe;
}
function v80_input(){
  $raw=file_get_contents('php://input');
  $json=json_decode($raw,true);
  if(is_array($json)) return $json;
  if(!empty($_POST)) return $_POST;
  return $_GET;
}
function v80_out($a,$code=200){http_response_code($code);echo json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
function v80_asset_type($title,$output,$agent){
  $txt=strtolower($title.' '.$output.' '.$agent);
  if(str_contains($txt,'mp4')||str_contains($txt,'video')||str_contains($txt,'thumbnail')||str_contains($txt,'reel')) return 'video_package';
  if(str_contains($txt,'blog')||str_contains($txt,'article')||str_contains($txt,'landing page')||str_contains($txt,'html')) return 'publish_ready_blog';
  if(str_contains($txt,'phone')||str_contains($txt,'email')||str_contains($txt,'lead')||str_contains($txt,'crm')||str_contains($txt,'contact')) return 'lead_list';
  if(str_contains($txt,'outreach')||str_contains($txt,'speaking')||str_contains($txt,'sponsor')||str_contains($txt,'venue')||str_contains($txt,'podcast')) return 'outreach_email_campaign';
  if(str_contains($txt,'seo')||str_contains($txt,'schema')||str_contains($txt,'aeo')) return 'seo_schema_package';
  return 'asset_contract';
}
function v80_extract_contract($output){
  $contract=['asset_type'=>null,'business_goal'=>null,'actionable_asset'=>null,'clickable_outputs'=>[],'evidence'=>null,'next_action'=>null];
  foreach(preg_split('/\R/',$output) as $line){
    if(preg_match('/^\s*ASSET_TYPE\s*:\s*(.+)$/i',$line,$m)) $contract['asset_type']=trim($m[1]);
    if(preg_match('/^\s*BUSINESS_GOAL\s*:\s*(.+)$/i',$line,$m)) $contract['business_goal']=trim($m[1]);
    if(preg_match('/^\s*ACTIONABLE_ASSET\s*:\s*(.+)$/i',$line,$m)) $contract['actionable_asset']=trim($m[1]);
    if(preg_match('/^\s*EVIDENCE\s*:\s*(.+)$/i',$line,$m)) $contract['evidence']=trim($m[1]);
    if(preg_match('/^\s*CLICKABLE_OUTPUTS\s*:\s*(.+)$/i',$line,$m)) $contract['clickable_outputs'][]=trim($m[1]);
    if(preg_match('/^\s*NEXT_ACTION\s*:\s*(.+)$/i',$line,$m)) $contract['next_action']=trim($m[1]);
  }
  return $contract;
}

try{
  $in=v80_input();
  $key=$in['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
  if(!hash_equals($expected,(string)$key)) v80_out(['success'=>false,'error'=>'bad_key'],403);
  if(!gdb_enabled()) v80_out(['success'=>false,'error'=>'db_not_enabled'],500);

  $taskId=(int)($in['task_id']??($in['id']??($in['local_ai_task_id']??0)));
  $commissionId=(int)($in['commission_id']??0);
  $status=strtolower((string)($in['status']??'completed'));
  if(in_array($status,['complete','done','success'],true)) $status='completed';
  $progress=(int)($in['progress']??($status==='completed'?100:90));
  $agent=(string)($in['agent']??($in['executive']??'Goliath'));
  $title=(string)($in['title']??'Local worker asset');
  $val=$in['output']??($in['result']??'');
  $output=(is_array($val)||is_object($val))?json_encode($val,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):(string)$val;
  if(trim($output)==='') $output='Completed by local worker. No detailed output was provided.';

  $task=null;
  if($taskId && v80_table('local_ai_tasks')){
    $task=gdb_one("SELECT * FROM local_ai_tasks WHERE id=? LIMIT 1",[$taskId]);
    if($task){
      if(!$commissionId) $commissionId=(int)($task['commission_id']??0);
      $agent=$task['agent']??$agent;
      $title=$task['task_type']??$title;
      $meta=json_decode($task['metadata']??'[]',true);
      if(is_array($meta)){
        $title=$meta['title']??($meta['asset_type']??$title);
        $agent=$meta['agent']??$agent;
      }
    }
  }

  $commission=null;
  if($commissionId && v80_table('executive_commissions')){
    $commission=gdb_one("SELECT * FROM executive_commissions WHERE id=? LIMIT 1",[$commissionId]);
    if($commission){
      $agent=$commission['executive_key']??($commission['executive']??$agent);
      $title=$commission['title']??($commission['current_task']??$title);
    }
  }

  $execKey=strtolower(preg_replace('/[^a-z0-9_\-]+/','',(string)$agent));
  $execDisplay=ucfirst($execKey ?: 'goliath');
  $contract=v80_extract_contract($output);
  $dtype=$contract['asset_type'] ?: v80_asset_type($title,$output,$execDisplay);
  $summary=$contract['actionable_asset'] ?: mb_substr(strip_tags($output),0,1200);
  $evidence=$contract['evidence'] ?: 'Local worker completion. Needs Mark review unless sources are included.';
  $publicUrl='';
  if(!empty($contract['clickable_outputs'][0]) && preg_match('#^/#',$contract['clickable_outputs'][0])) $publicUrl=$contract['clickable_outputs'][0];

  if($taskId && v80_table('local_ai_tasks')){
    v80_update('local_ai_tasks',$taskId,[
      'status'=>$status,
      'progress'=>$progress,
      'result'=>v80_json(['output'=>$output,'contract'=>$contract,'agent'=>$execDisplay]),
      'updated_at'=>gdb_now(),
      'completed_at'=>$status==='completed'?gdb_now():null
    ]);
  }
  if($commissionId && v80_table('executive_commissions')){
    v80_update('executive_commissions',$commissionId,[
      'status'=>'complete',
      'progress'=>100,
      'progress_percent'=>100,
      'current_step'=>'Asset saved by V80 worker update',
      'result_summary'=>$summary,
      'completed_at'=>gdb_now(),
      'updated_at'=>gdb_now()
    ]);
  }

  $completionId=null;
  if(v80_table('goliath_worker_completions')){
    $completionId=v80_insert('goliath_worker_completions',[
      'completion_uid'=>v80_uid('wc'),
      'task_id'=>$taskId?:null,
      'commission_id'=>$commissionId?:null,
      'executive'=>$execDisplay,
      'title'=>$title,
      'output'=>$output,
      'parsed'=>v80_json($contract),
      'source'=>'v80_worker_update'
    ]);
  }

  $deliverableId=null;
  if(v80_table('goliath_deliverables')){
    $deliverableId=v80_insert('goliath_deliverables',[
      'deliverable_uid'=>v80_uid('del'),
      'commission_uid'=>$commissionId?('commission_'.$commissionId):null,
      'executive'=>$execDisplay,
      'executive_key'=>$execKey,
      'deliverable_type'=>$dtype,
      'title'=>$title,
      'status'=>'ready',
      'file_path'=>'',
      'public_url'=>$publicUrl,
      'summary'=>$summary,
      'metadata'=>v80_json(['contract'=>$contract,'task_id'=>$taskId,'commission_id'=>$commissionId,'completion_id'=>$completionId,'raw_output'=>$output]),
      'evidence_status'=>($contract['evidence']?'needs_review':'needs_review'),
      'evidence'=>$evidence,
      'output_summary'=>$summary,
      'output_url'=>$publicUrl,
      'output_path'=>'',
      'source_urls'=>v80_json($contract['clickable_outputs']),
      'source_record_ids'=>v80_json(['task_id'=>$taskId,'commission_id'=>$commissionId,'completion_id'=>$completionId]),
      'related_commission_id'=>$commissionId?:null,
      'related_task_id'=>$taskId?:null,
      'related_completion_id'=>$completionId?:null,
      'next_action'=>$contract['next_action'] ?: 'Preview, approve, revise, or schedule this asset.',
      'review_status'=>'ready',
      'priority'=>80
    ]);
  }

  $reviewId=null;
  if(v80_table('goliath_review_queue')){
    $reviewId=v80_insert('goliath_review_queue',[
      'review_uid'=>v80_uid('review'),
      'executive'=>$execDisplay,
      'source_type'=>'goliath_deliverable',
      'source_id'=>$deliverableId?:0,
      'title'=>$title,
      'summary'=>$summary,
      'review_status'=>'ready',
      'business_value_score'=>70,
      'recommended_action'=>'Preview this asset, approve it, or request a revision.',
      'review_url'=>$deliverableId?('/dashboard/goliath-deliverables.php?deliverable_id='.$deliverableId):'/dashboard/goliath-assets.php',
      'metadata'=>v80_json(['deliverable_id'=>$deliverableId,'completion_id'=>$completionId,'task_id'=>$taskId])
    ]);
  }

  v80_out([
    'success'=>true,
    'version'=>'V80 Asset Contract Update',
    'task_id'=>$taskId,
    'commission_id'=>$commissionId,
    'completion_id'=>$completionId,
    'deliverable_id'=>$deliverableId,
    'review_id'=>$reviewId,
    'asset_type'=>$dtype,
    'message'=>'Saved to worker completion, deliverable, and review queue without 500.'
  ]);
}catch(Throwable $e){
  v80_out(['success'=>false,'version'=>'V80 Asset Contract Update','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],500);
}
?>