<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-runtime-sync.php';
header('Content-Type: application/json');
$h=grs_health();
try{
  if($h['ok']){
    $h['counts']=[
      'runtime_snapshots'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_runtime_snapshots') ?: ['c'=>0])['c']),
      'runtime_events'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_runtime_events') ?: ['c'=>0])['c'])
    ];
  }
}catch(Throwable $e){ $h['error']=$e->getMessage(); }
echo json_encode($h, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
