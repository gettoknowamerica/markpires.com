<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function lf1161_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function lf1161_one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
function lf1161_all($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function lf1161_cols($t){$r=lf1161_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t]);$o=[];foreach($r as $x)$o[(string)$x['column_name']]=true;return $o;}

$key=(string)($_GET['key']??'');
if(!hash_equals(lf1161_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$taskCols=lf1161_cols('local_ai_tasks');
$progressSelect=isset($taskCols['progress'])?'COALESCE(t.progress,0)':'0';
$order=['goliath','scout','jessica','shakespeare','scorsese','einstein','columbo','prospector','rockefeller','pandora','mozart','sherlock'];
$executives=[];

foreach($order as $exec){
 $active=lf1161_one(
  "SELECT m.id mission_id,m.title mission_title,m.status mission_status,m.current_stage_no,m.priority,
          s.id stage_id,s.title stage_title,s.stage_key,s.status stage_status,s.updated_at,
          t.id task_id,t.status task_status,$progressSelect task_progress
   FROM goliath_v112_missions m
   JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
   LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
   WHERE LOWER(s.executive_key)=? AND m.status IN ('queued','working')
   ORDER BY m.priority DESC,m.updated_at DESC LIMIT 1",
  [$exec]
 );
 $counts=lf1161_one(
  "SELECT COUNT(*) total_count,
          SUM(status IN ('queued','working')) active_count,
          SUM(status IN ('complete','completed','delivered','review','ready_for_review')) complete_count
   FROM goliath_v112_missions WHERE LOWER(originator_key)=?",
  [$exec]
 );

 $progress=0;$status='ready';$mode='standing by';$action='Ready for mission';$heartbeat=null;
 if($active){
   $ss=strtolower((string)($active['stage_status']??''));
   $ts=strtolower((string)($active['task_status']??''));
   $progress=(int)($active['task_progress']??0);
   if($progress<=0){
     if($ss==='ready')$progress=5;
     elseif($ss==='queued_local')$progress=10;
     elseif($ss==='working'||$ts==='working')$progress=35;
     elseif($ss==='complete')$progress=100;
   }
   $status=in_array($ss,['working','queued_local','ready'],true)?'active':$ss;
   $mode=(string)($active['stage_key']??'active');
   $action=(string)($active['stage_title']??$active['mission_title']??'Working');
   $heartbeat=$active['updated_at']??null;
 }
 $executives[]=[
   'executive_key'=>$exec,'display_name'=>ucfirst($exec),'status'=>$status,
   'current_mode'=>$mode,'current_action'=>$action,'progress'=>max(0,min(100,$progress)),
   'active_count'=>(int)($counts['active_count']??0),'total_count'=>(int)($counts['total_count']??0),
   'complete_count'=>(int)($counts['complete_count']??0),'last_heartbeat_at'=>$heartbeat
 ];
}

$events=lf1161_all(
 "SELECT id,executive_key,event_type,title,details,url,created_at
  FROM goliath_v112_events ORDER BY id DESC LIMIT 40"
);
$eventOut=[];
foreach($events as $e)$eventOut[]=[
 'id'=>(int)$e['id'],'executive_key'=>(string)($e['executive_key']??'goliath'),
 'title'=>(string)($e['title']??'Executive update'),'details'=>(string)($e['details']??''),
 'url'=>(string)($e['url']??'/dashboard/goliath-mission-control.php'),
 'status'=>strtoupper((string)($e['event_type']??'live')),'icon'=>'⚡','created_at'=>$e['created_at']??null
];

$counts=[
 'missions'=>lf1161_one("SELECT COUNT(*) total,SUM(status IN ('queued','working')) active,SUM(status IN ('complete','completed','delivered','review','ready_for_review')) completed FROM goliath_v112_missions"),
 'stages'=>lf1161_one("SELECT COUNT(*) total,SUM(status IN ('ready','queued_local','working')) active,SUM(status='complete') completed FROM goliath_v112_stages"),
 'artifacts'=>lf1161_one("SELECT COUNT(*) total,SUM(is_tangible=1) tangible,SUM(status IN ('ready_for_founder_review','review','approved','published')) reviewable FROM goliath_v112_artifacts")
];

echo json_encode([
 'ok'=>true,'version'=>'V116.1 Live OS Feed','executives'=>$executives,'events'=>$eventOut,
 'counts'=>$counts,'server_time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>