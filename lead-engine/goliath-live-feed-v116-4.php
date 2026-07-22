<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function lf1164_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function lf1164_one(string $sql,array $params=[]):array{
 try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function lf1164_all(string $sql,array $params=[]):array{
 try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function lf1164_cols(string $table):array{
 $rows=lf1164_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}

$key=(string)($_GET['key']??'');
if(!hash_equals(lf1164_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$taskCols=lf1164_cols('local_ai_tasks');
$progressExpr=isset($taskCols['progress'])?'COALESCE(t.progress,0)':'0';
$order=['goliath','scout','jessica','shakespeare','scorsese','einstein','columbo','prospector','rockefeller','pandora','mozart','sherlock'];
$executives=[];

foreach($order as $exec){
 $active=lf1164_one(
  "SELECT m.id mission_id,m.title mission_title,m.status mission_status,m.current_stage_no,m.priority,
          s.id stage_id,s.title stage_title,s.stage_key,s.status stage_status,s.updated_at,
          t.id task_id,t.status task_status,$progressExpr task_progress
   FROM goliath_v112_missions m
   JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
   WHERE LOWER(s.executive_key)=? AND m.status IN ('queued','working')
   ORDER BY m.priority DESC,m.updated_at DESC LIMIT 1",
  [$exec]
 );
 $counts=lf1164_one(
  "SELECT COUNT(*) total_count,
          SUM(status IN ('queued','working')) active_count,
          SUM(status IN ('complete','completed','delivered','review','ready_for_review')) complete_count
   FROM goliath_v112_missions WHERE LOWER(originator_key)=?",
  [$exec]
 );
 $reviewCount=(int)(lf1164_one(
  "SELECT COUNT(*) c FROM goliath_v112_artifacts
   WHERE LOWER(executive_key)=? AND status IN ('ready_for_founder_review','review','approved','published')",
  [$exec]
 )['c']??0);

 $progress=0;$status='ready';$mode='standing by';$action='Ready for mission';$heartbeat=null;
 if($active){
  $stageStatus=strtolower((string)($active['stage_status']??''));
  $taskStatus=strtolower((string)($active['task_status']??''));
  $progress=(int)($active['task_progress']??0);
  if($progress<=0){
   if($stageStatus==='ready')$progress=5;
   elseif($stageStatus==='queued_local')$progress=10;
   elseif($stageStatus==='working'||$taskStatus==='working')$progress=35;
   elseif($stageStatus==='complete')$progress=100;
  }
  $status=in_array($stageStatus,['working','queued_local','ready'],true)?'active':$stageStatus;
  $mode=(string)($active['stage_key']??'active');
  $action=(string)($active['stage_title']??$active['mission_title']??'Working');
  $heartbeat=$active['updated_at']??null;
 }
 $executives[]=[
  'executive_key'=>$exec,'display_name'=>ucfirst($exec),'status'=>$status,
  'current_mode'=>$mode,'current_action'=>$action,'progress'=>max(0,min(100,$progress)),
  'active_count'=>(int)($counts['active_count']??0),'total_count'=>(int)($counts['total_count']??0),
  'complete_count'=>(int)($counts['complete_count']??0),'review_count'=>$reviewCount,
  'last_heartbeat_at'=>$heartbeat
 ];
}

$reviewItems=lf1164_all(
 "SELECT a.id artifact_id,a.mission_id,a.executive_key,a.artifact_type,a.title,a.content_text,
         a.content_html,a.artifact_url,a.artifact_path,a.status,a.created_at,m.originator_key,m.title mission_title
  FROM goliath_v112_artifacts a
  LEFT JOIN goliath_v112_missions m ON m.id=a.mission_id
  WHERE a.status IN ('ready_for_founder_review','review','approved','published')
  ORDER BY a.id DESC LIMIT 30"
);
$reviewOut=[];
foreach($reviewItems as $item){
 $preview=trim(strip_tags((string)($item['content_html']?:$item['content_text'])));
 $reviewOut[]=[
  'artifact_id'=>(int)$item['artifact_id'],
  'mission_id'=>(int)($item['mission_id']??0),
  'executive_key'=>(string)($item['executive_key']??$item['originator_key']??'goliath'),
  'title'=>(string)($item['title']??$item['mission_title']??'Completed asset'),
  'artifact_type'=>(string)($item['artifact_type']??'deliverable'),
  'status'=>(string)($item['status']??'ready_for_founder_review'),
  'preview'=>mb_substr($preview,0,220),
  'url'=>'/dashboard/goliath-review-center.php?artifact_id='.(int)$item['artifact_id'].'&embed=1',
  'created_at'=>$item['created_at']??null
 ];
}

$events=lf1164_all(
 "SELECT id,executive_key,event_type,title,details,url,artifact_id,created_at
  FROM goliath_v112_events ORDER BY id DESC LIMIT 40"
);
$eventOut=[];
foreach($events as $event){
 $url=(string)($event['url']??'');
 if(!empty($event['artifact_id']))$url='/dashboard/goliath-review-center.php?artifact_id='.(int)$event['artifact_id'].'&embed=1';
 if($url===''||$url==='/dashboard/goliath-mission-control.php')$url='/dashboard/goliath-review-center.php';
 $eventOut[]=[
  'id'=>(int)$event['id'],'executive_key'=>(string)($event['executive_key']??'goliath'),
  'title'=>(string)($event['title']??'Executive update'),'details'=>(string)($event['details']??''),
  'url'=>$url,'status'=>strtoupper((string)($event['event_type']??'live')),
  'icon'=>'⚡','created_at'=>$event['created_at']??null
 ];
}

$counts=[
 'missions'=>lf1164_one("SELECT COUNT(*) total,SUM(status IN ('queued','working')) active,SUM(status IN ('complete','completed','delivered','review','ready_for_review')) completed FROM goliath_v112_missions"),
 'stages'=>lf1164_one("SELECT COUNT(*) total,SUM(status IN ('ready','queued_local','working')) active,SUM(status='complete') completed FROM goliath_v112_stages"),
 'artifacts'=>lf1164_one("SELECT COUNT(*) total,SUM(is_tangible=1) tangible,SUM(status IN ('ready_for_founder_review','review','approved','published')) reviewable FROM goliath_v112_artifacts")
];

echo json_encode([
 'ok'=>true,'version'=>'V116.5 Live OS Feed','executives'=>$executives,
 'events'=>$eventOut,'review_items'=>$reviewOut,'counts'=>$counts,'server_time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>