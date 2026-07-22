<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-v55-core.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

function g55_get($ep){$r=g55_req('GET',$ep,null,['Prefer: return=representation']);return $r['ok']&&is_array($r['data'])?$r['data']:[];}
$tasks = g55_get('local_ai_tasks?select=*&status=in.(completed,done)&order=updated_at.desc&limit=25');
$made=[];$skipped=[];
foreach($tasks as $t){
  $md=$t['metadata']??[]; if(is_string($md)) $md=json_decode($md,true) ?: [];
  if(($md['version']??'') !== '55.0'){ $skipped[]=['task'=>$t['id']??null,'reason'=>'not_v55']; continue; }
  $taskId=$t['id']??null;
  if(!$taskId){continue;}
  $exists=g55_get('goliath_deliverables?select=id&metadata->>source_task_id=eq.'.rawurlencode($taskId).'&limit=1');
  // The above metadata filter may not work on older PostgREST; also check source_task if previous schema used it.
  $exists2=g55_get('goliath_deliverables?select=id&source_task_id=eq.'.rawurlencode($taskId).'&limit=1');
  if(count($exists)||count($exists2)){ $skipped[]=['task'=>$taskId,'reason'=>'already_has_deliverable']; continue; }
  $agent=$md['agent']??'Goliath'; $commissionId=$md['commission_id']??null;
  $result=$t['result']??null; $output='';
  if(is_array($result)){
    $output = $result['output'] ?? $result['text'] ?? $result['response'] ?? json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  } else { $output=(string)($result ?: ($t['prompt']??'')); }
  $json=g55_first_json($output) ?: [];
  $title=$json['title'] ?? ($md['title'] ?? ($agent.' Work Product'));
  $summary=$json['summary'] ?? 'Prepared by '.$agent.' for Founder review.';
  $impact=(int)($json['business_impact'] ?? 3);
  $next=$json['next_agent'] ?? ($md['next_agent'] ?? g55_contract($agent)['next']);
  $nextAction=$json['next_action'] ?? 'Review and continue handoff.';
  $r=g55_create_deliverable($agent,$commissionId,$title,$summary,$output,$json,'ready','normal',$impact,$next,$nextAction);
  $made[]=['task'=>$taskId,'agent'=>$agent,'ok'=>$r['ok'],'deliverable'=>$r['data']];
}
echo json_encode(['success'=>true,'version'=>'55.0','created_count'=>count(array_filter($made,fn($x)=>$x['ok'])),'created'=>$made,'skipped'=>$skipped],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
