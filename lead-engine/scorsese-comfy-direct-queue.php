<?php
/**
 * V80.2 — Scorsese Direct Comfy Queue
 * Creates a real ComfyUI video job directly from the Media Center prompt.
 */
require_once __DIR__.'/scorsese-comfy-bridge.php';

$raw = json_decode(file_get_contents('php://input'), true);
if(!is_array($raw)) $raw = array_merge($_POST,$_GET);

$key = $raw['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)) scb_out(['ok'=>false,'success'=>false,'error'=>'bad_key'],403);

$prompt = trim((string)($raw['prompt'] ?? ''));
if($prompt==='') scb_out(['ok'=>false,'success'=>false,'error'=>'prompt_required'],400);

$title = trim((string)($raw['title'] ?? 'Scorsese Prompt Video'));
$aspect = trim((string)($raw['aspect_ratio'] ?? '9:16'));
$category = trim((string)($raw['category'] ?? 'Discover Connecticut'));
$style = trim((string)($raw['style'] ?? 'Cinematic luxury'));
$duration = trim((string)($raw['duration'] ?? '5 seconds'));
$platform = trim((string)($raw['platform'] ?? 'social'));

$aspectNote = [
  '9:16' => 'vertical 9:16 mobile-first social reel composition',
  '16:9' => 'horizontal 16:9 YouTube/cinematic composition',
  '1:1' => 'square 1:1 social feed composition'
][$aspect] ?? $aspect;

$categoryNote = [
  'discover_ct' => 'Discover Connecticut hyper-local community content, authentic Fairfield County energy, local charm',
  'real_estate' => 'Fairfield County real estate marketing, premium property/lifestyle visuals, luxury seller attraction',
  'house_detective' => 'cinematic noir House Detective real estate atmosphere, mystery, premium but playful',
  'beatseat' => 'BeatSeat music invention content, performance energy, one-person-band wow factor',
  'legacysaved' => 'LegacySaved emotional family memory storytelling, warm, respectful, cinematic',
  'general' => 'Goliath Omni branded premium content'
][$category] ?? $category;

$enhanced = "SCORSESE DIRECT VIDEO REQUEST\n".
"Title: {$title}\n".
"Category: {$categoryNote}\n".
"Aspect Ratio: {$aspectNote}\n".
"Style: {$style}\n".
"Duration target: {$duration}\n".
"Platform: {$platform}\n\n".
"Creative prompt:\n{$prompt}\n\n".
"Production standards: premium commercial finish, strong first-frame hook, no random text, no watermark, no distorted people, no fake logos, cinematic camera movement, review-ready output for Mark.";

try{
  $id = scb_insert_job([
    'id'=>null,
    'commission_id'=>null,
    'title'=>$title,
    'output'=>$enhanced,
    'result'=>$enhanced
  ]);

  // Add metadata if the columns exist.
  if($id && scb_table('scorsese_comfy_jobs')){
    $updates = [];
    if(scb_col('scorsese_comfy_jobs','metadata')){
      $updates['metadata'] = gdb_json([
        'source'=>'v80_2_direct_media_center_prompt',
        'aspect_ratio'=>$aspect,
        'category'=>$category,
        'style'=>$style,
        'duration'=>$duration,
        'platform'=>$platform,
        'original_prompt'=>$prompt
      ]);
    }
    if(scb_col('scorsese_comfy_jobs','priority')) $updates['priority']=180;
    if($updates) gdb_update('scorsese_comfy_jobs',$updates,'id=:id',['id'=>(int)$id]);
  }

  scb_out([
    'ok'=>true,
    'success'=>true,
    'version'=>'V80.2 Scorsese Direct Comfy Queue',
    'job_id'=>$id,
    'title'=>$title,
    'message'=>'Video job queued. The local Comfy worker will pull it and post the finished video back to Hostinger.',
    'next'=>[
      'worker'=>'powershell -ExecutionPolicy Bypass -File "F:\\GOliathOmni\\goliath-comfy-direct-worker-v80.ps1"',
      'status'=>'/lead-engine/scorsese-comfy-status.php?key=...',
      'media_center'=>'/dashboard/scorsese-media-center.php'
    ],
    'time'=>date('c')
  ]);
}catch(Throwable $e){
  scb_out(['ok'=>false,'success'=>false,'error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],500);
}
?>