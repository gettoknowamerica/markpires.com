<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function s1192_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function s1192_cols(string $table):array{
 $rows=gdb_all("SELECT column_name,column_type,is_nullable,column_default,extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=$r;return $out;
}
function s1192_default(string $column,string $type){
 $n=strtolower($column);$t=strtolower($type);
 if(str_contains($n,'uid'))return 'auto_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(12));
 if(str_contains($n,'status'))return 'created';
 if(str_contains($n,'type'))return 'artifact';
 if(str_contains($n,'key'))return 'goliath';
 if(str_contains($t,'int')||str_contains($t,'decimal'))return 0;
 if(str_contains($t,'date')||str_contains($t,'time'))return gdb_now();
 return '';
}
function s1192_insert(string $table,array $row):int{
 $cols=s1192_cols($table);$safe=[];
 foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 foreach($cols as $c=>$d){
  if(array_key_exists($c,$safe)||strtolower((string)$d['is_nullable'])==='yes'||$d['column_default']!==null||str_contains(strtolower((string)$d['extra']),'auto_increment'))continue;
  $safe[$c]=s1192_default($c,(string)$d['column_type']);
 }
 return (int)gdb_insert($table,$safe);
}
function s1192_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));}
function s1192_payload(array $row):string{
 $html=trim((string)($row['content_html']??''));$text=trim((string)($row['content_text']??''));
 return $html!==''?$html:$text;
}
function s1192_plugins(string $exec):string{
 $path=__DIR__.'/goliath-plugin-registry-v118-3.json';
 $data=is_file($path)?json_decode((string)file_get_contents($path),true):[];
 if(!is_array($data))return '';
 $name=ucfirst(strtolower($exec));$keys=$data['executive_mappings'][$name]??[];
 $indexed=[];foreach(($data['plugins']??[]) as $plugin)$indexed[$plugin['key']]=$plugin;
 $lines=[];foreach($keys as $key)if(isset($indexed[$key]))$lines[]='- '.$indexed[$key]['name'].': '.$indexed[$key]['capability'];
 return implode("\n",$lines);
}
function s1192_clone_final(array $stage,array $source):array{
 $missionId=(int)$stage['mission_id'];$stageNo=(int)$stage['stage_no'];
 gdb()->beginTransaction();
 try{
  $versionId=s1192_insert('goliath_v118_asset_versions',[
   'version_uid'=>s1192_uid('version'),'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],
   'stage_no'=>$stageNo,'executive_key'=>'goliath','artifact_type'=>$source['artifact_type']??'document',
   'title'=>$source['title']??$stage['mission_title'],
   'content_html'=>$source['content_html']??'','content_text'=>$source['content_text']??'',
   'artifact_url'=>$source['artifact_url']??null,'artifact_path'=>$source['artifact_path']??null,
   'change_note'=>'Goliath preserved the approved artifact exactly and routed it to Founder Review. No content was rewritten.',
   'source_version_id'=>(int)$source['id'],'is_tangible'=>1,'qa_passed'=>1,
   'status'=>'ready_for_founder_review',
   'metadata_json'=>gdb_json(['contract'=>'v119.2','deterministic_clone'=>true]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  $artifactId=s1192_insert('goliath_v112_artifacts',[
   'artifact_uid'=>s1192_uid('artifact'),'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],
   'executive_key'=>'goliath','artifact_type'=>$source['artifact_type']??'document',
   'title'=>$source['title']??$stage['mission_title'],
   'content_html'=>$source['content_html']??'','content_text'=>$source['content_text']??'',
   'artifact_url'=>$source['artifact_url']??null,'artifact_path'=>$source['artifact_path']??null,
   'metadata_json'=>gdb_json(['asset_version_id'=>$versionId,'deterministic_clone'=>true]),
   'status'=>'ready_for_founder_review','is_tangible'=>1,'delivered_by_goliath'=>1,
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  gdb_update('goliath_v112_stages',[
   'status'=>'complete','input_artifact_id'=>(int)$source['id'],'output_artifact_id'=>$artifactId,
   'completed_at'=>gdb_now(),'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$stage['id']]);
  gdb_update('goliath_v112_missions',[
   'status'=>'complete','current_stage_no'=>$stageNo,'updated_at'=>gdb_now()
  ],'id=:id',['id'=>$missionId]);
  s1192_insert('goliath_v112_events',[
   'event_uid'=>s1192_uid('event'),'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],
   'executive_key'=>'goliath','event_type'=>'mission_delivered',
   'title'=>'Complete artifact ready for Founder Review',
   'details'=>'Goliath preserved the complete approved work. Every Executive version remains available.',
   'artifact_id'=>$artifactId,
   'url'=>'/dashboard/goliath-workflow-review-v119-2.php?mission_id='.$missionId.'&stage='.$stageNo.'&embed=1',
   'created_at'=>gdb_now()
  ]);
  gdb()->commit();
  return ['mission_id'=>$missionId,'stage_no'=>$stageNo,'version_id'=>$versionId,'artifact_id'=>$artifactId];
 }catch(Throwable $e){if(gdb()->inTransaction())gdb()->rollBack();throw $e;}
}

$key=trim((string)($_GET['key']??''));
if(!hash_equals(s1192_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $started=[];$autoDelivered=[];
 $ready=gdb_all(
  "SELECT s.*,m.title mission_title,m.originator_key,m.priority,m.source_payload_json
   FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id
   WHERE m.status IN ('queued','working') AND s.stage_no=m.current_stage_no AND s.status='ready'
   ORDER BY m.priority DESC,m.id ASC LIMIT 24"
 )?:[];

 $taskCols=s1192_cols('local_ai_tasks');
 foreach($ready as $stage){
  $missionId=(int)$stage['mission_id'];$stageNo=(int)$stage['stage_no'];

  $latest=gdb_one("SELECT * FROM goliath_v118_asset_versions WHERE mission_id=? AND stage_no<? ORDER BY stage_no DESC,id DESC LIMIT 1",[$missionId,$stageNo])?:[];

  $selection=gdb_one(
   "SELECT x.id selection_id,v.* FROM goliath_v118_asset_selections x
    JOIN goliath_v118_asset_versions v ON v.id=x.version_id
    WHERE x.mission_id=? AND x.is_current=1 ORDER BY x.id DESC LIMIT 1",[$missionId]
  )?:[];
  $source=$selection?:$latest;

  if((string)$stage['stage_key']==='goliath_publish_deliver'){
   if(!$source)throw new RuntimeException("Goliath final stage has no approved source artifact for mission $missionId.");
   $autoDelivered[]=s1192_clone_final($stage,$source);
   if($selection)gdb_update('goliath_v118_asset_selections',['is_current'=>0],'id=:id',['id'=>(int)$selection['selection_id']]);
   continue;
  }

  $existing=null;
  if(isset($taskCols['metadata_json'])){
   $existing=gdb_one(
    "SELECT id FROM local_ai_tasks
     WHERE task_type='goliath_v112_stage' AND status IN ('queued','working','claimed','dispatched')
       AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.mission_id'))=?
       AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.stage_no'))=?
     ORDER BY id DESC LIMIT 1",[(string)$missionId,(string)$stageNo]
   );
  }
  if($existing)continue;

  $sourcePayload=s1192_payload($source);
  if($sourcePayload===''){
   $missionData=json_decode((string)($stage['source_payload_json']??''),true);if(!is_array($missionData))$missionData=[];
   $sourcePayload=trim((string)($missionData['directive']??$stage['mission_title']));
  }
  $artifactType=(string)($source['artifact_type']??'document');
  $sourceVersionId=(int)($source['id']??0);
  $sourceLength=mb_strlen(trim(strip_tags($sourcePayload)));
  $sourceHash=hash('sha256',preg_replace('/\s+/u',' ',trim(strip_tags($sourcePayload))));
  $pluginContext=s1192_plugins((string)$stage['executive_key']);

  $prompt=
   "GOLIATH OMNI V119.2 — WORK-ONLY EDITING CONTRACT\n\n".
   "Mission: ".$stage['mission_title']."\nExecutive: ".ucfirst((string)$stage['executive_key'])."\nStage: ".$stage['title']."\n".
   "DIRECT EDITING ASSIGNMENT: ".$stage['instructions']."\n\n".
   "AVAILABLE TOOLS:\n".$pluginContext."\n\n".
   "ABSOLUTE LAW:\n".
   "- Your response is the edited artifact itself. Do not write an executive brief.\n".
   "- Do not say what you would improve. Apply the improvement inside the artifact.\n".
   "- Return the ENTIRE artifact, not a fragment, outline, checklist or notes page.\n".
   "- Preserve useful prior material while making a real editorial change.\n".
   "- For a blog, content_html must contain the complete article from title through final CTA and author section.\n".
   "- The change_note is optional and secondary; it may be one short sentence only.\n".
   "- Never fabricate a URL, statistic, tool result, legal conclusion or archive asset.\n\n".
   "SOURCE ARTIFACT (".$sourceLength." visible characters):\n".
   "<<<COMPLETE_ARTIFACT_START>>>\n".$sourcePayload."\n<<<COMPLETE_ARTIFACT_END>>>\n\n".
   "RETURN ONLY THIS JSON OBJECT. content_html/content_text MUST HOLD THE COMPLETE EDITED WORK:\n".
   "{\"artifact_type\":\"".$artifactType."\",\"title\":\"complete title\",\"content_html\":\"complete edited HTML\",\"content_text\":\"\",\"artifact_url\":\"\",\"artifact_path\":\"\",\"change_note\":\"one short factual sentence\",\"tangible\":true}";

  $metadata=[
   'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],'stage_no'=>$stageNo,
   'executive_key'=>$stage['executive_key'],'source_version_id'=>$sourceVersionId,
   'source_length'=>$sourceLength,'source_hash'=>$sourceHash,
   'artifact_type'=>$artifactType,'artifact_contract'=>'v119.2-work-only',
   'selection_id'=>(int)($selection['selection_id']??0),'priority'=>(int)$stage['priority']
  ];

  $taskId=s1192_insert('local_ai_tasks',[
   'task_uid'=>s1192_uid('stage'),'task_type'=>'goliath_v112_stage','type'=>'goliath_v112_stage',
   'model'=>'local-goliath','prompt'=>$prompt,'status'=>'queued','workflow_state'=>'queued',
   'priority'=>max(100,(int)$stage['priority']),'agent'=>ucfirst((string)$stage['executive_key']),
   'executive_key'=>$stage['executive_key'],'title'=>$stage['title'],
   'metadata_json'=>gdb_json($metadata),'metadata'=>gdb_json($metadata),
   'progress'=>0,'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  gdb_update('goliath_v112_stages',[
   'status'=>'queued_local','input_artifact_id'=>$sourceVersionId?:null,
   'local_task_id'=>$taskId,'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$stage['id']]);
  gdb_update('goliath_v112_missions',['status'=>'working','updated_at'=>gdb_now()],'id=:id',['id'=>$missionId]);
  if($selection)gdb_update('goliath_v118_asset_selections',['is_current'=>0],'id=:id',['id'=>(int)$selection['selection_id']]);

  $started[]=['mission_id'=>$missionId,'stage_no'=>$stageNo,'executive_key'=>$stage['executive_key'],'task_id'=>$taskId,'source_version_id'=>$sourceVersionId];
 }

 echo json_encode([
  'ok'=>true,'version'=>'V119.2 Work-Only Sequential Engine',
  'stages_started'=>count($started),'auto_delivered'=>count($autoDelivered),
  'started'=>$started,'delivered'=>$autoDelivered,'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119.2 Work-Only Sequential Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>