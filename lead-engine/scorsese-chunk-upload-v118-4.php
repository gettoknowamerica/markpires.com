<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function cu1184_out(array $data,int $code=200):never{
 http_response_code($code);echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
}
function cu1184_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function cu1184_slug(string $text):string{
 $text=strtolower(trim($text));$text=preg_replace('/[^a-z0-9]+/','-',$text);
 return trim($text,'-')?:'scorsese-project';
}
function cu1184_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(16));}
function cu1184_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function cu1184_insert(string $table,array $row):int{
 $cols=cu1184_cols($table);$safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for $table");
 return (int)gdb_insert($table,$safe);
}
function cu1184_root():string{return realpath(__DIR__.'/..')?:dirname(__DIR__);}

$key=trim((string)($_POST['key']??$_GET['key']??''));
if(!hash_equals(cu1184_key(),$key))cu1184_out(['success'=>false,'error'=>'bad_key'],403);

$action=(string)($_POST['action']??$_GET['action']??'chunk');
$root=cu1184_root();
$chunkRoot=$root.'/data/scorsese_chunks';
$rawRoot=$root.'/data/scorsese_raw';
$legacyRoot=$root.'/data/legacy_raw';
@mkdir($chunkRoot,0775,true);@mkdir($rawRoot,0775,true);@mkdir($legacyRoot,0775,true);

try{
 if($action==='status'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_GET['upload_id']??''));
  if($uploadId==='')throw new RuntimeException('Missing upload_id.');
  $dir=$chunkRoot.'/'.$uploadId;
  $meta=is_file($dir.'/meta.json')?json_decode((string)file_get_contents($dir.'/meta.json'),true):[];
  cu1184_out(['success'=>true,'received'=>count(glob($dir.'/chunk_*')?:[]),'meta'=>$meta]);
 }

 if($action==='chunk'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['upload_id']??''));
  $index=(int)($_POST['chunk_index']??-1);$total=(int)($_POST['chunks_total']??0);
  if($uploadId===''||$index<0||$total<1)throw new RuntimeException('Missing chunk metadata.');
  if(empty($_FILES['chunk'])||!is_uploaded_file($_FILES['chunk']['tmp_name']))throw new RuntimeException('Missing chunk file.');

  $dir=$chunkRoot.'/'.$uploadId;@mkdir($dir,0775,true);
  $path=$dir.'/chunk_'.str_pad((string)$index,6,'0',STR_PAD_LEFT);
  if(!move_uploaded_file($_FILES['chunk']['tmp_name'],$path))throw new RuntimeException('Could not save chunk.');

  $meta=[
   'upload_id'=>$uploadId,'filename'=>basename((string)($_POST['filename']??'upload.mp4')),
   'project_name'=>(string)($_POST['project_name']??'Scorsese Project'),
   'brand'=>(string)($_POST['brand']??'mark_pires'),'target'=>(string)($_POST['target']??'scorsese'),
   'production_mode'=>(string)($_POST['production_mode']??'automatic_director'),
   'production_type'=>(string)($_POST['production_type']??'episode'),
   'source_goal'=>(string)($_POST['source_goal']??''),'supplied_script'=>(string)($_POST['supplied_script']??''),
   'chunks_total'=>$total,'updated_at'=>gmdate('c')
  ];
  file_put_contents($dir.'/meta.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
  $received=count(glob($dir.'/chunk_*')?:[]);
  cu1184_out(['success'=>true,'upload_id'=>$uploadId,'chunk_index'=>$index,'chunks_total'=>$total,'chunks_received'=>$received,'complete'=>$received>=$total]);
 }

 if($action==='complete'){
  $uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['upload_id']??''));
  if($uploadId==='')throw new RuntimeException('Missing upload_id.');
  $dir=$chunkRoot.'/'.$uploadId;$metaFile=$dir.'/meta.json';
  if(!is_file($metaFile))throw new RuntimeException('Missing upload metadata.');
  $meta=json_decode((string)file_get_contents($metaFile),true)?:[];
  $chunks=glob($dir.'/chunk_*')?:[];sort($chunks);
  if(count($chunks)<(int)($meta['chunks_total']??0))throw new RuntimeException('Not all chunks were received.');

  $target=($meta['target']??'scorsese')==='legacy'?'legacy':'scorsese';
  $destRoot=$target==='legacy'?$legacyRoot:$rawRoot;
  $projectDir=$destRoot.'/'.cu1184_slug((string)$meta['project_name']);@mkdir($projectDir,0775,true);
  $ext=strtolower(pathinfo((string)$meta['filename'],PATHINFO_EXTENSION))?:'mp4';
  $stored=cu1184_slug(pathinfo((string)$meta['filename'],PATHINFO_FILENAME)).'-'.$uploadId.'.'.$ext;
  $absolute=$projectDir.'/'.$stored;
  $output=fopen($absolute,'wb');if(!$output)throw new RuntimeException('Could not open destination.');
  foreach($chunks as $chunk){$input=fopen($chunk,'rb');if($input){stream_copy_to_stream($input,$output);fclose($input);}}
  fclose($output);

  $relative='/data/'.($target==='legacy'?'legacy_raw':'scorsese_raw').'/'.cu1184_slug((string)$meta['project_name']).'/'.$stored;
  $notes=(string)($_POST['director_notes']??'');
  $outputs=json_decode((string)($_POST['desired_outputs']??'[]'),true);if(!is_array($outputs))$outputs=[];

  gdb()->beginTransaction();
  try{
   $projectId=cu1184_insert('scorsese_director_projects',[
    'project_uid'=>cu1184_uid('director'),'title'=>(string)$meta['project_name'],
    'production_mode'=>in_array($meta['production_mode']??'',['automatic_director','human_director'],true)?$meta['production_mode']:'automatic_director',
    'production_type'=>(string)($meta['production_type']??'episode'),
    'source_goal'=>(string)($meta['source_goal']??''),'supplied_script'=>(string)($meta['supplied_script']??''),
    'status'=>'ingest','progress'=>10,'current_phase'=>'Raw source uploaded; proxy and WhisperX analysis pending',
    'metadata_json'=>gdb_json(['brand'=>$meta['brand'],'desired_outputs'=>$outputs,'director_notes'=>$notes,'upload_id'=>$uploadId,'version'=>'118.4']),
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   $sourceId=cu1184_insert('scorsese_media_sources',[
    'project_id'=>$projectId,'source_uid'=>cu1184_uid('source'),'source_name'=>(string)$meta['filename'],
    'source_url'=>$relative,'source_path'=>$absolute,'media_type'=>'video','transcript_status'=>'pending',
    'metadata_json'=>gdb_json(['upload_id'=>$uploadId,'size_bytes'=>filesize($absolute),'brand'=>$meta['brand']]),
    'created_at'=>gdb_now()
   ]);
   if($notes!=='')cu1184_insert('scorsese_director_notes',[
    'project_id'=>$projectId,'scene_id'=>null,'note_type'=>'project_direction','note_text'=>$notes,
    'created_by'=>'mark','status'=>'open','created_at'=>gdb_now()
   ]);
   gdb()->commit();
  }catch(Throwable $tx){if(gdb()->inTransaction())gdb()->rollBack();throw $tx;}

  foreach($chunks as $chunk)@unlink($chunk);@unlink($metaFile);@rmdir($dir);

  cu1184_out([
   'success'=>true,'version'=>'V118.4 Internal Chunk Upload','upload_id'=>$uploadId,
   'stored_url'=>$relative,'size_bytes'=>filesize($absolute),'project_id'=>$projectId,'source_id'=>$sourceId,
   'project_url'=>'/dashboard/scorsese-studio-pro.php?director_project='.$projectId.'#director-workstation',
   'next'=>'The project is now inside Scorsese Studio Pro.'
  ]);
 }

 throw new RuntimeException('Unknown action.');
}catch(Throwable $e){
 cu1184_out(['success'=>false,'version'=>'V118.4 Internal Chunk Upload','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],500);
}
?>