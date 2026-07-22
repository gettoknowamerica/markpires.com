<?php
/**
 * Goliath V75 — Autonomous Dispatcher
 * Run manually or by Hostinger cron every 5-15 minutes.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/goliath-v75-mission-engine.php';
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$limit=max(1,min(20,(int)($_GET['limit']??10)));
$installed=gv75_install_schema();
$missions_seeded=gv75_seed_missions();
$dispatch=gv75_dispatch($limit);
$award=null;
if(($_GET['award']??'')==='1') $award=gv75_award_daily();
echo json_encode([
  'ok'=>true,
  'version'=>'V75 autonomous executive mission engine',
  'installed'=>$installed,
  'missions_seeded'=>$missions_seeded,
  'commissions_created'=>count($dispatch['created']??[]),
  'created'=>$dispatch['created']??[],
  'skipped'=>array_slice($dispatch['skipped']??[],0,30),
  'award'=>$award,
  'next'=>'Keep the local Goliath worker running. It will pull any created commissions on its next poll.',
  'cron'=>'/lead-engine/run-goliath-autonomous-dispatcher.php?key=YOUR_KEY&limit=10',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
