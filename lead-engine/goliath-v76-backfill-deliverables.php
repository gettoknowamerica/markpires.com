<?php
/**
 * Goliath V76.2 — Backfill old completed worker output into V76 Deliverable Registry.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-v76-operating-system.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function v762_table($t){
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
  catch(Throwable $e){return false;}
}

gv76_install();

if(!v762_table('goliath_worker_completions')){
  echo json_encode(['ok'=>false,'error'=>'goliath_worker_completions_missing'],JSON_PRETTY_PRINT);
  exit;
}

$limit=max(1,min(1000,(int)($_GET['limit']??250)));
$rows=gdb_all("SELECT * FROM goliath_worker_completions ORDER BY created_at ASC, id ASC LIMIT {$limit}");
$created=[];$skipped=[];$errors=[];

foreach($rows as $c){
  $cid=(int)($c['id']??0);
  if(!$cid) continue;
  $exists=gdb_one("SELECT id FROM goliath_deliverables WHERE related_completion_id=? LIMIT 1",[$cid]);
  if($exists){
    $skipped[]=['completion_id'=>$cid,'deliverable_id'=>$exists['id'],'reason'=>'already_backfilled'];
    continue;
  }
  $exec=$c['executive']??'Goliath';
  $title=$c['title']??('Backfilled completion #'.$cid);
  $output=(string)($c['output']??'');

  if(stripos($output,'DELIVERABLE_TYPE:')===false){
    $output="DELIVERABLE_TYPE: legacy_completion\n".
      "EXECUTIVE: {$exec}\n".
      "ACTIONABLE_OUTPUT: Legacy worker completion backfilled into the V76 Deliverable Registry.\n".
      "EVIDENCE: NEEDS_REVIEW - this completion predates V76 evidence enforcement.\n".
      "CLICKABLE_OUTPUTS: /dashboard/executive-office.php?exec=".rawurlencode(strtolower($exec))."&completion={$cid}\n".
      "HANDOFFS: Goliath should review whether this legacy item created a real usable output.\n".
      "NEXT_ACTION: Review this item and either archive it, revise it, or convert it into a real deliverable.\n\n".
      "LEGACY_OUTPUT:\n".$output;
  }

  try{
    $result=gv76_create_deliverable([
      'executive_key'=>$exec,
      'title'=>$title,
      'output'=>$output,
      'completion_id'=>$cid,
      'commission_id'=>$c['commission_id']??null,
      'task_id'=>$c['task_id']??null,
      'priority'=>60
    ]);
    $created[]=['completion_id'=>$cid,'deliverable'=>$result];
  }catch(Throwable $e){
    $errors[]=['completion_id'=>$cid,'error'=>$e->getMessage()];
  }
}

echo json_encode([
  'ok'=>true,
  'version'=>'V76.2 Deliverable Backfill',
  'processed'=>count($rows),
  'created_count'=>count($created),
  'skipped_count'=>count($skipped),
  'error_count'=>count($errors),
  'created'=>array_slice($created,0,20),
  'skipped'=>array_slice($skipped,0,20),
  'errors'=>$errors,
  'next'=>'Open /dashboard/goliath-deliverables.php after this finishes.',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>