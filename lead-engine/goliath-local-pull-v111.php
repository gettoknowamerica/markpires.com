<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
 function lp111_key():string{if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;return 'timetomakethedonuts';}
 function lp111_cols(string $t):array{$rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];$o=[];foreach($rows as $r)$o[$r['column_name']]=true;return $o;}
 $key=(string)($_GET['key']??$_POST['key']??'');if(!hash_equals(lp111_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $cols=lp111_cols('local_ai_tasks');if(!$cols)throw new RuntimeException('local_ai_tasks table unavailable.');
 $where=[];$params=[];
 if(isset($cols['status']))$where[]="status='queued'";
 if(isset($cols['workflow_state']))$where[]="workflow_state IN ('queued','dispatched')";
 $whereSql=$where?'WHERE ('.implode(' OR ',$where).')':'';
 $order=[];if(isset($cols['priority']))$order[]='priority DESC';if(isset($cols['created_at']))$order[]='created_at ASC';$order[]='id ASC';
 $task=gdb_one("SELECT * FROM local_ai_tasks $whereSql ORDER BY ".implode(',',$order)." LIMIT 1");
 if(!$task){echo json_encode(['ok'=>true,'task'=>null]);exit;}
 $update=[];if(isset($cols['status']))$update['status']='working';if(isset($cols['workflow_state']))$update['workflow_state']='claimed';if(isset($cols['progress']))$update['progress']=5;if(isset($cols['updated_at']))$update['updated_at']=gdb_now();if(isset($cols['claimed_by']))$update['claimed_by']='goliath-v111-bridge';
 if($update)gdb_update('local_ai_tasks',$update,'id=:id',['id'=>(int)$task['id']]);
 $task=array_merge($task,$update);
 echo json_encode(['ok'=>true,'version'=>'V111.1 Local Pull','task'=>$task],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'version'=>'V111.1 Local Pull','error'=>'caught_exception','details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>