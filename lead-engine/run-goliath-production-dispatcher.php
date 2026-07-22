<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/goliath-v75-3-production-engine.php';
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit; }
$limit=max(1,min(30,(int)($_GET['limit']??10)));
$force=isset($_GET['force']) && $_GET['force']=='1';
$exec=isset($_GET['exec']) ? strtolower(trim($_GET['exec'])) : '';
$installed=g753_install_schema();
$missions=g753_seed_missions(isset($_GET['update']) && $_GET['update']=='1');
$tools=g753_seed_tools(isset($_GET['update']) && $_GET['update']=='1');
$dispatch=g753_dispatch($limit,$force,$exec);
$award=(isset($_GET['award']) && $_GET['award']=='1') ? g753_award_daily() : null;
echo json_encode([
  'ok'=>true,
  'version'=>'V75.3 Production Dispatcher + Executive Mission Engine',
  'installed'=>$installed,
  'missions'=>$missions,
  'tools'=>$tools,
  'force'=>$force,
  'exec_filter'=>$exec ?: null,
  'commissions_created'=>count($dispatch['created']??[]),
  'created'=>$dispatch['created']??[],
  'skipped'=>array_slice($dispatch['skipped']??[],0,50),
  'award'=>$award,
  'next'=>'Keep the desktop local worker polling local-ai-task-pull.php. It will pull these production commissions.',
  'cron'=>'/lead-engine/run-goliath-production-dispatcher.php?key=YOUR_KEY&limit=10',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
