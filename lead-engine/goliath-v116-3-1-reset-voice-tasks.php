<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function vr11631_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function vr11631_cols(string $table):array{
 $rows=gdb_all(
  "SELECT column_name FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name=?",
  [$table]
 )?:[];
 $out=[];
 foreach($rows as $row)$out[(string)$row['column_name']]=true;
 return $out;
}

$key=(string)($_GET['key']??'');
if(!hash_equals(vr11631_key(),$key)){
 http_response_code(403);
 echo json_encode(['ok'=>false,'error'=>'bad_key']);
 exit;
}

try{
 $cols=vr11631_cols('local_ai_tasks');
 $sets=["status='queued'"];
 if(isset($cols['workflow_state']))$sets[]="workflow_state='queued'";
 if(isset($cols['claimed_by']))$sets[]="claimed_by=NULL";
 if(isset($cols['progress']))$sets[]="progress=0";
 if(isset($cols['error_message']))$sets[]="error_message=NULL";
 if(isset($cols['error']))$sets[]="error=NULL";
 if(isset($cols['updated_at']))$sets[]="updated_at=NOW()";

 $sql=
  "UPDATE local_ai_tasks
   SET ".implode(',',$sets)."
   WHERE task_type='ask_goliath_live_v111'
   AND status IN ('working','claimed','failed','error')
   AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)";

 $stmt=gdb()->prepare($sql);
 $stmt->execute();

 echo json_encode([
  'ok'=>true,
  'version'=>'V116.3.1 Voice Task Reset',
  'tasks_requeued'=>$stmt->rowCount(),
  'next'=>'Start the V116.3.1 voice runtime.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,
  'version'=>'V116.3.1 Voice Task Reset',
  'error'=>$e->getMessage(),
  'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>