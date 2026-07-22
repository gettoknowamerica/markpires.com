<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-knowledge-vault.php';
header('Content-Type: application/json');
$h=gkv_health();
try{
  if($h['ok']){
    $h['counts']=[
      'knowledge_assets'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_knowledge_assets')?:['c'=>0])['c']),
      'timeline_events'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_relationship_timeline')?:['c'=>0])['c']),
      'compounding_queue'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_asset_compounding_queue')?:['c'=>0])['c']),
      'daily_digests'=>(int)((gdb_one('SELECT COUNT(*) c FROM goliath_knowledge_vault_daily_digest')?:['c'=>0])['c'])
    ];
  }
}catch(Throwable $e){ $h['error']=$e->getMessage(); }
echo json_encode($h, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
