<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function ps119_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function ps119_score(array $version):array{
 $html=(string)($version['content_html']??'');
 $text=(string)($version['content_text']??'');
 $content=trim(strip_tags($html!==''?$html:$text));
 $lower=mb_strtolower($html."\n".$text);
 $length=mb_strlen($content);
 $hasTitle=preg_match('/<h1\b|^#\s+/mi',$html."\n".$text)===1||trim((string)($version['title']??''))!=='';
 $hasCta=preg_match('/call|text|contact|schedule|reach out|203-247-2655|mark@markpires\.com/u',$lower)===1;
 $hasStructure=preg_match_all('/<h[2-4]\b|^##+\s+/mi',$html."\n".$text)>2;
 $hasEvidence=preg_match('/source|verify|disclaimer|attorney|tax professional|public record|citation|https?:\/\//u',$lower)===1;
 $hasVisual=preg_match('/image|hero|thumbnail|video|reel|alt text|visual/u',$lower)===1;
 $tangible=$length>=900&&$hasTitle&&$hasStructure;
 $message=$tangible?'Pass: complete structured artifact.':'Fail: artifact is too short or lacks title/section structure.';
 return compact('length','hasTitle','hasCta','hasStructure','hasEvidence','hasVisual','tangible','message');
}

$key=trim((string)($_GET['key']??''));
if(!hash_equals(ps119_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$missionId=(int)($_GET['mission_id']??0);
if($missionId<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_mission_id']);exit;}

try{
 $mission=gdb_one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]);
 if(!$mission)throw new RuntimeException('Mission not found.');
 $stages=gdb_all("SELECT * FROM goliath_v112_stages WHERE mission_id=? ORDER BY stage_no",[$missionId])?:[];
 $versions=gdb_all("SELECT * FROM goliath_v118_asset_versions WHERE mission_id=? ORDER BY stage_no,id",[$missionId])?:[];
 $versionByStage=[];foreach($versions as $v)$versionByStage[(int)$v['stage_no']]=$v;

 $results=[];$passes=0;
 foreach($stages as $stage){
  $v=$versionByStage[(int)$stage['stage_no']]??[];
  $qa=$v?ps119_score($v):[
   'length'=>0,'hasTitle'=>false,'hasCta'=>false,'hasStructure'=>false,
   'hasEvidence'=>false,'hasVisual'=>false,'tangible'=>false,'message'=>'No artifact version yet.'
  ];
  if($qa['tangible'])$passes++;
  $results[]=[
   'stage_no'=>(int)$stage['stage_no'],'executive_key'=>$stage['executive_key'],
   'stage_title'=>$stage['title'],'stage_status'=>$stage['status'],
   'artifact_version_id'=>(int)($v['id']??0),'artifact_title'=>$v['title']??null,
   'artifact_status'=>$v['status']??null,'qa'=>$qa
  ];
  if($v){
   $existing=gdb_one("SELECT id FROM goliath_v119_stage_quality WHERE mission_id=? AND stage_no=? LIMIT 1",[$missionId,(int)$stage['stage_no']]);
   $row=[
    'mission_id'=>$missionId,'stage_no'=>(int)$stage['stage_no'],'artifact_version_id'=>(int)$v['id'],
    'executive_key'=>$stage['executive_key'],'tangible_pass'=>$qa['tangible']?1:0,
    'content_length'=>$qa['length'],'has_title'=>$qa['hasTitle']?1:0,'has_cta'=>$qa['hasCta']?1:0,
    'has_structure'=>$qa['hasStructure']?1:0,'has_source_or_evidence'=>$qa['hasEvidence']?1:0,
    'has_visual_or_media_reference'=>$qa['hasVisual']?1:0,'qa_message'=>$qa['message'],'created_at'=>gdb_now()
   ];
   if($existing)gdb_update('goliath_v119_stage_quality',$row,'id=:id',['id'=>(int)$existing['id']]);
   else gdb_insert('goliath_v119_stage_quality',$row);
  }
 }

 $complete=(string)$mission['status']==='complete';
 $allPassed=count($stages)>0&&$passes===count($stages);
 $proofStatus=$complete&&$allPassed?'passed':($complete?'failed_qa':'running');
 if(!empty($mission['proof_test_uid'])){
  gdb_update('goliath_v119_proof_tests',[
   'status'=>$proofStatus,'last_checked_at'=>gdb_now(),
   'result_json'=>gdb_json(['passes'=>$passes,'expected'=>count($stages),'mission_complete'=>$complete])
  ],'proof_uid=:uid',['uid'=>$mission['proof_test_uid']]);
 }

 echo json_encode([
  'ok'=>true,'version'=>'V119 Blog Proof Status','mission_id'=>$missionId,
  'mission_title'=>$mission['title'],'mission_status'=>$mission['status'],
  'current_stage_no'=>(int)$mission['current_stage_no'],
  'versions_created'=>count($versions),'expected_versions'=>count($stages),
  'tangible_passes'=>$passes,'all_stages_tangible'=>$allPassed,
  'proof_status'=>$proofStatus,'stages'=>$results,
  'review_url'=>'/dashboard/goliath-v119-blog-proof.php?mission_id='.$missionId,
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119 Blog Proof Status','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>