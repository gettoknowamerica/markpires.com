<?php
/**
 * V93.2.1 Scout Diagnostics
 * Upload to /public_html/lead-engine/scout-cycle-diagnostic.php
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
  function d_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
  function d_table($t){$r=d_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
  function d_cols($t){try{$rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",[$t])?:[];return array_map(fn($r)=>$r['column_name'],$rows);}catch(Throwable $e){return ['ERROR '.$e->getMessage()];}}
  $tables=['internal_crm_contacts','scout_intel_missions','scout_intel_dossiers','scout_intel_events','local_ai_tasks'];
  $out=[];
  foreach($tables as $t){$out[$t]=['exists'=>d_table($t),'count'=>d_table($t)?(int)(d_one("SELECT COUNT(*) c FROM {$t}")['c']??0):0,'columns'=>d_table($t)?d_cols($t):[]];}
  echo json_encode(['ok'=>true,'version'=>'V93.2.1 Scout Cycle Diagnostic','tables'=>$out,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>