<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function c1192_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function c1192_cols(string $table):array{
 $rows=gdb_all("SELECT column_name,column_type,is_nullable,column_default,extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=$r;return $out;
}
function c1192_default(string $column,string $type){
 $n=strtolower($column);$t=strtolower($type);
 if(str_contains($n,'uid'))return 'auto_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(12));
 if(str_contains($n,'title'))return 'Goliath Evolving Artifact';
 if(str_contains($n,'status'))return 'stage_complete';
 if(str_contains($n,'type'))return 'artifact';
 if(str_contains($n,'key'))return 'goliath';
 if(str_contains($t,'int')||str_contains($t,'decimal'))return 0;
 if(str_contains($t,'date')||str_contains($t,'time'))return gdb_now();
 return '';
}
function c1192_insert(string $table,array $row):int{
 $cols=c1192_cols($table);$safe=[];
 foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 foreach($cols as $c=>$d){
  if(array_key_exists($c,$safe)||strtolower((string)$d['is_nullable'])==='yes'||$d['column_default']!==null||str_contains(strtolower((string)$d['extra']),'auto_increment'))continue;
  $safe[$c]=c1192_default($c,(string)$d['column_type']);
 }
 return (int)gdb_insert($table,$safe);
}
function c1192_update(string $table,array $row,string $where,array $params):int{
 $cols=c1192_cols($table);$safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 return $safe?(int)(bool)gdb_update($table,$safe,$where,$params):0;
}
function c1192_meta(array $task):array{
 foreach(['metadata_json','metadata'] as $col){if(empty($task[$col]))continue;$j=json_decode((string)$task[$col],true);if(is_array($j))return $j;}return [];
}
function c1192_parse(string $raw):array{
 $trim=trim($raw);$j=json_decode($trim,true);if(is_array($j))return $j;
 $a=strpos($trim,'{');$b=strrpos($trim,'}');
 if($a!==false&&$b!==false&&$b>$a){$j=json_decode(substr($trim,$a,$b-$a+1),true);if(is_array($j))return $j;}
 $plain=trim(preg_replace('/^```(?:html|markdown)?\s*|\s*```$/iu','',$trim));
 if(mb_strlen(strip_tags($plain))>=500){
  $html=preg_match('/<(?:article|section|h1|h2|p|div)\b/i',$plain)===1;
  return ['artifact_type'=>$html?'blog':'document','title'=>'Complete Edited Artifact','content_html'=>$html?$plain:'',
   'content_text'=>$html?'':$plain,'artifact_url'=>'','artifact_path'=>'',
   'change_note'=>'Complete artifact normalized from a non-JSON model response.','tangible'=>true];
 }
 return [];
}
function c1192_content(array $p):string{
 $h=trim((string)($p['content_html']??''));$t=trim((string)($p['content_text']??''));return $h!==''?$h:$t;
}
function c1192_plain(string $content):string{return preg_replace('/\s+/u',' ',trim(strip_tags($content)));}
function c1192_notes_only(string $content):bool{
 $plain=mb_strtolower(c1192_plain($content));
 $patterns=[
  '/^executive (?:review|summary|brief)/u','/^creator notes?/u',
  '/^here (?:is|are) (?:my|the) (?:review|analysis|recommendations|suggestions)/u',
  '/^i (?:would|recommend|suggest)/u','/what i (?:added|changed)/u'
 ];
 $hits=0;foreach($patterns as $p)if(preg_match($p,$plain))$hits++;
 $hasArticle=preg_match('/<h1\b|<h2\b|<article\b/iu',$content)===1;
 return !$hasArticle&&mb_strlen($plain)<1200&&$hits>0;
}
function c1192_overlap(string $source,string $output):float{
 $normalize=function(string $text):array{
  $words=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower(c1192_plain($text)),-1,PREG_SPLIT_NO_EMPTY)?:[];
  $stop=['the','and','for','that','with','this','from','your','you','are','was','have','has','but','not','can','will','home','property'];
  return array_values(array_unique(array_filter($words,fn($w)=>mb_strlen($w)>=4&&!in_array($w,$stop,true))));
 };
 $a=$normalize($source);$b=$normalize($output);
 if(!$a||!$b)return 0.0;
 return count(array_intersect($a,$b))/max(1,min(count($a),count($b)));
}
function c1192_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));}
function c1192_gate_log(int $missionId,int $stageNo,int $taskId,string $exec,bool $passed,string $reason,int $sourceLen,int $outputLen,string $sourceHash,string $outputHash,array $details=[]):void{
 try{
  c1192_insert('goliath_v119_artifact_gate_log',[
   'gate_uid'=>c1192_uid('gate'),'mission_id'=>$missionId,'stage_no'=>$stageNo,'task_id'=>$taskId,
   'executive_key'=>$exec,'passed'=>$passed?1:0,'reason'=>$reason,
   'source_length'=>$sourceLen,'output_length'=>$outputLen,
   'source_hash'=>$sourceHash?:null,'output_hash'=>$outputHash?:null,
   'details_json'=>gdb_json($details),'created_at'=>gdb_now()
  ]);
 }catch(Throwable $ignored){}
}

$input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;
$key=trim((string)($input['key']??$_GET['key']??''));
if(!hash_equals(c1192_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$taskId=(int)($input['task_id']??0);if($taskId<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_task_id']);exit;}

try{
 $task=gdb_one("SELECT * FROM local_ai_tasks WHERE id=? LIMIT 1",[$taskId]);if(!$task)throw new RuntimeException('Task not found.');
 $success=in_array(strtolower((string)($input['status']??'complete')),['complete','completed','done','success'],true);
 $raw=(string)($input['result']??'');$error=(string)($input['error_message']??$input['error']??'');

 if(!$success){
  c1192_update('local_ai_tasks',['status'=>'failed','result'=>$raw,'error'=>$error,'updated_at'=>gdb_now()],'id=:id',['id'=>$taskId]);
  echo json_encode(['ok'=>true,'version'=>'V119.2 Work-Only Completion','status'=>'failed']);exit;
 }
 if((string)($task['task_type']??$task['type']??'')==='ask_goliath_live_v111'){
  c1192_update('local_ai_tasks',['status'=>'complete','progress'=>100,'result'=>$raw,'updated_at'=>gdb_now()],'id=:id',['id'=>$taskId]);
  echo json_encode(['ok'=>true,'version'=>'V119.2 Work-Only Completion','voice'=>true]);exit;
 }

 $meta=c1192_meta($task);$missionId=(int)($meta['mission_id']??0);$stageNo=(int)($meta['stage_no']??0);
 $exec=(string)($meta['executive_key']??$task['executive_key']??'unknown');
 if($missionId<1||$stageNo<1)throw new RuntimeException('Missing mission/stage metadata.');

 $parsed=c1192_parse($raw);if(!$parsed)throw new RuntimeException('No complete artifact could be parsed from the model response.');
 $content=c1192_content($parsed);$outputPlain=c1192_plain($content);
 $outputLen=mb_strlen($outputPlain);$outputHash=hash('sha256',$outputPlain);
 $url=trim((string)($parsed['artifact_url']??''));$path=trim((string)($parsed['artifact_path']??''));
 $sourceVersionId=(int)($meta['source_version_id']??0);
 $source=$sourceVersionId?gdb_one("SELECT * FROM goliath_v118_asset_versions WHERE id=? LIMIT 1",[$sourceVersionId]):[];
 $sourceContent=$source?((trim((string)$source['content_html'])!=='')?(string)$source['content_html']:(string)$source['content_text']):'';
 $sourcePlain=c1192_plain($sourceContent);$sourceLen=mb_strlen($sourcePlain);$sourceHash=hash('sha256',$sourcePlain);
 $artifactType=(string)($parsed['artifact_type']??$meta['artifact_type']??$source['artifact_type']??'document');

 $reason='passed';$passed=true;$details=[];
 if(!(bool)($parsed['tangible']??false)){$passed=false;$reason='tangible_false';}
 elseif(c1192_notes_only($content)){$passed=false;$reason='notes_instead_of_artifact';}
 elseif($artifactType==='blog'&&preg_match('/<h1\b|<h2\b/iu',$content)!==1){$passed=false;$reason='blog_missing_article_structure';}
 elseif($outputLen<max(600,(int)floor($sourceLen*0.65))&&$url===''&&$path===''){$passed=false;$reason='incomplete_artifact_too_short';}
 elseif($sourceLen>300&&$outputHash===$sourceHash){$passed=false;$reason='no_material_edit';}
 else{
  $overlap=$sourceLen>300?c1192_overlap($sourceContent,$content):1.0;
  $details['word_overlap']=$overlap;
  if($sourceLen>800&&$overlap<0.18){$passed=false;$reason='artifact_continuity_lost';}
 }

 c1192_gate_log($missionId,$stageNo,$taskId,$exec,$passed,$reason,$sourceLen,$outputLen,$sourceHash,$outputHash,$details);

 if(!$passed){
  c1192_update('local_ai_tasks',[
   'status'=>'failed','progress'=>0,'result'=>$raw,
   'error'=>"V119.2 work-only gate rejected output: $reason",'updated_at'=>gdb_now()
  ],'id=:id',['id'=>$taskId]);
  $stage=gdb_one("SELECT id FROM goliath_v112_stages WHERE mission_id=? AND stage_no=? LIMIT 1",[$missionId,$stageNo]);
  if($stage)c1192_update('goliath_v112_stages',[
   'status'=>'ready','local_task_id'=>null,
   'blocking_issue'=>"Rejected: $reason. Return the complete edited artifact.",
   'last_error'=>"V119.2 artifact gate: $reason",'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$stage['id']]);
  echo json_encode([
   'ok'=>true,'version'=>'V119.2 Work-Only Completion',
   'task_id'=>$taskId,'handoff'=>['advanced'=>false,'reason'=>$reason],
   'source_length'=>$sourceLen,'output_length'=>$outputLen,'details'=>$details
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;
 }

 gdb()->beginTransaction();
 try{
  $stage=gdb_one(
   "SELECT s.*,m.current_stage_no,m.title mission_title
    FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id
    WHERE s.mission_id=? AND s.stage_no=? LIMIT 1 FOR UPDATE",[$missionId,$stageNo]
  );
  if(!$stage)throw new RuntimeException('Stage not found.');
  if((int)$stage['current_stage_no']!==$stageNo)throw new RuntimeException('Stale stage completion.');

  $title=mb_substr(trim((string)($parsed['title']??$stage['mission_title']??$stage['title'])),0,250);
  $changeNote=mb_substr(trim((string)($parsed['change_note']??'')),0,800);
  $versionId=c1192_insert('goliath_v118_asset_versions',[
   'version_uid'=>c1192_uid('version'),'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],
   'stage_no'=>$stageNo,'executive_key'=>$stage['executive_key'],'artifact_type'=>$artifactType,'title'=>$title,
   'content_html'=>(string)($parsed['content_html']??''),'content_text'=>(string)($parsed['content_text']??''),
   'artifact_url'=>$url?:null,'artifact_path'=>$path?:null,'change_note'=>$changeNote,
   'source_version_id'=>$sourceVersionId?:null,'is_tangible'=>1,'qa_passed'=>1,'status'=>'stage_complete',
   'metadata_json'=>gdb_json([
    'task_id'=>$taskId,'contract'=>'v119.2-work-only',
    'source_hash'=>$sourceHash,'output_hash'=>$outputHash,'source_length'=>$sourceLen,
    'output_length'=>$outputLen,'word_overlap'=>$details['word_overlap']??null
   ]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  $artifactId=c1192_insert('goliath_v112_artifacts',[
   'artifact_uid'=>c1192_uid('artifact'),'mission_id'=>$missionId,'stage_id'=>(int)$stage['id'],
   'executive_key'=>$stage['executive_key'],'artifact_type'=>$artifactType,'title'=>$title,
   'content_html'=>(string)($parsed['content_html']??''),'content_text'=>(string)($parsed['content_text']??''),
   'artifact_url'=>$url?:null,'artifact_path'=>$path?:null,
   'metadata_json'=>gdb_json(['asset_version_id'=>$versionId,'change_note'=>$changeNote,'version'=>'119.2']),
   'status'=>'stage_complete','is_tangible'=>1,'delivered_by_goliath'=>0,
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);

  c1192_update('local_ai_tasks',['status'=>'complete','progress'=>100,'result'=>$raw,'error'=>null,'updated_at'=>gdb_now()],'id=:id',['id'=>$taskId]);
  c1192_update('goliath_v112_stages',[
   'status'=>'complete','output_artifact_id'=>$artifactId,'completed_at'=>gdb_now(),
   'blocking_issue'=>null,'last_error'=>null,'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$stage['id']]);

  $next=gdb_one("SELECT * FROM goliath_v112_stages WHERE mission_id=? AND stage_no>? ORDER BY stage_no ASC LIMIT 1 FOR UPDATE",[$missionId,$stageNo]);
  if($next){
   c1192_update('goliath_v112_stages',[
    'status'=>'ready','input_artifact_id'=>$versionId,'local_task_id'=>null,
    'blocking_issue'=>null,'last_error'=>null,'updated_at'=>gdb_now()
   ],'id=:id',['id'=>(int)$next['id']]);
   c1192_update('goliath_v112_missions',['status'=>'working','current_stage_no'=>(int)$next['stage_no'],'updated_at'=>gdb_now()],'id=:id',['id'=>$missionId]);
   c1192_insert('goliath_v112_events',[
    'event_uid'=>c1192_uid('event'),'mission_id'=>$missionId,'stage_id'=>(int)$next['id'],
    'executive_key'=>$next['executive_key'],'event_type'=>'stage_ready','title'=>$next['title'],
    'details'=>'A complete edited artifact advanced from '.ucfirst((string)$stage['executive_key']).' to '.ucfirst((string)$next['executive_key']).'.',
    'artifact_id'=>$artifactId,
    'url'=>'/dashboard/goliath-workflow-review-v119-2.php?mission_id='.$missionId.'&stage='.$stageNo.'&embed=1',
    'created_at'=>gdb_now()
   ]);
   $handoff=['advanced'=>true,'reason'=>'complete_edited_artifact','next_stage'=>(int)$next['stage_no'],'next_executive'=>$next['executive_key']];
  }else{
   c1192_update('goliath_v112_missions',['status'=>'complete','updated_at'=>gdb_now()],'id=:id',['id'=>$missionId]);
   $handoff=['advanced'=>false,'reason'=>'mission_complete'];
  }
  gdb()->commit();
 }catch(Throwable $e){if(gdb()->inTransaction())gdb()->rollBack();throw $e;}

 echo json_encode([
  'ok'=>true,'version'=>'V119.2 Work-Only Completion','task_id'=>$taskId,
  'mission_id'=>$missionId,'stage_no'=>$stageNo,'version_id'=>$versionId,
  'artifact_id'=>$artifactId,'handoff'=>$handoff,
  'source_length'=>$sourceLen,'output_length'=>$outputLen
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 if(gdb()->inTransaction())gdb()->rollBack();
 echo json_encode([
  'ok'=>false,'version'=>'V119.2 Work-Only Completion','error'=>$e->getMessage(),
  'file'=>basename($e->getFile()),'line'=>$e->getLine(),'task_id'=>$taskId
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>