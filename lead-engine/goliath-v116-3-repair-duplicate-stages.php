<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function r1163_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function r1163_cols(string $t):array{
 $r=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];
 $o=[];foreach($r as $x)$o[(string)$x['column_name']]=true;return $o;
}
$key=(string)($_GET['key']??'');
if(!hash_equals(r1163_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $cols=r1163_cols('local_ai_tasks');
 $metaCol=isset($cols['metadata_json'])?'metadata_json':(isset($cols['metadata'])?'metadata':null);
 $missions=gdb_all(
  "SELECT m.id mission_id,m.current_stage_no,s.id stage_id,s.local_task_id,s.executive_key,s.stage_key,s.status
   FROM goliath_v112_missions m
   JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   WHERE m.status IN ('queued','working') ORDER BY m.id"
 )?:[];
 $archived=0;$reset=[];
 foreach($missions as $x){
  $mid=(int)$x['mission_id'];$sno=(int)$x['current_stage_no'];
  if($metaCol){
   $sets=["status='archived'"];
   if(isset($cols['workflow_state']))$sets[]="workflow_state='archived'";
   if(isset($cols['updated_at']))$sets[]="updated_at=NOW()";
   $st=gdb()->prepare(
    "UPDATE local_ai_tasks SET ".implode(',',$sets)."
     WHERE task_type='goliath_v112_stage'
     AND status IN ('queued','working','claimed','dispatched')
     AND JSON_VALID($metaCol)
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT($metaCol,'$.mission_id')) AS UNSIGNED)=?
     AND CAST(JSON_UNQUOTE(JSON_EXTRACT($metaCol,'$.stage_no')) AS UNSIGNED)=?"
   );
   $st->execute([$mid,$sno]);$archived+=$st->rowCount();
  }elseif(!empty($x['local_task_id'])){
   $u=['status'=>'archived'];if(isset($cols['updated_at']))$u['updated_at']=gdb_now();
   gdb_update('local_ai_tasks',$u,'id=:id',['id'=>(int)$x['local_task_id']]);$archived++;
  }
  gdb_update('goliath_v112_stages',[
   'status'=>'ready','local_task_id'=>null,'last_error'=>'Reset by V116.3 duplicate-loop repair','updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$x['stage_id']]);
  $reset[]=['mission_id'=>$mid,'stage_no'=>$sno,'executive_key'=>$x['executive_key'],'stage_key'=>$x['stage_key']];
 }
 echo json_encode([
  'ok'=>true,'version'=>'V116.3 Duplicate Loop Repair','tasks_archived'=>$archived,
  'current_stages_reset'=>$reset,
  'next'=>'Run the sequential engine once, then start both V116.3 runtimes.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V116.3 Duplicate Loop Repair','error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>