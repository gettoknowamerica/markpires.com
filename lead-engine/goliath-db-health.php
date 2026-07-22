<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
header('Content-Type: application/json');
$out=['configured'=>gdb_enabled(),'connected'=>false,'tables'=>[]];
$db=gdb();
if($db){
  $out['connected']=true;
  foreach(['goliath_contacts','leads','executive_commissions','executive_heartbeats','plugin_jobs','executive_notifications','executive_deliverables','einstein_compounding_queue'] as $t){
    try{$out['tables'][$t]=(int)$db->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();}catch(Throwable $e){$out['tables'][$t]='ERROR: '.$e->getMessage();}
  }
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
