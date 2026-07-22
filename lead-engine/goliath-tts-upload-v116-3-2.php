<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function t11632_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function t11632_cols(string $table):array{
 $rows=gdb_all(
  "SELECT column_name FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name=?",
  [$table]
 )?:[];
 $out=[];
 foreach($rows as $row)$out[(string)$row['column_name']]=true;
 return $out;
}

$raw=(string)file_get_contents('php://input');
$in=json_decode($raw,true);

if(!is_array($in)){
 http_response_code(400);
 echo json_encode(['ok'=>false,'version'=>'V116.3.2 JSON TTS Upload','error'=>'json_required']);
 exit;
}

$key=(string)($in['key']??'');
if(!hash_equals(t11632_key(),$key)){
 http_response_code(403);
 echo json_encode(['ok'=>false,'version'=>'V116.3.2 JSON TTS Upload','error'=>'bad_key']);
 exit;
}

try{
 $taskId=(int)($in['task_id']??0);
 $b64=(string)($in['audio_base64']??'');

 if($taskId<1||$b64==='')throw new RuntimeException('Missing task_id or audio_base64.');

 $cols=t11632_cols('local_ai_tasks');
 $select=['id'];
 foreach(['task_type','metadata_json','metadata'] as $column){
  if(isset($cols[$column]))$select[]=$column;
 }

 $task=gdb_one(
  "SELECT ".implode(',',$select)." FROM local_ai_tasks WHERE id=? LIMIT 1",
  [$taskId]
 );

 if(!$task)throw new RuntimeException('Voice task was not found.');

 if(isset($task['task_type'])&&(string)$task['task_type']!=='ask_goliath_live_v111'){
  throw new RuntimeException('TTS is allowed only for live conversation tasks.');
 }

 $meta=[];
 foreach(['metadata_json','metadata'] as $column){
  if(empty($task[$column]))continue;
  $decoded=json_decode((string)$task[$column],true);
  if(is_array($decoded)){$meta=$decoded;break;}
 }

 if($meta && !(($meta['voice']??false)||($meta['tts_requested']??false))){
  throw new RuntimeException('This live task did not request TTS.');
 }

 $bytes=base64_decode($b64,true);
 if($bytes===false||strlen($bytes)<500)throw new RuntimeException('Invalid or empty MP3 data.');

 $relativeDirectory='/uploads/goliath-tts/'.date('Y/m');
 $absoluteDirectory=dirname(__DIR__).$relativeDirectory;

 if(!is_dir($absoluteDirectory)){
  if(!mkdir($absoluteDirectory,0755,true)&&!is_dir($absoluteDirectory)){
   throw new RuntimeException('Could not create TTS directory: '.$absoluteDirectory);
  }
 }

 $filename='goliath_'.$taskId.'_'.bin2hex(random_bytes(4)).'.mp3';
 $absolutePath=$absoluteDirectory.'/'.$filename;

 if(file_put_contents($absolutePath,$bytes,LOCK_EX)===false){
  throw new RuntimeException('Could not save MP3 file.');
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V116.3.2 JSON TTS Upload',
  'audio_url'=>$relativeDirectory.'/'.$filename,
  'bytes'=>strlen($bytes),
  'task_id'=>$taskId
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,
  'version'=>'V116.3.2 JSON TTS Upload',
  'error'=>$e->getMessage(),
  'file'=>$e->getFile(),
  'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>