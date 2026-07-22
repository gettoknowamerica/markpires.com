<?php
/**
 * V100.1 Scorsese Requeue Invalid Workflow Jobs
 * Reopens Scorsese jobs that failed because workflow_json was invalid/empty.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function v101_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v101_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 if(!v101_table('scorsese_comfy_jobs')){echo json_encode(['ok'=>false,'error'=>'scorsese_comfy_jobs missing']);exit;}

 $limit=max(1,min(500,(int)($_GET['limit']??200)));
 $where="status IN ('failed','error') AND (error_message LIKE '%workflow_json%' OR error_message LIKE '%Invalid workflow%' OR error_message LIKE '%Missing workflow%')";
 $rows=gdb_all("SELECT id,title,status,error_message FROM scorsese_comfy_jobs WHERE {$where} ORDER BY id DESC LIMIT {$limit}")?:[];

 $pdo=gdb();
 $sets=["status='queued'","progress=0","updated_at=NOW()"];
 if(v101_col('scorsese_comfy_jobs','error_message')) $sets[]="error_message=NULL";
 if(v101_col('scorsese_comfy_jobs','remote_prompt_id')) $sets[]="remote_prompt_id=NULL";

 $ids=[];
 foreach($rows as $r){
   $ids[]=(int)$r['id'];
   $sql="UPDATE scorsese_comfy_jobs SET ".implode(',',$sets)." WHERE id=?";
   $st=$pdo->prepare($sql);
   $st->execute([(int)$r['id']]);
 }

 echo json_encode([
   'ok'=>true,
   'version'=>'V100.1 Scorsese Requeue Invalid Workflow Jobs',
   'requeued_count'=>count($ids),
   'requeued_ids'=>$ids,
   'next'=>'Run the V100.1 local workflow builder PowerShell. It builds the Comfy workflow locally from F:\\GoliathOmni\\workflows\\wan_video_template.json.',
   'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.1 Scorsese Requeue Invalid Workflow Jobs','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>