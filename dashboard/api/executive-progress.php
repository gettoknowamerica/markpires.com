<?php
require_once __DIR__ . '/../../lead-engine/config.php';
require_once __DIR__ . '/../../lead-engine/goliath-local-store.php';
header('Content-Type: application/json; charset=utf-8');
$executive=$_GET['executive']??$_GET['agent']??'Goliath';
$tasks=array_values(array_filter(goliath_local_select('local_ai_tasks',500), function($t) use($executive){
  $m=$t['metadata']??[]; if(is_string($m)) $m=json_decode($m,true)?:[];
  $a=$t['assigned_agent']??$t['executive']??$t['agent']??$t['department']??($m['agent']??'');
  return strcasecmp($a,$executive)===0;
}));
$events=array_values(array_filter(goliath_local_select('goliath_events',500), function($e) use($executive){
  $m=$e['metadata']??[]; if(is_string($m)) $m=json_decode($m,true)?:[];
  $a=$e['department']??($m['agent']??'');
  return strcasecmp($a,$executive)===0;
}));
$latest=$tasks[0]??null;
if(!$latest){$latest=['title'=>$executive.' is standing by','status'=>'available','progress'=>0,'current_phase'=>'No active commission currently reporting progress.','updated_at'=>gmdate('c')];}
echo json_encode(['success'=>true,'executive'=>$executive,'latest'=>[
  'title'=>$latest['title']??'Executive work',
  'status'=>$latest['status']??'working',
  'progress'=>(int)($latest['progress']??0),
  'phase'=>$latest['current_phase']??$latest['phase']??'',
  'next'=>$latest['next_milestone']??'',
  'blocked'=>$latest['blocking_issue']??'',
  'ready_url'=>$latest['ready_url']??'',
  'updated_at'=>$latest['updated_at']??$latest['created_at']??'',
  'asset_count'=>($latest['metadata']['asset_count']??0),
  'handoff_to'=>($latest['metadata']['handoff_to']??'')
], 'active'=>$tasks, 'messages'=>$events], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
