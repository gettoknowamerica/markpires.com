<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-heartbeat-runtime.php';
header('Content-Type: application/json');
$h=ghr_health();
try{
  if($h['ok']){
    $h['counts']=[
      'heartbeats'=>(int)((gdb_one('SELECT COUNT(*) c FROM executive_heartbeats') ?: ['c'=>0])['c']),
      'heartbeat_events'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_heartbeat_events') ?: ['c'=>0])['c']),
      'ready_for_review'=>(int)((gdb_one("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('review','ready_for_review') OR ready_for_review=1") ?: ['c'=>0])['c'])
    ];
  }
}catch(Throwable $e){ $h['error']=$e->getMessage(); }
echo json_encode($h, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
