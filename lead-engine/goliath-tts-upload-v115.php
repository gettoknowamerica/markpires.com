<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gt1159_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function gt1159_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
$key=(string)($_POST['key']??'');
if(!hash_equals(gt1159_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 $taskId=max(0,(int)($_POST['task_id']??0));
 if($taskId<1)throw new RuntimeException('A valid Ask Goliath task_id is required.');
 $cols=gt1159_cols('local_ai_tasks');
 $fields=['id'];foreach(['task_type','type','metadata','metadata_json'] as $c)if(isset($cols[$c]))$fields[]=$c;
 $task=gdb_one("SELECT ".implode(',',$fields)." FROM local_ai_tasks WHERE id=? LIMIT 1",[$taskId]);
 if(!$task)throw new RuntimeException('Task not found.');
 $taskType=strtolower(trim((string)($task['task_type']??$task['type']??'')));
 if($taskType!=='ask_goliath_live_v111'){
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'tts_rejected_non_conversation_task','task_id'=>$taskId,'task_type'=>$taskType,'message'=>'Kokoro may speak only Ask Goliath conversation replies, never missions, constitutions, stage prompts, or executive work.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
 }
 $metaRaw=(string)($task['metadata_json']??$task['metadata']??'');
 $meta=json_decode($metaRaw,true);if(!is_array($meta))$meta=[];
 if(empty($meta['tts_requested']) && empty($meta['voice'])){
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'tts_not_requested','task_id'=>$taskId,'message'=>'This conversation turn did not request spoken audio.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
 }
 if(empty($_FILES['audio'])||($_FILES['audio']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('No valid audio file was uploaded.');
 $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__)),'/\\');
 $dir=$docRoot.'/uploads/goliath-tts/'.date('Y/m');
 if(!is_dir($dir)&&!mkdir($dir,0775,true))throw new RuntimeException('Could not create TTS upload directory.');
 $name='goliath_'.$taskId.'_'.bin2hex(random_bytes(4)).'.mp3';
 $path=$dir.'/'.$name;
 if(!move_uploaded_file($_FILES['audio']['tmp_name'],$path))throw new RuntimeException('Could not save TTS audio.');
 $url=str_replace('\\','/',str_replace($docRoot,'',$path));
 echo json_encode(['ok'=>true,'version'=>'V115.9 Conversation-Only TTS','task_id'=>$taskId,'audio_url'=>$url,'scope'=>'ask_goliath_live_only'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'version'=>'V115.9 Conversation-Only TTS','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>
