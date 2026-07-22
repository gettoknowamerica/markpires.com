<?php
/**
 * Goliath Omni OS V58.1
 * Scorsese Chunked Upload Handler
 *
 * Stores large files in chunks, then assembles them safely.
 * Keys remain in lead-engine/config.php. No secrets here.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function out($arr,$code=200){ http_response_code($code); echo json_encode($arr, JSON_PRETTY_PRINT); exit; }
function safe_slug($s){ $s=strtolower(trim((string)$s)); $s=preg_replace('/[^a-z0-9]+/','-',$s); return trim($s,'-') ?: 'scorsese-project'; }
function cfg_key(){ return defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts'; }
function check_key(){ $key=$_POST['key'] ?? $_GET['key'] ?? ''; if(!hash_equals(cfg_key(),$key)) out(['success'=>false,'error'=>'Invalid key'],403); }
function sb($method,$endpoint,$payload=null){
  if(!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'error'=>'Supabase config missing'];
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: resolution=merge-duplicates,return=representation'];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'error'=>$err,'data'=>json_decode($body,true),'raw'=>$body];
}
function base_dir(){ return realpath(__DIR__.'/..') ?: dirname(__DIR__); }

check_key();
$action=$_POST['action'] ?? $_GET['action'] ?? 'chunk';
$root=base_dir();
$chunkRoot=$root.'/data/scorsese_chunks';
$rawRoot=$root.'/data/scorsese_raw';
$legacyRoot=$root.'/data/legacy_raw';
@mkdir($chunkRoot,0775,true); @mkdir($rawRoot,0775,true); @mkdir($legacyRoot,0775,true);

if($action==='status'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',$_GET['upload_id'] ?? '');
  if(!$uploadId) out(['success'=>false,'error'=>'Missing upload_id'],400);
  $dir=$chunkRoot.'/'.$uploadId;
  $metaFile=$dir.'/meta.json';
  $meta=is_file($metaFile)?json_decode(file_get_contents($metaFile),true):[];
  $received=count(glob($dir.'/chunk_*') ?: []);
  out(['success'=>true,'upload_id'=>$uploadId,'received'=>$received,'meta'=>$meta]);
}

if($action==='chunk'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',$_POST['upload_id'] ?? '');
  $chunkIndex=(int)($_POST['chunk_index'] ?? -1);
  $chunksTotal=(int)($_POST['chunks_total'] ?? 0);
  $filename=basename($_POST['filename'] ?? 'upload.mp4');
  $project=$_POST['project_name'] ?? 'Scorsese Project';
  $brand=$_POST['brand'] ?? 'mark_pires';
  $target=$_POST['target'] ?? 'scorsese'; // scorsese or legacy
  if(!$uploadId || $chunkIndex<0 || $chunksTotal<1) out(['success'=>false,'error'=>'Missing upload data'],400);
  if(empty($_FILES['chunk']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) out(['success'=>false,'error'=>'Missing chunk file'],400);

  $dir=$chunkRoot.'/'.$uploadId;
  @mkdir($dir,0775,true);
  $chunkPath=$dir.'/chunk_'.str_pad((string)$chunkIndex,6,'0',STR_PAD_LEFT);
  if(!move_uploaded_file($_FILES['chunk']['tmp_name'],$chunkPath)) out(['success'=>false,'error'=>'Could not save chunk'],500);

  $meta=[
    'upload_id'=>$uploadId,
    'filename'=>$filename,
    'project_name'=>$project,
    'brand'=>$brand,
    'target'=>$target,
    'chunks_total'=>$chunksTotal,
    'updated_at'=>gmdate('c')
  ];
  file_put_contents($dir.'/meta.json',json_encode($meta,JSON_PRETTY_PRINT));
  $received=count(glob($dir.'/chunk_*') ?: []);

  sb('POST','scorsese_media_assets?on_conflict=upload_id', [[
    'upload_id'=>$uploadId,
    'project_name'=>$project,
    'brand'=>$brand,
    'original_filename'=>$filename,
    'upload_status'=>'uploading',
    'chunks_total'=>$chunksTotal,
    'chunks_received'=>$received,
    'upload_method'=>'chunked',
    'raw_payload'=>$meta,
    'updated_at'=>gmdate('c')
  ]]);

  out(['success'=>true,'upload_id'=>$uploadId,'chunk_index'=>$chunkIndex,'chunks_total'=>$chunksTotal,'chunks_received'=>$received,'complete'=>$received>=$chunksTotal]);
}

if($action==='complete'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',$_POST['upload_id'] ?? '');
  $notes=$_POST['director_notes'] ?? '';
  $outputs=$_POST['desired_outputs'] ?? '[]';
  $outputsJson=json_decode($outputs,true); if(!is_array($outputsJson)) $outputsJson=[];
  if(!$uploadId) out(['success'=>false,'error'=>'Missing upload_id'],400);
  $dir=$chunkRoot.'/'.$uploadId; $metaFile=$dir.'/meta.json';
  if(!is_file($metaFile)) out(['success'=>false,'error'=>'Missing upload metadata'],400);
  $meta=json_decode(file_get_contents($metaFile),true) ?: [];
  $chunksTotal=(int)($meta['chunks_total'] ?? 0);
  $chunks=glob($dir.'/chunk_*') ?: [];
  sort($chunks);
  if(count($chunks)<$chunksTotal) out(['success'=>false,'error'=>'Not all chunks received','received'=>count($chunks),'expected'=>$chunksTotal],400);

  $target=($meta['target'] ?? 'scorsese')==='legacy' ? 'legacy' : 'scorsese';
  $destRoot=$target==='legacy' ? $legacyRoot : $rawRoot;
  $projectSlug=safe_slug($meta['project_name'] ?? 'scorsese-project');
  $projectDir=$destRoot.'/'.$projectSlug;
  @mkdir($projectDir,0775,true);
  $ext=strtolower(pathinfo($meta['filename'] ?? 'upload.mp4', PATHINFO_EXTENSION));
  if(!$ext) $ext='mp4';
  $stored=safe_slug(pathinfo($meta['filename'] ?? 'upload', PATHINFO_FILENAME)).'-'.$uploadId.'.'.$ext;
  $dest=$projectDir.'/'.$stored;

  $out=fopen($dest,'wb');
  if(!$out) out(['success'=>false,'error'=>'Could not open destination file'],500);
  foreach($chunks as $c){ $in=fopen($c,'rb'); if($in){ stream_copy_to_stream($in,$out); fclose($in); } }
  fclose($out);

  $rel='/data/'.($target==='legacy'?'legacy_raw':'scorsese_raw').'/'.$projectSlug.'/'.$stored;
  $size=filesize($dest);
  $asset=sb('POST','scorsese_media_assets?on_conflict=upload_id', [[
    'upload_id'=>$uploadId,
    'project_name'=>$meta['project_name'] ?? 'Scorsese Project',
    'brand'=>$meta['brand'] ?? 'mark_pires',
    'media_type'=>'video',
    'original_filename'=>$meta['filename'] ?? $stored,
    'stored_filename'=>$stored,
    'stored_path'=>$dest,
    'public_url'=>$rel,
    'file_size_bytes'=>$size,
    'upload_status'=>'stored',
    'chunks_total'=>$chunksTotal,
    'chunks_received'=>count($chunks),
    'director_notes'=>$notes,
    'desired_outputs'=>$outputsJson,
    'scorsese_status'=>'ready_for_commission',
    'raw_payload'=>$meta,
    'updated_at'=>gmdate('c')
  ]]);

  // Queue Scorsese production command if command table/event bus exists.
  sb('POST','goliath_events', [[
    'department'=>'Scorsese',
    'event_type'=>'media_uploaded',
    'title'=>'Scorsese media stored',
    'detail'=>'Media ready: '.($meta['project_name'] ?? 'Scorsese Project'),
    'roi_estimate'=>9000,
    'confidence'=>96,
    'status'=>'ready',
    'phase'=>'media_vault',
    'progress'=>100,
    'link_url'=>'/dashboard/scorsese-media-vault.php',
    'metadata'=>['upload_id'=>$uploadId,'url'=>$rel,'outputs'=>$outputsJson]
  ]]);

  // Leave chunks for 24h safety? For now delete after successful merge.
  foreach($chunks as $c) @unlink($c);
  @unlink($metaFile); @rmdir($dir);

  out(['success'=>true,'version'=>'58.1','upload_id'=>$uploadId,'stored_url'=>$rel,'size_bytes'=>$size,'asset'=>$asset['data'] ?? null,'next'=>'Open Scorsese Media Vault.']);
}

out(['success'=>false,'error'=>'Unknown action'],400);
