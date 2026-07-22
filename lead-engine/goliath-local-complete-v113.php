<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
 function lc111_key():string{if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;return 'timetomakethedonuts';}
 function lc111_cols(string $t):array{$rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];$o=[];foreach($rows as $r)$o[$r['column_name']]=true;return $o;}
 $in=json_decode(file_get_contents('php://input'),true);if(!is_array($in))$in=$_POST;
 $key=(string)($in['key']??'');if(!hash_equals(lc111_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $id=(int)($in['task_id']??0);if(!$id){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_task_id']);exit;}
 $status=strtolower((string)($in['status']??'completed'));$result=(string)($in['result']??'');$error=(string)($in['error_message']??'');
 $cols=lc111_cols('local_ai_tasks');$row=[];
 if(isset($cols['status']))$row['status']=$status;if(isset($cols['workflow_state']))$row['workflow_state']=in_array($status,['complete','completed','done','success'])?'completed':'failed';
 if(isset($cols['progress']))$row['progress']=in_array($status,['complete','completed','done','success'])?100:0;
 if(isset($cols['result']))$row['result']=$result;elseif(isset($cols['output']))$row['output']=$result;elseif(isset($cols['response']))$row['response']=$result;
 if(isset($cols['error_message']))$row['error_message']=$error;elseif(isset($cols['error']))$row['error']=$error;
 if(isset($cols['updated_at']))$row['updated_at']=gdb_now();
 if(!$row)throw new RuntimeException('No compatible local_ai_tasks completion columns.');
 gdb_update('local_ai_tasks',$row,'id=:id',['id'=>$id]);
 echo json_encode(['ok'=>true,'version'=>'V113.0 Local Complete','task_id'=>$id,'status'=>$status],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'version'=>'V113.0 Local Complete','error'=>'caught_exception','details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>