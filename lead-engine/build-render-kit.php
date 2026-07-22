<?php
/**
 * V17.2 Jessica Render Kit
 * Upload: /public_html/lead-engine/build-render-kit.php
 *
 * Creates render instruction files and optional shell commands for FFmpeg rendering.
 * Does not require exec() to be enabled.
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function rk_sb($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function rk_rows($t,$q){$r=rk_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
function safe($s){return preg_replace('/[^a-zA-Z0-9._-]/','_',trim((string)$s));}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $queue=rk_rows('media_render_queue','select=*&render_status=in.(ready_for_render,queued)&order=created_at.desc&limit=100');
  $created=0; $updated=0; $errors=[];

  $renderDir=__DIR__.'/../uploads/media/renders';
  if(!is_dir($renderDir)) mkdir($renderDir,0755,true);

  foreach($queue as $r){
    $rid=$r['id'];
    $project=rk_rows('media_projects','select=*&id=eq.'.rawurlencode($r['media_project_id']).'&limit=1');
    $clip=[];
    if(!empty($r['media_clip_plan_id'])){
      $clip=rk_rows('media_clip_plans','select=*&id=eq.'.rawurlencode($r['media_clip_plan_id']).'&limit=1');
    }
    $p=$project[0]??[];
    $c=$clip[0]??[];

    $title=$c['clip_title'] ?? ($p['title'] ?? 'jessica_short');
    $slug=safe(strtolower(substr($title,0,70)));
    $jsonFile='render_'.$slug.'_'.substr($rid,0,8).'.json';
    $shFile='render_'.$slug.'_'.substr($rid,0,8).'.sh';
    $outputFile='export_'.$slug.'_'.substr($rid,0,8).'.mp4';

    $inputPath='/public_html/uploads/media/raw/'.($p['source_file'] ?? '');
    $outputPath='/public_html/uploads/media/renders/'.$outputFile;
    $caption=$c['overlay_text'] ?? 'Discover Connecticut';
    $cta=$c['cta_text'] ?? 'Call or text Mark Pyres: 203-247-2655';

    $instructions=[
      'render_id'=>$rid,
      'project_title'=>$p['title'] ?? '',
      'clip_title'=>$title,
      'input_file'=>$p['source_file'] ?? '',
      'input_path'=>$inputPath,
      'output_file'=>$outputFile,
      'output_path'=>$outputPath,
      'format'=>'vertical_1080x1920',
      'caption'=>$caption,
      'cta'=>$cta,
      'logo'=>'/public_html/uploads/media/assets/mark-logo.png',
      'start_seconds'=>$c['start_seconds'] ?? 0,
      'end_seconds'=>$c['end_seconds'] ?? 35,
      'effects'=>[
        'vertical_crop'=>true,
        'bold_captions'=>true,
        'logo_overlay'=>true,
        'cta_card'=>true,
        'ken_burns_for_photos'=>true,
        'punch_in_zoom'=>true,
        'subtitle_pop'=>true
      ],
      'opensource_tools'=>[
        'ffmpeg'=>'render/crop/caption/logo/export',
        'faster-whisper'=>'future transcript + word timestamps',
        'opencv'=>'future scene/hook detection',
        'imagemagick'=>'future title cards',
        'remotion'=>'future polished template rendering'
      ],
      'director_note'=>'Review the hook first. Keep captions readable. End with CTA. Make it feel unmistakably like Mark Pyres / Discover CT / House Detective content.'
    ];

    file_put_contents($renderDir.'/'.$jsonFile, json_encode($instructions,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    $cmd='bash /public_html/render-kit/render-short.sh '.escapeshellarg($inputPath).' '.escapeshellarg($outputPath).' '.escapeshellarg($caption).' '.escapeshellarg($cta).' '.escapeshellarg('/public_html/uploads/media/assets/mark-logo.png');
    file_put_contents($renderDir.'/'.$shFile, "#!/usr/bin/env bash\n".$cmd."\n");

    rk_sb('PATCH','media_render_queue?id=eq.'.rawurlencode($rid),[
      'render_status'=>'ready_for_render',
      'export_file'=>$outputFile,
      'render_instructions'=>($r['render_instructions']??'')."\n\nV17.2 Render File: /uploads/media/renders/".$jsonFile."\nShell Command File: /uploads/media/renders/".$shFile,
      'updated_at'=>date('c')
    ]);
    $updated++; $created+=2;
  }

  echo json_encode([
    'success'=>empty($errors),
    'queue_items'=>count($queue),
    'instruction_files_created'=>$created,
    'queue_updated'=>$updated,
    'render_folder'=>'/uploads/media/renders/',
    'note'=>'V17.2 creates render instructions and shell commands. Actual server rendering requires FFmpeg and shell execution.',
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>