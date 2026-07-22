<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-local-store.php';
header('Content-Type: application/json; charset=utf-8');
function out($a,$c=200){http_response_code($c);echo json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;}
$key=$_GET['key']??($_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($expected && !hash_equals($expected,$key)) out(['success'=>false,'error'=>'Unauthorized'],403);
$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=$_POST;
$executive=$in['executive']??$in['agent']??$in['assigned_agent']??'Goliath';
$title=$in['title']??($executive.' executive work');
$id=$in['id']??$in['task_id']??$in['related_id']??('task_'.strtolower(preg_replace('/\W+/','_',$executive)).'_'.substr(md5($title),0,10));
$status=$in['status']??'working';
$progress=max(0,min(100,(int)($in['progress']??0)));
$task=[
  'id'=>$id,
  'assigned_agent'=>$executive,
  'agent'=>$executive,
  'title'=>$title,
  'task_type'=>$in['related_type']??$in['task_type']??'executive_progress',
  'status'=>$status,
  'workflow_state'=>$in['workflow_state']??$status,
  'progress'=>$progress,
  'current_phase'=>$in['current_phase']??$in['phase']??ucfirst(str_replace('_',' ',$status)),
  'next_milestone'=>$in['next_milestone']??'',
  'blocking_issue'=>$in['blocking_issue']??$in['blocked']??'',
  'ready_url'=>$in['ready_url']??'',
  'metadata'=>[
    'agent'=>$executive,
    'asset_count'=>$in['asset_count']??0,
    'handoff_to'=>$in['handoff_to']??'',
    'related_type'=>$in['related_type']??'',
    'related_id'=>$in['related_id']??''
  ]
];
$task=goliath_upsert_row('local_ai_tasks',$id,$task);
goliath_append_row('goliath_events',[
  'department'=>$executive,
  'event_type'=>'executive_progress',
  'title'=>$title,
  'detail'=>$task['current_phase'],
  'status'=>$status,
  'progress'=>$progress,
  'confidence'=>94,
  'metadata'=>['agent'=>$executive,'task_id'=>$id]
]);
out(['success'=>true,'stored'=>'local_json','task'=>$task]);
