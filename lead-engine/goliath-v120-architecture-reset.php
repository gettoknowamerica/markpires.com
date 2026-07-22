<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function r120_key():string{
 if(defined('AFTER_HOURS_CRON_KEY')) return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY')) return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function r120_uid(string $p):string{return $p.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(16));}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(r120_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $patterns=['%HubSpot%','%Supabase%','%Rembrandt%','%Salesforce%','%executive brief%','%creator notes%','%what I would improve%'];
 $quarantined=0;$archived=0;
 foreach($patterns as $pattern){
  $rows=gdb_all("SELECT * FROM local_ai_tasks
    WHERE status IN ('queued','working','claimed','complete','completed','failed')
      AND (COALESCE(prompt,'') LIKE ? OR COALESCE(result,'') LIKE ?)
    ORDER BY id DESC LIMIT 5000",[$pattern,$pattern])?:[];
  foreach($rows as $row){
   try{
    gdb_insert('goliath_system_quarantine',[
     'quarantine_uid'=>r120_uid('quarantine'),'source_table'=>'local_ai_tasks','source_id'=>(int)$row['id'],
     'reason'=>'Legacy/generic architecture contamination: '.$pattern,
     'snapshot_json'=>json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
     'created_at'=>gdb_now()
    ]);
   }catch(Throwable $ignored){}
   if(in_array((string)$row['status'],['queued','working','claimed'],true)){
    gdb_update('local_ai_tasks',[
     'status'=>'archived','workflow_state'=>'archived',
     'error'=>'V120 archived legacy/generic task. Recommission under work-only architecture.',
     'visible_in_production_studio'=>0,'updated_at'=>gdb_now()
    ],'id=:id',['id'=>(int)$row['id']]);
    $archived++;
   }
   $quarantined++;
  }
 }

 $stale=gdb_all("SELECT s.id,s.mission_id,s.stage_no
  FROM goliath_v112_stages s
  JOIN goliath_v112_missions m ON m.id=s.mission_id
  LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
  WHERE m.status IN ('queued','working')
    AND s.stage_no=m.current_stage_no
    AND s.status IN ('queued_local','working')
    AND (t.id IS NULL OR t.status IN ('archived','failed','complete','completed'))")?:[];
 foreach($stale as $s){
  gdb_update('goliath_v112_stages',[
   'status'=>'ready','local_task_id'=>null,'last_error'=>null,'blocking_issue'=>null,'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$s['id']]);
 }

 gdb()->exec("UPDATE goliath_v112_missions
   SET architecture_version='v120-work-only', visible_in_production_studio=1
   WHERE status IN ('queued','working')");

 echo json_encode([
  'ok'=>true,'version'=>'V120 Architecture Reset',
  'legacy_records_quarantined'=>$quarantined,
  'active_legacy_tasks_archived'=>$archived,
  'stranded_stages_reset'=>count($stale),
  'important'=>'Historical bad outputs remain quarantined for audit but are hidden from the production studio and cannot be claimed.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>