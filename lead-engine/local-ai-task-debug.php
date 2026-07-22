<?php
/**
 * Goliath V70.6 — Local Worker Queue Debug
 * Shows what Hermes/OpenClaw can see in the Hostinger runtime.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Invalid key']);exit;}
function gwdbg_table($table){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gwdbg_count($sql,$params=[]){try{$r=gdb_one($sql,$params);return (int)($r['c']??0);}catch(Throwable $e){return null;}}
$out=['ok'=>gdb_enabled(),'configured'=>gdb_enabled(),'tables'=>[],'counts'=>[],'latest'=>[],'time'=>date('c')];
$tables=['executive_commissions','executive_heartbeats','local_ai_tasks','goliath_worker_completions','goliath_review_queue','goliath_notifications'];
foreach($tables as $t){$out['tables'][$t]=gwdbg_table($t);} 
if($out['tables']['executive_commissions']){
  foreach(['queued','claimed','working','review','ready_for_review','complete','completed','blocked','failed'] as $s){
    $out['counts']['commissions_'.$s]=gwdbg_count('SELECT COUNT(*) c FROM executive_commissions WHERE status=?',[$s]);
  }
  $out['counts']['pull_eligible']=gwdbg_count("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100");
  $out['latest']['eligible']=gdb_all("SELECT id, executive_key, title, status, progress, current_step, updated_at FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100 ORDER BY FIELD(status,'working','review','ready_for_review','claimed','queued'), priority DESC, updated_at ASC LIMIT 10");
  $out['latest']['recent']=gdb_all("SELECT id, executive_key, title, status, progress, current_step, updated_at FROM executive_commissions ORDER BY updated_at DESC LIMIT 10");
}
if($out['tables']['local_ai_tasks']){
  foreach(['queued','working','completed','failed'] as $s){$out['counts']['local_ai_'.$s]=gwdbg_count('SELECT COUNT(*) c FROM local_ai_tasks WHERE status=?',[$s]);}
  $out['latest']['local_ai_tasks']=gdb_all('SELECT id, commission_id, agent, task_type, status, progress, updated_at FROM local_ai_tasks ORDER BY updated_at DESC LIMIT 10');
}
if($out['tables']['goliath_worker_completions']){$out['counts']['worker_completions']=gwdbg_count('SELECT COUNT(*) c FROM goliath_worker_completions');}
if($out['tables']['goliath_review_queue']){$out['counts']['review_ready']=gwdbg_count("SELECT COUNT(*) c FROM goliath_review_queue WHERE review_status IN ('ready','open','pending')");}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
