<?php
require_once __DIR__.'/scorsese-comfy-bridge.php';

$key = $_POST['key'] ?? ($_GET['key'] ?? '');
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if (!hash_equals($expected, (string)$key)) scb_out(['success'=>false,'error'=>'bad_key'],403);

$jobId = (int)($_POST['job_id'] ?? ($_POST['id'] ?? ($_GET['job_id'] ?? 0)));
if (!$jobId) scb_out(['success'=>false,'error'=>'missing_job_id','received_post_keys'=>array_keys($_POST),'files'=>array_keys($_FILES)],400);
if (empty($_FILES['media']) || !is_uploaded_file($_FILES['media']['tmp_name'])) {
  scb_out(['success'=>false,'error'=>'missing_media_file','received_post_keys'=>array_keys($_POST),'files'=>array_keys($_FILES)],400);
}

$job = gdb_one('SELECT * FROM scorsese_comfy_jobs WHERE id=? LIMIT 1', [$jobId]);
if (!$job) scb_out(['success'=>false,'error'=>'job_not_found'],404);

$orig = $_FILES['media']['name'] ?? 'scorsese-media.bin';
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$allowed = ['mp4','webm','mov','png','jpg','jpeg','webp'];
if (!in_array($ext, $allowed, true)) scb_out(['success'=>false,'error'=>'unsupported_file_type','ext'=>$ext],400);

$publicDir = realpath(__DIR__.'/..');
$relDir = '/media-assets/scorsese/'.date('Y/m/d');
$destDir = $publicDir.$relDir;
if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
if (!is_dir($destDir) || !is_writable($destDir)) scb_out(['success'=>false,'error'=>'upload_dir_not_writable','dir'=>$destDir],500);

$safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($job['title'] ?? 'scorsese'));
$safeTitle = trim($safeTitle, '-');
if (!$safeTitle) $safeTitle = 'scorsese';
$filename = 'scorsese-job-'.$jobId.'-'.$safeTitle.'-'.time().'.'.$ext;
$dest = $destDir.'/'.$filename;

if (!move_uploaded_file($_FILES['media']['tmp_name'], $dest)) scb_out(['success'=>false,'error'=>'move_uploaded_file_failed'],500);
@chmod($dest, 0644);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'www.markpires.com';
$outputUrl = $scheme.'://'.$host.$relDir.'/'.$filename;
$outputPath = $_POST['output_path'] ?? ($_GET['output_path'] ?? '');

$thumb = preg_match('/\.(png|jpg|jpeg|webp)$/i', $filename) ? $outputUrl : $outputUrl;

gdb_update('scorsese_comfy_jobs', [
  'status'=>'complete',
  'progress'=>100,
  'output_url'=>$outputUrl,
  'output_path'=>$outputPath ?: $relDir.'/'.$filename,
  'thumbnail_url'=>$thumb,
  'error_message'=>null,
  'completed_at'=>gdb_now(),
  'updated_at'=>gdb_now(),
  'metadata'=>gdb_json(['source'=>'v75_5_2_hostinger_media_upload','original_name'=>$orig,'public_url'=>$outputUrl,'local_output_path'=>$outputPath])
], 'id=:id', ['id'=>$jobId]);

$reviewId = null;
if (scb_table('goliath_review_queue')) {
  try {
    $reviewId = gdb_insert('goliath_review_queue', [
      'review_uid'=>gdb_uid('review'),
      'executive'=>'Scorsese',
      'source_type'=>'scorsese_comfy_media_upload',
      'source_id'=>(string)$jobId,
      'title'=>$job['title'] ?? 'Scorsese Media Render',
      'summary'=>'Scorsese ComfyUI render uploaded to Hostinger and ready for review: '.$outputUrl,
      'review_status'=>'ready',
      'recommended_action'=>'Preview the uploaded media, approve it, request revisions, or send it to publishing.',
      'review_url'=>'/dashboard/scorsese-media-center.php?job_id='.$jobId,
      'metadata'=>gdb_json(['comfy_job_id'=>$jobId,'output_url'=>$outputUrl,'thumbnail_url'=>$thumb])
    ]);
  } catch (Throwable $e) {}
}
if (function_exists('gal_action')) @gal_action('Scorsese','media_uploaded_to_hostinger',$job['title'] ?? 'Scorsese media render','ComfyUI media uploaded to Hostinger.','complete',100,['comfy_job_id'=>$jobId,'output_url'=>$outputUrl]);
if (function_exists('gal_notify')) @gal_notify('Scorsese','Media uploaded for review',$job['title'] ?? 'Scorsese media render','high','/dashboard/scorsese-media-center.php?job_id='.$jobId,['comfy_job_id'=>$jobId,'output_url'=>$outputUrl]);

scb_out(['success'=>true,'job_id'=>$jobId,'output_url'=>$outputUrl,'thumbnail_url'=>$thumb,'review_id'=>$reviewId,'message'=>'Media uploaded to Hostinger and job completed.']);
