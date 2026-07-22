<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function rf1165_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function rf1165_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}

$key=(string)($_GET['key']??'');
if(!hash_equals(rf1165_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $artifactCols=rf1165_cols('goliath_v112_artifacts');
 $eventsCols=rf1165_cols('goliath_v112_events');
 $rows=gdb_all(
  "SELECT final_stage.id stage_id,final_stage.mission_id,final_stage.input_artifact_id,
          final_stage.output_artifact_id,m.originator_key,
          final_artifact.content_text final_text,final_artifact.content_html final_html
   FROM goliath_v112_stages final_stage
   JOIN goliath_v112_missions m ON m.id=final_stage.mission_id
   LEFT JOIN goliath_v112_artifacts final_artifact ON final_artifact.id=final_stage.output_artifact_id
   WHERE final_stage.stage_key='goliath_publish_deliver'
     AND final_stage.status='complete'
     AND final_stage.output_artifact_id IS NOT NULL
   ORDER BY final_stage.id ASC"
 )?:[];

 $fixed=[];$skipped=[];
 foreach($rows as $row){
  $sourceId=(int)($row['input_artifact_id']??0);
  $finalId=(int)($row['output_artifact_id']??0);
  if($sourceId<1||$finalId<1){$skipped[]=['mission_id'=>(int)$row['mission_id'],'reason'=>'missing_source_or_final'];continue;}

  $source=gdb_one("SELECT * FROM goliath_v112_artifacts WHERE id=? LIMIT 1",[$sourceId]);
  $final=gdb_one("SELECT * FROM goliath_v112_artifacts WHERE id=? LIMIT 1",[$finalId]);
  if(!$source||!$final){$skipped[]=['mission_id'=>(int)$row['mission_id'],'reason'=>'artifact_not_found'];continue;}

  $sourceLength=mb_strlen(trim(strip_tags((string)($source['content_html']?:$source['content_text']))));
  if($sourceLength<100&&!$source['artifact_url']&&!$source['artifact_path']){
   $skipped[]=['mission_id'=>(int)$row['mission_id'],'reason'=>'source_not_tangible'];continue;
  }

  $existingMeta=json_decode((string)($final['metadata_json']??''),true);
  if(!is_array($existingMeta))$existingMeta=[];
  $existingMeta['goliath_previous_overview']=[
   'content_text'=>$final['content_text']??'',
   'content_html'=>$final['content_html']??''
  ];
  $existingMeta['source_artifact_id']=$sourceId;
  $existingMeta['preserve_originator_approved_asset']=true;
  $existingMeta['repair_version']='116.5';

  $update=[
   'executive_key'=>(string)($row['originator_key']??$source['executive_key']??'goliath'),
   'artifact_type'=>$source['artifact_type']??'final_deliverable',
   'title'=>$source['title']??$final['title'],
   'content_html'=>$source['content_html']??'',
   'content_text'=>$source['content_text']??'',
   'artifact_url'=>$source['artifact_url']??null,
   'artifact_path'=>$source['artifact_path']??null,
   'evidence_json'=>$source['evidence_json']??'[]',
   'metadata_json'=>gdb_json($existingMeta),
   'status'=>'ready_for_founder_review',
   'is_tangible'=>1,
   'delivered_by_goliath'=>1,
   'updated_at'=>gdb_now()
  ];
  $safe=[];foreach($update as $k=>$v)if(isset($artifactCols[$k]))$safe[$k]=$v;
  gdb_update('goliath_v112_artifacts',$safe,'id=:id',['id'=>$finalId]);

  if($eventsCols){
   $event=[
    'mission_id'=>(int)$row['mission_id'],'stage_id'=>(int)$row['stage_id'],
    'executive_key'=>(string)($row['originator_key']??'goliath'),
    'event_type'=>'final_asset_repaired','title'=>(string)($source['title']??'Final deliverable restored'),
    'details'=>'V116.5 restored the originator-approved deliverable and moved Goliath notes to supporting metadata.',
    'artifact_id'=>$finalId,
    'url'=>'/dashboard/goliath-review-center.php?artifact_id='.$finalId.'&embed=1',
    'created_at'=>gdb_now()
   ];
   $safeEvent=[];foreach($event as $k=>$v)if(isset($eventsCols[$k]))$safeEvent[$k]=$v;
   if($safeEvent)gdb_insert('goliath_v112_events',$safeEvent);
  }
  $fixed[]=['mission_id'=>(int)$row['mission_id'],'source_artifact_id'=>$sourceId,'final_artifact_id'=>$finalId,'title'=>$source['title']??''];
 }

 echo json_encode([
  'ok'=>true,'version'=>'V116.5 Final Asset Preservation Repair',
  'artifacts_repaired'=>$fixed,'skipped'=>$skipped,
  'next'=>'Upload the V116.5 completion endpoint so all future Goliath closings preserve the approved deliverable.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V116.5 Final Asset Preservation Repair','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>