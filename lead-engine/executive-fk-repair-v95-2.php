<?php
/**
 * V95.2 Foreign Key Repair
 * Converts invalid 0 IDs to NULL and cleans orphan FK IDs in executive_deliverables.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
  }

  function v952_exec($sql){
    if(function_exists('gdb_exec')) return gdb_exec($sql);
    $pdo=gdb(); return $pdo->exec($sql);
  }
  function v952_table($t){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function v952_col($t,$c){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }

  $actions=[];

  if(v952_table('executive_deliverables')){
    foreach(['commission_id','task_id','browser_job_id','source_id'] as $c){
      if(v952_col('executive_deliverables',$c)){
        v952_exec("UPDATE executive_deliverables SET `$c`=NULL WHERE `$c`=0");
        $actions[]="executive_deliverables.$c zero_to_null";
      }
    }

    if(v952_col('executive_deliverables','commission_id') && v952_table('executive_commissions')){
      v952_exec("UPDATE executive_deliverables d LEFT JOIN executive_commissions c ON c.id=d.commission_id SET d.commission_id=NULL WHERE d.commission_id IS NOT NULL AND c.id IS NULL");
      $actions[]="orphan commission_id nullified";
    }
    if(v952_col('executive_deliverables','task_id') && v952_table('local_ai_tasks')){
      v952_exec("UPDATE executive_deliverables d LEFT JOIN local_ai_tasks t ON t.id=d.task_id SET d.task_id=NULL WHERE d.task_id IS NOT NULL AND t.id IS NULL");
      $actions[]="orphan task_id nullified";
    }
    if(v952_col('executive_deliverables','browser_job_id') && v952_table('goliath_browser_jobs')){
      v952_exec("UPDATE executive_deliverables d LEFT JOIN goliath_browser_jobs b ON b.id=d.browser_job_id SET d.browser_job_id=NULL WHERE d.browser_job_id IS NOT NULL AND b.id IS NULL");
      $actions[]="orphan browser_job_id nullified";
    }
  }

  $counts=[
    'deliverables'=>(v952_table('executive_deliverables')?(int)(gdb_one("SELECT COUNT(*) c FROM executive_deliverables")['c']??0):0),
    'zero_commissions'=>(v952_table('executive_deliverables')&&v952_col('executive_deliverables','commission_id')?(int)(gdb_one("SELECT COUNT(*) c FROM executive_deliverables WHERE commission_id=0")['c']??0):0)
  ];

  echo json_encode([
    'ok'=>true,
    'version'=>'V95.2 Foreign Key Repair',
    'actions'=>$actions,
    'counts'=>$counts,
    'next'=>'Upload the V95.2 executive-engine.php and executive-dispatcher.php, then rerun dispatcher.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V95.2 Foreign Key Repair','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>