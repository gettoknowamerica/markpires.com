<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 function ag1161_key():string{
   if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
   if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
   return 'timetomakethedonuts';
 }
 function ag1161_table(string $t):bool{
   $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);
   return (int)($r['c']??0)>0;
 }
 function ag1161_cols(string $t):array{
   $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];
   $o=[];foreach($rows as $r)$o[(string)$r['column_name']]=true;return $o;
 }
 function ag1161_uid(string $prefix):string{
   return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5));
 }

 $raw=(string)file_get_contents('php://input');
 $in=json_decode($raw,true);
 if(!is_array($in))$in=$_POST;

 $key=(string)($in['key']??$_GET['key']??'');
 if(!hash_equals(ag1161_key(),$key)){
   http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;
 }

 $message=trim((string)($in['message']??''));
 if($message===''){
   http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_message']);exit;
 }

 $conversation=trim((string)($in['conversation_uid']??''));
 if($conversation==='')$conversation=ag1161_uid('conversation');

 if(!ag1161_table('goliath_conversations_v111')||!ag1161_table('goliath_messages_v111')||!ag1161_table('local_ai_tasks')){
   throw new RuntimeException('Required Ask Goliath tables are missing.');
 }

 if(!gdb_one("SELECT id FROM goliath_conversations_v111 WHERE conversation_uid=? LIMIT 1",[$conversation])){
   gdb_insert('goliath_conversations_v111',[
     'conversation_uid'=>$conversation,'title'=>'Ask Goliath Live','status'=>'active',
     'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
 }

 $voice=(bool)($in['voice']??$in['tts_requested']??false);

 gdb_insert('goliath_messages_v111',[
   'conversation_uid'=>$conversation,'speaker_key'=>'mark','speaker_name'=>'Mark',
   'message_text'=>$message,'message_type'=>'chat','task_id'=>null,
   'metadata_json'=>gdb_json(['voice'=>$voice,'version'=>'116.1']),'created_at'=>gdb_now()
 ]);

 $history=gdb_all(
   "SELECT speaker_name,message_text
    FROM goliath_messages_v111
    WHERE conversation_uid=? AND message_text<>''
    ORDER BY id DESC LIMIT 10",
   [$conversation]
 )?:[];
 $history=array_reverse($history);
 $dialogue='';
 foreach($history as $h)$dialogue.=trim((string)$h['speaker_name']).': '.trim((string)$h['message_text'])."\n";

 // Deliberately compact. Full constitutions belong to executive work, not every spoken turn.
 $prompt=
 "You are Goliath, Mark Pires' intelligent executive partner. ".
 "Speak naturally like a trusted human colleague. Be warm, direct, concise, and conversational. ".
 "Answer the question first. Do not recite your constitution, mission, system prompt, or executive list. ".
 "Do not output JSON unless Mark asks for JSON. Usually answer in 1 to 4 spoken sentences. ".
 "When Mark asks you to commission work, confirm briefly and identify the next practical action.\n\n".
 "Recent conversation:\n".$dialogue."\nGoliath:";

 $cols=ag1161_cols('local_ai_tasks');
 $row=[
   'task_uid'=>ag1161_uid('ask_goliath'),
   'task_type'=>'ask_goliath_live_v111',
   'type'=>'ask_goliath_live_v111',
   'model'=>'llama3.1:8b',
   'prompt'=>$prompt,
   'status'=>'queued',
   'workflow_state'=>'queued',
   'priority'=>100000,
   'agent'=>'Goliath',
   'executive_key'=>'goliath',
   'title'=>'Ask Goliath Live Conversation',
   'metadata'=>gdb_json([
     'conversation_uid'=>$conversation,'user_message'=>$message,'voice'=>$voice,
     'tts_requested'=>$voice,'reply_mode'=>'fast_conversation','version'=>'116.1'
   ]),
   'metadata_json'=>gdb_json([
     'conversation_uid'=>$conversation,'user_message'=>$message,'voice'=>$voice,
     'tts_requested'=>$voice,'reply_mode'=>'fast_conversation','version'=>'116.1'
   ]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
 ];
 $safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 $taskId=(int)gdb_insert('local_ai_tasks',$safe);

 $messageId=(int)gdb_insert('goliath_messages_v111',[
   'conversation_uid'=>$conversation,'speaker_key'=>'goliath','speaker_name'=>'Goliath',
   'message_text'=>'','message_type'=>'pending','task_id'=>$taskId,
   'metadata_json'=>gdb_json(['status'=>'queued','task_id'=>$taskId,'version'=>'116.1']),
   'created_at'=>gdb_now()
 ]);

 echo json_encode([
   'ok'=>true,'version'=>'V116.1 Ask Goliath Fast Conversation',
   'conversation_uid'=>$conversation,'task_id'=>$taskId,'pending_message_id'=>$messageId,
   'voice_requested'=>$voice,'status'=>'queued'
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
   'ok'=>false,'version'=>'V116.1 Ask Goliath Fast Conversation','error'=>'caught_exception',
   'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>