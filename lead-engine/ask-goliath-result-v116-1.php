<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 function ar1161_key():string{
   if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
   if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
   return 'timetomakethedonuts';
 }
 function ar1161_cols(string $t):array{
   $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];
   $o=[];foreach($rows as $r)$o[(string)$r['column_name']]=true;return $o;
 }

 $key=(string)($_GET['key']??'');
 if(!hash_equals(ar1161_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 $conversation=trim((string)($_GET['conversation_uid']??''));
 $after=max(0,(int)($_GET['after_id']??0));
 if($conversation===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_conversation_uid']);exit;}

 $cols=ar1161_cols('local_ai_tasks');
 $resultCol=isset($cols['result'])?'result':(isset($cols['output'])?'output':(isset($cols['response'])?'response':null));
 $errorCol=isset($cols['error_message'])?'error_message':(isset($cols['error'])?'error':null);

 $pending=gdb_all(
   "SELECT id,task_id FROM goliath_messages_v111
    WHERE conversation_uid=? AND message_type='pending' AND task_id IS NOT NULL
    ORDER BY id ASC",
   [$conversation]
 )?:[];

 $taskDiagnostics=[];
 foreach($pending as $p){
   $fields=['id','status','task_type','updated_at'];
   if($resultCol)$fields[]=$resultCol;
   if($errorCol)$fields[]=$errorCol;
   $task=gdb_one("SELECT ".implode(',',$fields)." FROM local_ai_tasks WHERE id=? LIMIT 1",[(int)$p['task_id']]);
   if(!$task)continue;

   $status=strtolower((string)($task['status']??''));
   $diag=['task_id'=>(int)$task['id'],'status'=>$status,'updated_at'=>$task['updated_at']??null,'audio_url'=>''];

   if(in_array($status,['complete','completed','done','success'],true)){
     $raw=$resultCol?(string)($task[$resultCol]??''):'';
     $answer=$raw;$audioUrl='';
     $j=json_decode($raw,true);
     if(is_array($j)){
       $answer=(string)($j['content_text']??$j['content_html']??$j['output']??$j['answer']??$j['response']??$j['content']??$raw);
       $audioUrl=(string)($j['audio_url']??'');
     }
     if(trim($answer)==='')$answer='Goliath completed the turn without returning text.';
     gdb_update('goliath_messages_v111',[
       'message_text'=>$answer,'message_type'=>'chat',
       'metadata_json'=>gdb_json([
         'status'=>$status,'audio_url'=>$audioUrl,'task_id'=>(int)$p['task_id'],
         'voice_engine'=>$audioUrl!==''?'kokoro':'none','version'=>'116.1'
       ])
     ],'id=:id',['id'=>(int)$p['id']]);
     $diag['audio_url']=$audioUrl;
   }elseif(in_array($status,['failed','error'],true)){
     $err=$errorCol?(string)($task[$errorCol]??'Unknown worker error'):'Unknown worker error';
     gdb_update('goliath_messages_v111',[
       'message_text'=>'Local service error: '.$err,'message_type'=>'error',
       'metadata_json'=>gdb_json(['status'=>$status,'task_id'=>(int)$p['task_id'],'version'=>'116.1'])
     ],'id=:id',['id'=>(int)$p['id']]);
   }
   $taskDiagnostics[]=$diag;
 }

 $messages=gdb_all(
   "SELECT id,speaker_key,speaker_name,message_text,message_type,metadata_json,created_at
    FROM goliath_messages_v111
    WHERE conversation_uid=? AND id>?
    ORDER BY id ASC LIMIT 100",
   [$conversation,$after]
 )?:[];

 $last=$after;$hasPending=false;
 foreach($messages as &$m){
   $meta=json_decode((string)($m['metadata_json']??''),true);
   $m['audio_url']=is_array($meta)?(string)($meta['audio_url']??''):'';
   $m['voice_engine']=is_array($meta)?(string)($meta['voice_engine']??''):'';
   unset($m['metadata_json']);
   if(($m['message_type']??'')==='pending'){$hasPending=true;continue;}
   if(trim((string)($m['message_text']??''))!=='')$last=max($last,(int)$m['id']);
 }
 unset($m);

 echo json_encode([
   'ok'=>true,'version'=>'V116.1 Ask Goliath Result',
   'messages'=>$messages,'last_id'=>$last,'has_pending'=>$hasPending,
   'tasks'=>$taskDiagnostics,'retry_ms'=>$hasPending?250:900,'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
   'ok'=>false,'version'=>'V116.1 Ask Goliath Result','error'=>'caught_exception',
   'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>