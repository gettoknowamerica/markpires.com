<?php
/**
 * V80.1 — Scorsese ComfyUI Status
 * Compatibility endpoint for:
 * /lead-engine/scorsese-comfy-status.php?key=...
 */
require_once __DIR__.'/scorsese-comfy-bridge.php';

if(!scb_key_ok()) scb_out(['ok'=>false,'success'=>false,'error'=>'bad_key'],403);

$health = scb_health();
$recent = [];
$queued = [];
$latest_complete = [];

try {
  if (scb_table('scorsese_comfy_jobs')) {
    $recent = gdb_all("SELECT id,title,status,progress,media_type,output_url,output_path,thumbnail_url,error_message,created_at,updated_at,completed_at
      FROM scorsese_comfy_jobs
      ORDER BY updated_at DESC, id DESC
      LIMIT 25");
    $queued = gdb_all("SELECT id,title,status,progress,media_type,created_at,updated_at
      FROM scorsese_comfy_jobs
      WHERE status IN ('queued','retry','working','rendering')
      ORDER BY priority DESC, created_at ASC
      LIMIT 25");
    $latest_complete = gdb_all("SELECT id,title,status,progress,media_type,output_url,output_path,thumbnail_url,created_at,updated_at,completed_at
      FROM scorsese_comfy_jobs
      WHERE status IN ('complete','completed')
      ORDER BY completed_at DESC, updated_at DESC, id DESC
      LIMIT 10");
  }
} catch(Throwable $e) {
  scb_out(['ok'=>false,'success'=>false,'error'=>$e->getMessage(),'health'=>$health],500);
}

scb_out([
  'ok'=>true,
  'success'=>true,
  'version'=>'V80.1 Scorsese ComfyUI Status',
  'health'=>$health,
  'queued_or_working'=>$queued,
  'latest_complete'=>$latest_complete,
  'recent'=>$recent,
  'endpoints'=>[
    'pull'=>'/lead-engine/scorsese-comfy-pull.php?key=...',
    'queue'=>'/lead-engine/scorsese-comfy-queue.php?key=...',
    'test_job'=>'/lead-engine/scorsese-comfy-test-job.php?key=...',
    'upload'=>'/lead-engine/scorsese-comfy-upload.php',
    'register'=>'/lead-engine/scorsese-comfy-register.php',
    'update'=>'/lead-engine/scorsese-comfy-update.php'
  ],
  'time'=>date('c')
]);
?>