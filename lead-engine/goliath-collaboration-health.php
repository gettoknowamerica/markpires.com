<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-collaboration-engine.php';
header('Content-Type: application/json');
$h=gce_health();
try{
  if($h['ok']){
    $h['counts']=[
      'collaboration_requests'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_collaboration_requests') ?: ['c'=>0])['c']),
      'constitution_checks'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_constitution_checks') ?: ['c'=>0])['c']),
      'teamwork_scores'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_teamwork_scores') ?: ['c'=>0])['c'])
    ];
  }
}catch(Throwable $e){ $h['error']=$e->getMessage(); }
echo json_encode($h, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
