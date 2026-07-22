<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function t1163_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
$raw=(string)file_get_contents('php://input');
$in=json_decode($raw,true);
if(!is_array($in)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'json_required']);exit;}
$key=(string)($in['key']??'');
if(!hash_equals(t1163_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $taskId=(int)($in['task_id']??0);
 $b64=(string)($in['audio_base64']??'');
 if($taskId<1||$b64==='')throw new RuntimeException('Missing task_id or audio_base64.');
 $task=gdb_one("SELECT task_type,metadata_json,metadata FROM local_ai_tasks WHERE id=? LIMIT 1",[$taskId]);
 if(!$task||(string)$task['task_type']!=='ask_goliath_live_v111')throw new RuntimeException('TTS is allowed only for live conversation tasks.');
 $meta=[];
 foreach(['metadata_json','metadata'] as $c){
  if(!empty($task[$c])){$j=json_decode((string)$task[$c],true);if(is_array($j)){$meta=$j;break;}}
 }
 if(!(($meta['voice']??false)||($meta['tts_requested']??false)))throw new RuntimeException('This live task did not request TTS.');

 $bytes=base64_decode($b64,true);
 if($bytes===false||strlen($bytes)<500)throw new RuntimeException('Invalid or empty MP3 data.');

 $rel='/uploads/goliath-tts/'.date('Y/m');
 $root=dirname(__DIR__).$rel;
 if(!is_dir($root)&&!mkdir($root,0755,true)&&!is_dir($root))throw new RuntimeException('Could not create TTS directory.');
 $name='goliath_'.$taskId.'_'.bin2hex(random_bytes(4)).'.mp3';
 $path=$root.'/'.$name;
 if(file_put_contents($path,$bytes,LOCK_EX)===false)throw new RuntimeException('Could not save MP3.');
 echo json_encode(['ok'=>true,'version'=>'V116.3 JSON TTS Upload','audio_url'=>$rel.'/'.$name,'bytes'=>strlen($bytes)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V116.3 JSON TTS Upload','error'=>$e->getMessage()],JSON_PRETTY_PRINT);
}
?>