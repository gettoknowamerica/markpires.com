<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$response=['ok'=>false,'version'=>'V115.7 Ask Goliath Fast Lane'];
register_shutdown_function(function()use(&$response){
  $e=error_get_last();
  if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    if(!headers_sent()){http_response_code(500);header('Content-Type: application/json; charset=utf-8');}
    $response['error']='fatal_error';
    $response['details']=['message'=>$e['message'],'file'=>$e['file'],'line'=>$e['line']];
    echo json_encode($response,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  }
});

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 if(file_exists(__DIR__.'/goliath-orchestration-lib-v115.php')){
 require_once __DIR__.'/goliath-orchestration-lib-v115.php';
}elseif(file_exists(__DIR__.'/goliath-orchestration-lib-v114.php')){
 require_once __DIR__.'/goliath-orchestration-lib-v114.php';
}

 function ag111_key():string{
   if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
   if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
   return 'timetomakethedonuts';
 }
 function ag111_one(string $s,array $p=[]):?array{try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function ag111_table(string $t):bool{$r=ag111_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return (int)($r['c']??0)>0;}
 function ag111_cols(string $t):array{
   try{$rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];$out=[];foreach($rows as $r)$out[$r['column_name']]=true;return $out;}catch(Throwable $e){return [];}
 }
 function ag111_insert_safe(string $t,array $row,array $cols):int{ $safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;if(!$safe)throw new RuntimeException("No compatible columns found for $t.");return (int)gdb_insert($t,$safe); }
 function ag111_uid(string $p):string{return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}

 $raw=file_get_contents('php://input');
 $in=json_decode((string)$raw,true);
 if(!is_array($in))$in=$_POST;

 $key=(string)($in['key']??$_GET['key']??'');
 if(!hash_equals(ag111_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'version'=>'V115.7 Ask Goliath Fast Lane','error'=>'bad_key']);exit;}

 $message=trim((string)($in['message']??''));
 if($message===''){http_response_code(400);echo json_encode(['ok'=>false,'version'=>'V115.7 Ask Goliath Fast Lane','error'=>'missing_message']);exit;}
 if(!gdb_enabled()||!gdb()){throw new RuntimeException('Goliath MySQL is not available.');}

 if(!ag111_table('goliath_conversations_v111')||!ag111_table('goliath_messages_v111')){
   throw new RuntimeException('V111 migration tables are missing. Run goliath-v111-migration.php first.');
 }

 $conversation=trim((string)($in['conversation_uid']??''));
 if($conversation==='')$conversation=ag111_uid('conversation');

 if(!ag111_one("SELECT id FROM goliath_conversations_v111 WHERE conversation_uid=? LIMIT 1",[$conversation])){
   gdb_insert('goliath_conversations_v111',['conversation_uid'=>$conversation,'title'=>'Ask Goliath Live','status'=>'active','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 }

 gdb_insert('goliath_messages_v111',[
   'conversation_uid'=>$conversation,'speaker_key'=>'mark','speaker_name'=>'Mark',
   'message_text'=>$message,'message_type'=>'chat','task_id'=>null,
   'metadata_json'=>gdb_json(['voice'=>(bool)($in['voice']??false)]),'created_at'=>gdb_now()
 ]);

 $history=gdb_all("SELECT speaker_name,message_text FROM goliath_messages_v111 WHERE conversation_uid=? AND message_text<>'' ORDER BY id DESC LIMIT 16",[$conversation])?:[];
 $history=array_reverse($history);$historyText='';
 foreach($history as $h)$historyText.=($h['speaker_name']?:'Speaker').": ".$h['message_text']."\n";

 $system="You are Goliath, Mark Pires' live executive operating system and strategic partner.\n".
 "Talk naturally, directly, and conversationally to Mark. Keep normal spoken replies concise.\n".
 "You coordinate Scout, Jessica, Shakespeare, Scorsese, Einstein, Columbo, Prospector, Rockefeller, Pandora, Mozart, and Sherlock.\n".
 "Jessica is invisible externally; all outreach is from Mark Pires.\n".
 "Columbo mines Mark's content archive. Sherlock investigates property ownership, LLCs, probate, trusts, tax records and verifies claims.\n".
 "When Mark requests work, state a short plan and coordinate the appropriate executives. One useful idea should create five valuable outputs.\n".
 "Never say work is complete unless a real deliverable or confirmed result exists.\n";

 $constitution=function_exists('g115_constitution')?g115_constitution('goliath'):(function_exists('g114_constitution')?g114_constitution('goliath'):'');
 $prompt=$constitution."\n\nLIVE CONVERSATION RULES:\n".$system."\nConversation:\n".$historyText."\nGoliath:";

 if(!ag111_table('local_ai_tasks'))throw new RuntimeException('local_ai_tasks table is missing.');
 $cols=ag111_cols('local_ai_tasks');
 $taskRow=[
   'task_uid'=>(function_exists('g115_uid')?g115_uid('ask_goliath'):(function_exists('g114_uid')?g114_uid('ask_goliath'):ag111_uid('ask_goliath'))),
   'task_type'=>'ask_goliath_live_v111',
   'type'=>'ask_goliath_live_v111',
   'model'=>'',
   'prompt'=>$prompt,
   'status'=>'queued',
   'workflow_state'=>'queued',
   'priority'=>2000,
   'agent'=>'Goliath',
   'executive_key'=>'goliath',
   'title'=>'Ask Goliath Live Conversation',
   'metadata'=>gdb_json(['source'=>'mission_control_v111','conversation_uid'=>$conversation,'user_message'=>$message,'voice'=>(bool)($in['voice']??false),'tts_requested'=>(bool)($in['voice']??false),'reply_mode'=>'conversation_only']),
   'metadata_json'=>gdb_json(['source'=>'mission_control_v111','conversation_uid'=>$conversation,'user_message'=>$message,'voice'=>(bool)($in['voice']??false),'tts_requested'=>(bool)($in['voice']??false),'reply_mode'=>'conversation_only']),
   'created_at'=>gdb_now(),
   'updated_at'=>gdb_now()
 ];
 $taskId=ag111_insert_safe('local_ai_tasks',$taskRow,$cols);
 if(!$taskId)throw new RuntimeException('Could not queue the local AI task.');

 gdb_insert('goliath_messages_v111',[
   'conversation_uid'=>$conversation,'speaker_key'=>'goliath','speaker_name'=>'Goliath',
   'message_text'=>'','message_type'=>'pending','task_id'=>$taskId,
   'metadata_json'=>gdb_json(['status'=>'queued']),'created_at'=>gdb_now()
 ]);

 echo json_encode(['ok'=>true,'version'=>'V115.7 Ask Goliath Fast Lane','conversation_uid'=>$conversation,'task_id'=>$taskId,'status'=>'queued','local_ai_columns_used'=>array_values(array_intersect(array_keys($taskRow),array_keys($cols)))],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 $response['error']='caught_exception';
 $response['details']=['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()];
 echo json_encode($response,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>