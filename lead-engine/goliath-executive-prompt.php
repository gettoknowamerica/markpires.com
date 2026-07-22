<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';

function out($a,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit; }
$key = $_POST['key'] ?? $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if (!hash_equals($expected, (string)$key)) out(['success'=>false,'error'=>'bad_key'],403);

$prompt = trim((string)($_POST['prompt'] ?? $_GET['prompt'] ?? ''));
if ($prompt === '') out(['success'=>false,'error'=>'missing_prompt'],400);

function tbl($t){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }

$executive = 'goliath';
$lower = strtolower($prompt);
foreach (['jessica','scout','scorsese','mozart','shakespeare','einstein','columbo','prospector','rockefeller','pandora','goliath'] as $name) {
  if (strpos($lower, $name) !== false) { $executive = $name; break; }
}
$title = 'Mark Prompt: '.mb_substr(preg_replace('/\s+/', ' ', $prompt), 0, 80);

$commissionId = null;
if (tbl('executive_commissions')) {
  $commissionId = gdb_insert('executive_commissions', [
    'executive_key' => $executive,
    'title' => $title,
    'status' => 'queued',
    'progress' => 0,
    'metadata' => gdb_json(['source'=>'mission_control_prompt','prompt'=>$prompt])
  ]);
}

$taskId = null;
if (tbl('local_ai_tasks')) {
  $taskId = gdb_insert('local_ai_tasks', [
    'commission_id' => $commissionId,
    'agent' => ucfirst($executive),
    'task_type' => 'mission_control_prompt',
    'status' => 'queued',
    'progress' => 0,
    'priority' => 175,
    'title' => $title,
    'prompt' => $prompt,
    'metadata' => gdb_json(['source'=>'mission_control_prompt','executive'=>$executive])
  ]);
}

if (tbl('goliath_notifications')) {
  @gdb_insert('goliath_notifications', [
    'executive' => ucfirst($executive),
    'title' => 'New Mission Control prompt',
    'message' => $prompt,
    'priority' => 'high',
    'metadata' => gdb_json(['task_id'=>$taskId,'commission_id'=>$commissionId])
  ]);
}

header('Location: /dashboard/goliath-mission-control.php?sent=1');
exit;
