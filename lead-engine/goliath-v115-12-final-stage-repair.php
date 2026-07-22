<?php
// Goliath Omni V115.12 final-stage repair.
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
function f11512_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function f11512_cols(string $t):array{
 $r=gdb_all("SELECT column_name,column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];
 $o=[];foreach($r as $x)$o[(string)$x['column_name']]=$x;return $o;
}
function f11512_enum(array $col,array $preferred,string $fallback):string{
 $ct=(string)($col['column_type']??'');
 if(stripos($ct,'enum(')!==0)return $preferred[0]??$fallback;
 preg_match_all("/'((?:[^'\\]|\\.)*)'/",$ct,$m);
 $vals=array_map(fn($v)=>stripcslashes($v),$m[1]??[]);
 foreach($preferred as $p)if(in_array($p,$vals,true))return $p;
 return in_array($fallback,$vals,true)?$fallback:($vals[0]??$fallback);
}
$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(f11512_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 $taskCols=f11512_cols('local_ai_tasks');
 $missionCols=f11512_cols('goliath_v112_missions');
 $artifactCols=f11512_cols('goliath_v112_artifacts');
 $fixed=[];
 $rows=gdb_all("SELECT s.*,m.title mission_title,m.status mission_status,t.id task_id,t.status task_status,t.result task_result FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id WHERE s.stage_key='goliath_publish_deliver' AND s.status IN ('ready','queued_local','working') ORDER BY s.mission_id")?:[];
 foreach($rows as $s){
  $ts=strtolower((string)($s['task_status']??''));
  if(!in_array($ts,['complete','completed','done','success'],true))continue;
  $raw=(string)($s['task_result']??'');if(trim($raw)==='')continue;
  $j=json_decode($raw,true);if(!is_array($j))$j=[];
  $text=(string)($j['content_text']??$j['output']??$j['answer']??$raw);
  $html=(string)($j['content_html']??'');
  $a=[
   'mission_id'=>(int)$s['mission_id'],'stage_id'=>(int)$s['id'],'executive_key'=>'goliath',
   'artifact_type'=>(string)($j['artifact_type']??'final_delivery_package'),'title'=>(string)($j['title']??$s['mission_title']),
   'content_html'=>$html,'content_text'=>$text,'metadata_json'=>gdb_json(['repair_version'=>'115.12','task_id'=>(int)$s['task_id']]),
   'status'=>'ready_for_founder_review','is_tangible'=>1,'delivered_by_goliath'=>1,'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ];
  $safe=[];foreach($a as $k=>$v)if(isset($artifactCols[$k]))$safe[$k]=$v;
  $aid=(int)gdb_insert('goliath_v112_artifacts',$safe);
  gdb_update('goliath_v112_stages',['status'=>'complete','output_artifact_id'=>$aid,'completed_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>(int)$s['id']]);
  $mu=[];if(isset($missionCols['status']))$mu['status']=f11512_enum($missionCols['status'],['delivered','complete','completed','review','ready_for_review'],'complete');
  foreach(['delivered_at','completed_at','finished_at'] as $c){if(isset($missionCols[$c])){$mu[$c]=gdb_now();break;}}
  foreach(['final_artifact_id','output_artifact_id','delivered_artifact_id'] as $c){if(isset($missionCols[$c])){$mu[$c]=$aid;break;}}
  if(isset($missionCols['updated_at']))$mu['updated_at']=gdb_now();
  if($mu)gdb_update('goliath_v112_missions',$mu,'id=:id',['id'=>(int)$s['mission_id']]);
  $fixed[]=['mission_id'=>(int)$s['mission_id'],'stage_id'=>(int)$s['id'],'task_id'=>(int)$s['task_id'],'artifact_id'=>$aid,'status'=>$mu['status']??'complete'];
 }
 $stmt=gdb()->prepare("UPDATE local_ai_tasks t LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id SET t.status='archived',t.updated_at=NOW() WHERE t.task_type='goliath_v112_stage' AND t.status IN ('queued','working','claimed','dispatched') AND (s.id IS NULL OR s.stage_key='goliath_publish_deliver')");
 $stmt->execute();$archived=$stmt->rowCount();
 echo json_encode(['ok'=>true,'version'=>'V115.12 Final Stage Repair','missions_finalized'=>$fixed,'duplicate_tasks_archived'=>$archived,'next'=>'Restart start-goliath-v115-11.bat. Future final stages are handled by the patched completion endpoint.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'version'=>'V115.12 Final Stage Repair','error'=>'caught_exception','details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>