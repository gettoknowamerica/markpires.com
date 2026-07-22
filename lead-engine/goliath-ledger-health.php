<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-action-ledger.php';
header('Content-Type: application/json');
$tables=['goliath_crm_action_ledger','goliath_executive_daily_tallies','goliath_review_queue','goliath_notifications','executive_commissions','executive_heartbeats','plugin_jobs'];
$out=['ok'=>gdb_enabled(),'configured'=>gdb_enabled(),'tables'=>[],'time'=>date('c')];
foreach($tables as $t){
  $exists=gal_has_table($t);
  $count=null;
  if($exists){ try{$r=gdb_one("SELECT COUNT(*) c FROM `$t`"); $count=(int)($r['c']??0);}catch(Throwable $e){} }
  $out['tables'][$t]=['exists'=>$exists,'count'=>$count];
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
