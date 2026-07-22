<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function r1191_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function r1191_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function r1191_update(string $table,array $row,string $where,array $params):int{
 $cols=r1191_cols($table);$safe=[];
 foreach($row as $key=>$value)if(isset($cols[$key]))$safe[$key]=$value;
 return $safe?gdb_update($table,$safe,$where,$params):0;
}
function r1191_json_result(array $data):string{
 return json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?:'{}';
}

$key=trim((string)($_GET['key']??''));
if(!hash_equals(r1191_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$proofMission=max(1,(int)($_GET['proof_mission_id']??184));

try{
 gdb()->beginTransaction();

 $tasks=gdb_all(
  "SELECT id,status,metadata_json,metadata
   FROM local_ai_tasks
   WHERE task_type='goliath_v112_stage'
     AND status IN ('queued','working','claimed','dispatched','failed')
   ORDER BY id ASC"
 )?:[];

 $archived=[];
 foreach($tasks as $task){
  $meta=[];
  foreach(['metadata_json','metadata'] as $column){
   if(empty($task[$column]))continue;
   $decoded=json_decode((string)$task[$column],true);
   if(is_array($decoded)){$meta=$decoded;break;}
  }
  $missionId=(int)($meta['mission_id']??0);
  if($missionId<1)continue;

  r1191_update('local_ai_tasks',[
   'status'=>'archived',
   'workflow_state'=>'archived',
   'result'=>r1191_json_result([
     'status'=>'archived',
     'reason'=>'V119.1 JSON result repair reset',
     'mission_id'=>$missionId,
     'previous_status'=>$task['status'],
     'time'=>date('c')
   ]),
   'error'=>'Archived by V119.1 completion/result repair.',
   'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$task['id']]);
  $archived[]=(int)$task['id'];
 }

 $missions=gdb_all(
  "SELECT m.id,m.current_stage_no,s.id stage_id
   FROM goliath_v112_missions m
   JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   WHERE m.status IN ('queued','working')
   ORDER BY m.priority DESC,m.id ASC"
 )?:[];

 $reset=[];
 foreach($missions as $mission){
  r1191_update('goliath_v112_stages',[
   'status'=>'ready',
   'local_task_id'=>null,
   'blocking_issue'=>null,
   'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$mission['stage_id']]);
  r1191_update('goliath_v112_missions',[
   'status'=>'queued',
   'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$mission['id']]);
  $reset[]=(int)$mission['id'];
 }

 r1191_update('goliath_v112_missions',[
  'priority'=>99999,
  'status'=>'queued',
  'updated_at'=>gdb_now()
 ],'id=:id',['id'=>$proofMission]);

 gdb()->commit();

 echo json_encode([
  'ok'=>true,
  'version'=>'V119.1 JSON Result + Queue Repair',
  'proof_mission_id'=>$proofMission,
  'tasks_archived'=>count($archived),
  'archived_task_ids'=>array_slice($archived,0,50),
  'missions_reset'=>$reset,
  'next'=>'Run the sequential engine once, then restart the production runtime. Mission '.$proofMission.' now has priority 99999.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 if(gdb()->inTransaction())gdb()->rollBack();
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V119.1 JSON Result + Queue Repair',
  'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>