<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function one($s){try{return gdb_one($s)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
 function all($s){try{return gdb_all($s)?:[];}catch(Throwable $e){return [['error'=>$e->getMessage()]];}}
 echo json_encode([
  'ok'=>true,'version'=>'V103.0 Runtime Status',
  'runtime'=>one("SELECT * FROM goliath_runtime_state WHERE state_key='universal_runtime' LIMIT 1"),
  'missions'=>one("SELECT COUNT(*) total,SUM(status='proposed') proposed,SUM(status IN ('assigned','working')) active,SUM(status IN ('complete','completed','delivered')) done FROM goliath_missions"),
  'assignments'=>one("SELECT COUNT(*) total,SUM(status='assigned') assigned,SUM(status='working') working FROM executive_mission_assignments"),
  'top10'=>all("SELECT executive_key,COUNT(*) items,MAX(score) top_score FROM executive_top10_boards GROUP BY executive_key ORDER BY executive_key"),
  'recent_runs'=>all("SELECT * FROM goliath_universal_runtime_logs ORDER BY id DESC LIMIT 5"),
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>