<?php
/**
 * Goliath Worker v53
 * Picks up queued agent_jobs, commissions the correct agent skill, and queues local AI tasks.
 * It no longer pretends the work is complete before the local AI result comes back.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-v53-lib.php';
require_once __DIR__ . '/goliath-skills.php';

if (!g53_key_ok()) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function gw_activity($agent,$message,$severity='info',$payload=[]){
  return g53_req('POST','agent_activity', [[
    'agent'=>$agent,
    'message'=>$message,
    'severity'=>$severity,
    'payload'=>$payload
  ]]);
}
function gw_update_job($id,$fields){
  $fields['updated_at'] = gmdate('c');
  return g53_req('PATCH','agent_jobs?id=eq.'.rawurlencode($id),$fields);
}
function gw_recent_task_exists($agent,$jobType,$minutes=10){
  $since = gmdate('c', time() - ($minutes*60));
  $ep = 'local_ai_tasks?select=id&status=in.(queued,working)&metadata->>agent=eq.'.rawurlencode($agent).'&metadata->>job_type=eq.'.rawurlencode($jobType).'&created_at=gte.'.rawurlencode($since).'&limit=1';
  $r = g53_req('GET',$ep);
  return $r['ok'] && is_array($r['data']) && count($r['data']) > 0;
}
function gw_queue_task($agent,$job,$prompt){
  $jobType = $job['job_type'] ?? 'daily_mission';
  $priorityMap = ['critical'=>100,'high'=>80,'normal'=>50,'low'=>20];
  $priorityText = $job['priority'] ?? 'normal';
  $payload = [
    'task_type'=>$jobType,
    'model'=>'llama3.1:8b',
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priorityMap[$priorityText] ?? 50,
    'metadata'=>[
      'agent'=>$agent,
      'agent_job_id'=>$job['id'] ?? null,
      'mission_id'=>$job['mission_id'] ?? null,
      'job_type'=>$jobType,
      'priority'=>$priorityText,
      'source'=>'goliath-worker-v53',
      'commissioned'=>true
    ]
  ];
  return g53_req('POST','local_ai_tasks',$payload);
}

$limit = max(1, min(10, (int)($_GET['limit'] ?? 5)));
$jobsRes = g53_req('GET','agent_jobs?select=*&status=eq.queued&order=created_at.asc&limit='.$limit);
if(!$jobsRes['ok']){
  echo json_encode(['success'=>false,'stage'=>'load_jobs','error'=>$jobsRes], JSON_PRETTY_PRINT);
  exit;
}
$jobs = is_array($jobsRes['data']) ? $jobsRes['data'] : [];
$processed = [];

foreach($jobs as $job){
  $id = $job['id'] ?? null;
  $agent = $job['agent'] ?? 'Goliath';
  $jobType = $job['job_type'] ?? 'daily_mission';
  if(!$id) continue;

  if(gw_recent_task_exists($agent,$jobType,8)){
    gw_update_job($id,[
      'status'=>'waiting',
      'result'=>['message'=>'Similar queued/working local AI task already exists. Worker delayed duplicate.','agent'=>$agent,'job_type'=>$jobType]
    ]);
    $processed[] = ['job_id'=>$id,'agent'=>$agent,'status'=>'waiting_duplicate_guard'];
    continue;
  }

  gw_update_job($id,['status'=>'working','started_at'=>gmdate('c')]);
  $prompt = g53_agent_prompt($agent,$job);
  $task = gw_queue_task($agent,$job,$prompt);

  if($task['ok']){
    $taskId = is_array($task['data']) && isset($task['data'][0]['id']) ? $task['data'][0]['id'] : null;
    gw_update_job($id,[
      'status'=>'ai_queued',
      'result'=>['message'=>'Queued to local AI worker. Waiting for real deliverable.','task_id'=>$taskId,'agent'=>$agent,'job_type'=>$jobType]
    ]);
    gw_activity($agent,'Commissioned real work: '.$jobType,'info',['job_id'=>$id,'task_id'=>$taskId]);
    g53_event($agent,$agent.' commissioned','Real work queued: '.$jobType,'/dashboard/goliath-deliverables.php?agent='.rawurlencode($agent),['job_id'=>$id,'task_id'=>$taskId,'job_type'=>$jobType],92,0);
    $processed[] = ['job_id'=>$id,'agent'=>$agent,'status'=>'ai_queued','task_id'=>$taskId];
  } else {
    gw_update_job($id,['status'=>'failed','result'=>['message'=>'Failed to queue local AI task','error'=>$task]]);
    $processed[] = ['job_id'=>$id,'agent'=>$agent,'status'=>'failed_queue','error'=>$task];
  }
}

echo json_encode([
  'success'=>true,
  'worker'=>'Goliath Worker v53 Commissioning',
  'processed_count'=>count($processed),
  'processed'=>$processed,
  'next'=>'Keep the local desktop worker running. Completed tasks will create deliverables through local-ai-task-update.php.'
], JSON_PRETTY_PRINT);
