<?php
/**
 * Goliath V70.5 — Hostinger Local AI Task Create
 * Creates Hostinger local_ai_tasks and optionally executive_commissions.
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key']??$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

function glw_table($table){
  try{ $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]); return ((int)($r['c']??0))>0; }
  catch(Throwable $e){ return false; }
}
if(!gdb_enabled()){ echo json_encode(['success'=>false,'error'=>'Goliath MySQL not configured']); exit; }

$agent=$data['agent']??($data['executive']??'Goliath');
$title=$data['title']??($agent.' local AI task');
$prompt=$data['prompt']??'';
$type=$data['task_type']??'goliath_commission';
$priority=(int)($data['priority']??80);
$metadata=$data['metadata']??[];

$commissionId=null;
if(glw_table('executive_commissions') && !empty($data['create_commission'])){
  $commissionId=gdb_insert('executive_commissions',[
    'commission_uid'=>gdb_uid('com'),
    'executive_key'=>strtolower($agent),
    'title'=>$title,
    'commission_type'=>$type,
    'status'=>'queued',
    'priority'=>$priority,
    'progress'=>0,
    'current_task'=>$title,
    'metadata'=>gdb_json($metadata)
  ]);
}

$taskId=null;
if(glw_table('local_ai_tasks')){
  $taskId=gdb_insert('local_ai_tasks',[
    'task_uid'=>gdb_uid('lat'),
    'commission_id'=>$commissionId,
    'agent'=>ucfirst(strtolower($agent)),
    'task_type'=>$type,
    'model'=>$data['model']??'goliath-local-worker',
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priority,
    'metadata'=>gdb_json($metadata)
  ]);
}
echo json_encode(['success'=>true,'id'=>$taskId,'commission_id'=>$commissionId,'message'=>'Queued in Hostinger Knowledge Vault.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>