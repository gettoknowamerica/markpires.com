<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function t1181_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function t1181_cols(string $table):array{
 $rows=gdb_all(
  "SELECT column_name FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name=?",
  [$table]
 )?:[];
 $out=[];
 foreach($rows as $row)$out[(string)$row['column_name']]=true;
 return $out;
}
function t1181_fail(int $status,string $error,array $extra=[]):never{
 http_response_code($status);
 echo json_encode(['ok'=>false,'version'=>'V118.1 TTS Upload','error'=>$error]+$extra,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 exit;
}

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));

if($method==='GET'){
 $key=trim((string)($_GET['key']??''));
 if($key!==''&&!hash_equals(t1181_key(),$key))t1181_fail(403,'bad_key');
 echo json_encode([
  'ok'=>true,
  'version'=>'V118.1 TTS Upload',
  'method'=>'GET',
  'post_supported'=>true,
  'json_base64_supported'=>true,
  'multipart_supported'=>true,
  'usage'=>'POST JSON {key,task_id,audio_base64} or multipart {key,task_id,audio}.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 exit;
}

if($method!=='POST'){
 header('Allow: GET, POST');
 t1181_fail(405,'method_not_allowed',['received_method'=>$method,'allowed'=>['GET','POST']]);
}

$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
$input=[];
$bytes=false;

if(str_contains($contentType,'application/json')){
 $raw=(string)file_get_contents('php://input');
 $decoded=json_decode($raw,true);
 if(!is_array($decoded))t1181_fail(400,'invalid_json');
 $input=$decoded;
 $encoded=(string)($input['audio_base64']??'');
 if($encoded!=='')$bytes=base64_decode($encoded,true);
}else{
 $input=$_POST;
 if(!empty($_FILES['audio'])&&($_FILES['audio']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){
  $bytes=@file_get_contents((string)$_FILES['audio']['tmp_name']);
 }
}

$key=trim((string)($input['key']??''));
if(!hash_equals(t1181_key(),$key))t1181_fail(403,'bad_key');

try{
 $taskId=(int)($input['task_id']??0);
 if($taskId<1)throw new RuntimeException('Missing or invalid task_id.');
 if($bytes===false||strlen((string)$bytes)<500)throw new RuntimeException('No usable MP3 data was received.');

 $cols=t1181_cols('local_ai_tasks');
 $select=['id'];
 foreach(['task_type','type','metadata_json','metadata'] as $column){
  if(isset($cols[$column]))$select[]=$column;
 }
 $task=gdb_one("SELECT ".implode(',',$select)." FROM local_ai_tasks WHERE id=? LIMIT 1",[$taskId]);
 if(!$task)throw new RuntimeException('Voice task was not found.');

 $taskType=strtolower(trim((string)($task['task_type']??$task['type']??'')));
 if($taskType!=='ask_goliath_live_v111'){
  throw new RuntimeException('TTS is restricted to Ask Goliath live conversation tasks.');
 }

 $meta=[];
 foreach(['metadata_json','metadata'] as $column){
  if(empty($task[$column]))continue;
  $decoded=json_decode((string)$task[$column],true);
  if(is_array($decoded)){$meta=$decoded;break;}
 }
 if($meta&&empty($meta['voice'])&&empty($meta['tts_requested'])){
  throw new RuntimeException('This live conversation task did not request spoken audio.');
 }

 $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__)),'/\\');
 $relative='/uploads/goliath-tts/'.date('Y/m');
 $directory=$docRoot.$relative;

 if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory)){
  throw new RuntimeException('Could not create TTS directory.');
 }

 $filename='goliath_'.$taskId.'_'.bin2hex(random_bytes(6)).'.mp3';
 $absolute=$directory.'/'.$filename;
 if(file_put_contents($absolute,$bytes,LOCK_EX)===false){
  throw new RuntimeException('Could not save the MP3.');
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V118.1 TTS Upload',
  'task_id'=>$taskId,
  'audio_url'=>$relative.'/'.$filename,
  'bytes'=>strlen($bytes),
  'received_as'=>str_contains($contentType,'application/json')?'json_base64':'multipart',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
 t1181_fail(500,'upload_failed',[
  'message'=>$e->getMessage(),
  'file'=>basename($e->getFile()),
  'line'=>$e->getLine()
 ]);
}
?>