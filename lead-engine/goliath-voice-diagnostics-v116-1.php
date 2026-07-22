<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function vd1161_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
$key=(string)($_GET['key']??'');
if(!hash_equals(vd1161_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$messages=gdb_all(
 "SELECT m.id,m.conversation_uid,m.speaker_key,m.message_type,m.message_text,m.task_id,m.metadata_json,m.created_at,
         t.status task_status,t.task_type,t.updated_at task_updated_at,
         t.result task_result
  FROM goliath_messages_v111 m
  LEFT JOIN local_ai_tasks t ON t.id=m.task_id
  ORDER BY m.id DESC LIMIT 12"
)?:[];

foreach($messages as &$m){
 $meta=json_decode((string)($m['metadata_json']??''),true);
 $result=json_decode((string)($m['task_result']??''),true);
 $m['message_audio_url']=is_array($meta)?($meta['audio_url']??null):null;
 $m['task_audio_url']=is_array($result)?($result['audio_url']??null):null;
 $m['task_content_preview']=is_array($result)?substr((string)($result['content_text']??''),0,240):substr((string)($m['task_result']??''),0,240);
 unset($m['metadata_json'],$m['task_result']);
}
echo json_encode(['ok'=>true,'version'=>'V116.1 Voice Diagnostics','messages'=>$messages,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>