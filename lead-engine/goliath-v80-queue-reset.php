<?php
/**
 * Goliath V80 — Queue Reset for asset contract regeneration
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$mode=$_GET['mode']??'stale';
$updated=0;
try{
  if($mode==='stale'){
    gdb_exec("UPDATE local_ai_tasks SET status='queued', progress=0, updated_at=NOW() WHERE status='working' AND progress<=10 AND updated_at < (NOW() - INTERVAL 10 MINUTE)");
    $updated=(int)((gdb_one("SELECT ROW_COUNT() c")?:['c'=>0])['c']);
  } elseif($mode==='all_working'){
    gdb_exec("UPDATE local_ai_tasks SET status='queued', progress=0, updated_at=NOW() WHERE status='working' AND progress<100");
    $updated=(int)((gdb_one("SELECT ROW_COUNT() c")?:['c'=>0])['c']);
  }
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);exit;}
echo json_encode(['ok'=>true,'version'=>'V80 Queue Reset','mode'=>$mode,'updated'=>$updated,'next'=>'Restart local worker or wait for next loop. It should pull V80 asset-contract tasks.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>