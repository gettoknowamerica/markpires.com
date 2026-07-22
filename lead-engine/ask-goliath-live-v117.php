<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function a117_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function a117_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function a117_uid(string $prefix):string{
 return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5));
}
function a117_insert_safe(string $table,array $row):int{
 $cols=a117_cols($table);$safe=[];foreach($row as $key=>$value)if(isset($cols[$key]))$safe[$key]=$value;
 if(!$safe)throw new RuntimeException("No compatible columns for $table.");
 return (int)gdb_insert($table,$safe);
}

$raw=(string)file_get_contents('php://input');
$input=json_decode($raw,true);
if(!is_array($input))$input=$_POST;

$key=(string)($input['key']??$_GET['key']??'');
if(!hash_equals(a117_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $message=trim((string)($input['message']??''));
 if($message===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_message']);exit;}

 $conversation=trim((string)($input['conversation_uid']??''));
 if($conversation==='')$conversation=a117_uid('conversation');

 if(!gdb_one("SELECT id FROM goliath_conversations_v111 WHERE conversation_uid=? LIMIT 1",[$conversation])){
  gdb_insert('goliath_conversations_v111',[
   'conversation_uid'=>$conversation,'title'=>'Ask Goliath V117','status'=>'active',
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
 }

 $voice=(bool)($input['voice']??true);
 gdb_insert('goliath_messages_v111',[
  'conversation_uid'=>$conversation,'speaker_key'=>'mark','speaker_name'=>'Mark',
  'message_text'=>$message,'message_type'=>'chat','task_id'=>null,
  'metadata_json'=>gdb_json(['voice'=>$voice,'wake_phrase'=>(string)($input['wake_phrase']??'hey goliath'),'version'=>'117']),
  'created_at'=>gdb_now()
 ]);

 $history=gdb_all(
  "SELECT speaker_name,message_text FROM goliath_messages_v111
   WHERE conversation_uid=? AND message_text<>'' AND message_type<>'error'
   ORDER BY id DESC LIMIT 12",[$conversation]
 )?:[];
 $history=array_reverse($history);
 $dialogue=[];
 foreach($history as $turn)$dialogue[]=($turn['speaker_name']?:'Speaker').': '.trim((string)$turn['message_text']);

 // The runtime fetches the live Brain Context independently. The queued prompt stays
 // compact so conversation is fast and the Constitution is not spoken verbatim.
 $prompt=
  "Mark Pires said:\n".$message."\n\n".
  "Recent conversation:\n".implode("\n",$dialogue)."\n\n".
  "Respond as Goliath in natural spoken language. Answer first. Usually use 1 to 5 sentences. ".
  "Use named Executives when discussing team work. Never invent progress, research or completed work.";

 $metadata=[
  'source'=>'goliath_v117_handsfree',
  'conversation_uid'=>$conversation,
  'user_message'=>$message,
  'voice'=>$voice,
  'tts_requested'=>$voice,
  'reply_mode'=>'brain_grounded_conversation',
  'wake_phrase'=>(string)($input['wake_phrase']??'hey goliath'),
  'client'=>(string)($input['client']??'browser'),
  'version'=>'117'
 ];

 $taskId=a117_insert_safe('local_ai_tasks',[
  'task_uid'=>a117_uid('ask_goliath_v117'),
  'task_type'=>'ask_goliath_live_v111',
  'type'=>'ask_goliath_live_v111',
  'model'=>'llama3.1:8b',
  'prompt'=>$prompt,
  'status'=>'queued',
  'workflow_state'=>'queued',
  'priority'=>1000000,
  'agent'=>'Goliath',
  'executive_key'=>'goliath',
  'title'=>'Goliath V117 Brain-Grounded Conversation',
  'metadata'=>gdb_json($metadata),
  'metadata_json'=>gdb_json($metadata),
  'created_at'=>gdb_now(),
  'updated_at'=>gdb_now()
 ]);

 $pendingId=(int)gdb_insert('goliath_messages_v111',[
  'conversation_uid'=>$conversation,'speaker_key'=>'goliath','speaker_name'=>'Goliath',
  'message_text'=>'','message_type'=>'pending','task_id'=>$taskId,
  'metadata_json'=>gdb_json(['task_id'=>$taskId,'status'=>'queued','version'=>'117']),
  'created_at'=>gdb_now()
 ]);

 echo json_encode([
  'ok'=>true,'version'=>'V117 Ask Goliath Brain Lane',
  'conversation_uid'=>$conversation,'task_id'=>$taskId,'pending_message_id'=>$pendingId,
  'status'=>'queued','voice_requested'=>$voice
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V117 Ask Goliath Brain Lane','error'=>'caught_exception',
  'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>