<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=trim((string)($_GET['key']??''));
$expected=defined('AFTER_HOURS_CRON_KEY')?trim((string)AFTER_HOURS_CRON_KEY):
 (defined('RETELL_WEBHOOK_KEY')?trim((string)RETELL_WEBHOOK_KEY):'timetomakethedonuts');
if(!hash_equals($expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function t118_all($sql,$params=[]){try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}}
function t118_one($sql,$params=[]){try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}}

$reviewStatuses="'ready_for_founder_review','review','approved','published','delivered'";
$finished=t118_one("SELECT COUNT(*) c FROM goliath_v112_artifacts WHERE status IN ($reviewStatuses)");
$today=t118_one("SELECT COUNT(*) c FROM goliath_v112_artifacts WHERE status IN ($reviewStatuses) AND DATE(COALESCE(delivered_at,updated_at,created_at))=CURRENT_DATE");
$active=t118_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE status IN ('queued','working')");
$priorityCount=t118_one("SELECT COUNT(*) c FROM goliath_v112_missions WHERE status IN ('queued','working') AND (mission_type='founder_priority' OR priority>=5000)");

$stages=t118_all(
 "SELECT s.*,m.title mission_title,m.priority,m.originator_key,m.mission_type
  FROM goliath_v112_stages s
  JOIN goliath_v112_missions m ON m.id=s.mission_id
  WHERE s.status IN ('ready','queued_local','working')
  ORDER BY m.priority DESC,m.id ASC,s.stage_no ASC LIMIT 40"
);

$assets=t118_all(
 "SELECT a.id artifact_id,a.mission_id,a.executive_key,a.artifact_type,a.title,a.status,
         a.artifact_url,a.artifact_path,a.created_at,a.updated_at,m.originator_key,m.title mission_title
  FROM goliath_v112_artifacts a
  LEFT JOIN goliath_v112_missions m ON m.id=a.mission_id
  WHERE a.status IN ($reviewStatuses)
  ORDER BY a.id DESC LIMIT 30"
);
foreach($assets as &$asset){
 $asset['review_url']='/dashboard/goliath-review-center.php?artifact_id='.(int)$asset['artifact_id'].'&embed=1';
}
unset($asset);

$priorities=t118_all(
 "SELECT m.id mission_id,m.title,m.originator_key,m.status,m.priority,m.current_stage_no,m.created_at,
         s.executive_key current_executive,s.stage_key,s.title stage_title,s.status stage_status
  FROM goliath_v112_missions m
  JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
  WHERE m.status IN ('queued','working') AND (m.mission_type='founder_priority' OR m.priority>=5000)
  ORDER BY m.priority DESC,m.id DESC LIMIT 12"
);
foreach($priorities as &$priority){
 $priority['url']='/dashboard/goliath-mission-control.php#executive-council';
}
unset($priority);

$byExec=t118_all(
 "SELECT executive_key,
  SUM(status IN ('queued_local','working')) working,
  SUM(status='ready') ready,
  SUM(status='waiting') waiting,
  SUM(status='complete') stage_complete
  FROM goliath_v112_stages GROUP BY executive_key ORDER BY executive_key"
);

$events=t118_all("SELECT * FROM goliath_v112_events ORDER BY id DESC LIMIT 40");

echo json_encode([
 'ok'=>true,
 'version'=>'V118 Truth API',
 'counts'=>[
  'finished_assets'=>(int)($finished['c']??0),
  'finished_today'=>(int)($today['c']??0),
  'active_missions'=>(int)($active['c']??0),
  'founder_priority'=>(int)($priorityCount['c']??0),
  'review_ready'=>(int)($finished['c']??0)
 ],
 'active_stages'=>$stages,
 'review_assets'=>$assets,
 'founder_priority_missions'=>$priorities,
 'executives'=>$byExec,
 'events'=>$events,
 'definition'=>'Founder priorities rank first. Finished means a tangible V112 artifact ready for Founder review, approved, published or delivered.',
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
?>