<?php
/**
 * V80.1 — Scorsese ComfyUI Test Job
 * Queues one tiny/normal test job so the local Comfy worker can prove:
 * Hostinger -> local worker -> ComfyUI -> Hostinger upload/register.
 */
require_once __DIR__.'/scorsese-comfy-bridge.php';

if(!scb_key_ok()) scb_out(['ok'=>false,'success'=>false,'error'=>'bad_key'],403);

$health = scb_health();
if(!$health['configured']) scb_out(['ok'=>false,'success'=>false,'error'=>'db_not_configured','health'=>$health],500);
if(empty($health['tables']['scorsese_comfy_jobs'])) scb_out(['ok'=>false,'success'=>false,'error'=>'missing_scorsese_comfy_jobs_table','health'=>$health],500);
if(!scb_template_loaded()) scb_out(['ok'=>false,'success'=>false,'error'=>'missing_workflow_template','expected'=>scb_workflow_path(),'health'=>$health],500);

$title = trim($_GET['title'] ?? $_POST['title'] ?? 'Scorsese V80.1 ComfyUI Connection Test');
$prompt = trim($_GET['prompt'] ?? $_POST['prompt'] ?? 'A polished cinematic 5 second luxury Connecticut real estate marketing shot, golden hour, premium commercial lighting, elegant camera move, no text, no distorted people, ready for social media.');

try{
  $id = scb_insert_job([
    'id'=>null,
    'commission_id'=>null,
    'title'=>$title,
    'output'=>$prompt
  ]);

  scb_out([
    'ok'=>true,
    'success'=>true,
    'version'=>'V80.1 Scorsese ComfyUI Test Job',
    'job_id'=>$id,
    'title'=>$title,
    'message'=>'Test job queued. Run goliath-comfy-direct-worker-v80.ps1 locally. Then check scorsese-comfy-status.php and Scorsese Video Center.',
    'next'=>[
      'local_worker'=>'powershell -ExecutionPolicy Bypass -File "F:\\GOliathOmni\\goliath-comfy-direct-worker-v80.ps1"',
      'status'=>'/lead-engine/scorsese-comfy-status.php?key=...',
      'video_center'=>'/dashboard/scorsese-media-center.php'
    ],
    'health'=>scb_health(),
    'time'=>date('c')
  ]);
}catch(Throwable $e){
  scb_out(['ok'=>false,'success'=>false,'error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'health'=>$health],500);
}
?>