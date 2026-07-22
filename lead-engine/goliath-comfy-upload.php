<?php
/**
 * Goliath Omni V75.5 — Comfy media upload endpoint.
 * PowerShell worker uploads local Comfy output files here.
 */
require_once __DIR__.'/goliath-comfy-v75-bridge.php';
[$in,$raw]=gc55_in();
if(!gc55_key_ok($in)) gc55_out(['success'=>false,'error'=>'bad_key'],403);

$jobId=(int)($in['job_id']??($in['id']??0));
if(empty($_FILES)){
  gc55_out(['success'=>false,'error'=>'no_file_received','files'=>array_keys($_FILES),'post_keys'=>array_keys($_POST),'get_keys'=>array_keys($_GET)],400);
}
$file=current($_FILES);
if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK){
  gc55_out(['success'=>false,'error'=>'upload_error','code'=>$file['error']??null],400);
}

$orig=basename((string)($file['name']??'goliath-media.bin'));
$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
$allowed=['png','jpg','jpeg','webp','gif','svg','mp4','mov','webm','m4v'];
if(!in_array($ext,$allowed,true)) $ext='bin';

$relDir='/data/goliath_media/'.date('Ymd');
$absDir=realpath(__DIR__.'/..').$relDir;
if(!is_dir($absDir)) @mkdir($absDir,0775,true);
$safe='scorsese_job_'.$jobId.'_'.date('His').'_'.substr(bin2hex(random_bytes(4)),0,8).'.'.$ext;
$abs=$absDir.'/'.$safe;
if(!move_uploaded_file($file['tmp_name'],$abs)){
  gc55_out(['success'=>false,'error'=>'move_failed','target'=>$abs],500);
}
$url=$relDir.'/'.$safe;
$type=preg_match('/\.(mp4|mov|webm|m4v)$/i',$safe)?'video':'image';

gc55_out([
  'success'=>true,
  'job_id'=>$jobId,
  'url'=>$url,
  'output_url'=>$url,
  'output_path'=>$abs,
  'output_type'=>$type,
  'message'=>'Media uploaded to Hostinger.'
]);
?>