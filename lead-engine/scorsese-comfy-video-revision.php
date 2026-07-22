<?php
/**
 * V80.3 — Scorsese Video Revision Queue
 * Creates a new Comfy job from a specific existing video + revision note.
 */
require_once __DIR__.'/scorsese-comfy-bridge.php';

$raw = json_decode(file_get_contents('php://input'), true);
if(!is_array($raw)) $raw = array_merge($_POST,$_GET);

$key = $raw['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)) scb_out(['ok'=>false,'success'=>false,'error'=>'bad_key'],403);

$jobId = (int)($raw['job_id'] ?? 0);
$revision = trim((string)($raw['revision_prompt'] ?? ''));
if(!$jobId || $revision==='') scb_out(['ok'=>false,'success'=>false,'error'=>'job_id_and_revision_prompt_required'],400);

$job = null;
try{ $job = gdb_one("SELECT * FROM scorsese_comfy_jobs WHERE id=? LIMIT 1",[$jobId]); }catch(Throwable $e){}
if(!$job) scb_out(['ok'=>false,'success'=>false,'error'=>'original_job_not_found'],404);

$title = 'Revision of Job #'.$jobId.': '.($job['title'] ?? 'Scorsese Video');
$origPrompt = $job['prompt'] ?? ($job['output'] ?? ($job['title'] ?? ''));
$origUrl = $job['output_url'] ?? '';

$enhanced = "SCORSESE VIDEO REVISION REQUEST\n".
"Original Job: #{$jobId}\n".
"Original Title: ".($job['title'] ?? '')."\n".
"Original Video URL: {$origUrl}\n\n".
"Original creative direction:\n{$origPrompt}\n\n".
"Mark's revision instructions:\n{$revision}\n\n".
"Scorsese quality upgrade rules:\n".
"- Make it look less AI and more like premium commercial film.\n".
"- Avoid fake writing, garbled text, warped architecture, distorted grass, bad windows, impossible reflections, or unstable camera artifacts.\n".
"- For real estate: prioritize believable luxury architecture, clean waterfront/lawn details, golden-hour lighting, smooth cinematic camera movement.\n".
"- If Mark asks for longer duration, create the longest high-quality output the current workflow supports; if limited by WAN workflow, note the limitation and produce the best available clip.\n".
"- Keep final output review-ready and upload back to Hostinger.\n";

try{
  $newId = scb_insert_job([
    'id'=>null,
    'commission_id'=>null,
    'title'=>$title,
    'output'=>$enhanced,
    'result'=>$enhanced
  ]);
  scb_out([
    'ok'=>true,
    'success'=>true,
    'version'=>'V80.3 Scorsese Video Revision',
    'original_job_id'=>$jobId,
    'new_job_id'=>$newId,
    'message'=>'Revision queued as a new Comfy job.',
    'time'=>date('c')
  ]);
}catch(Throwable $e){
  scb_out(['ok'=>false,'success'=>false,'error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],500);
}
?>