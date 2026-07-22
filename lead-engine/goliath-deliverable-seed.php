<?php
/**
 * V52 backfill helper: creates deliverables from recent completed local_ai_tasks.
 * Use once after installing V52 if the local worker already completed tasks before this engine was installed.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-deliverables-lib.php';
$key = $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if (!hash_equals($expected, $key)) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'bad_key']); exit; }
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$res = gd_req('GET', 'local_ai_tasks?select=*&status=eq.completed&order=updated_at.desc&limit=' . $limit);
if (!$res['ok']) { echo json_encode(['success'=>false,'stage'=>'load_completed','error'=>$res], JSON_PRETTY_PRINT); exit; }
$made=[];
foreach (($res['data'] ?? []) as $task) {
  $m = $task['metadata'] ?? [];
  if (is_string($m)) $m = json_decode($m,true) ?: [];
  $agent = $m['agent'] ?? ($task['agent'] ?? 'Goliath');
  $out = $task['output'] ?? '';
  if (!$out && isset($task['result'])) {
    $r = is_string($task['result']) ? json_decode($task['result'], true) : $task['result'];
    $out = is_array($r) ? ($r['output'] ?? json_encode($r, JSON_PRETTY_PRINT)) : (string)$task['result'];
  }
  if (!$out) $out = 'Completed before V52 output capture. Re-run this agent for full deliverable text.';
  $d = gd_create_deliverable($agent, $task['id'] ?? null, 'completed', $out, $m);
  $made[] = ['task_id'=>$task['id'] ?? null, 'agent'=>$agent, 'ok'=>$d['ok']];
}
echo json_encode(['success'=>true,'seeded'=>count($made),'items'=>$made], JSON_PRETTY_PRINT);
