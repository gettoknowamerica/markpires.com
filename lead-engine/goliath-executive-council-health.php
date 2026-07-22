<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-executive-council.php';
header('Content-Type: application/json');
$h=gec_health();
try{
  if($h['ok']){
    $h['counts']=[
      'council_reports'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_executive_council_reports')?:['c'=>0])['c']),
      'morning_briefs'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_morning_briefs')?:['c'=>0])['c']),
      'council_action_items'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_council_action_items')?:['c'=>0])['c'])
    ];
  }
}catch(Throwable $e){ $h['error']=$e->getMessage(); }
echo json_encode($h, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
