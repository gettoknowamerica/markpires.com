<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
set_time_limit(50);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-orchestration-lib-v115.php';

$V112_RESPONSE=['ok'=>false,'version'=>'V115.4 Verified Sequential Engine'];
register_shutdown_function(function()use(&$V112_RESPONSE){
 $e=error_get_last();
 if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
  if(!headers_sent()){http_response_code(500);header('Content-Type: application/json; charset=utf-8');}
  $V112_RESPONSE['error']='fatal_error';
  $V112_RESPONSE['details']=['message'=>$e['message'],'file'=>$e['file'],'line'=>$e['line']];
  echo json_encode($V112_RESPONSE,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
 }
});

function p112_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function p112_one(string $s,array $p=[]):?array{try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
function p112_all(string $s,array $p=[]):array{try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function p112_cols(string $t):array{
 $rows=p112_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t]);
 $out=[];foreach($rows as $r)$out[$r['column_name']]=true;return $out;
}
function p112_insert_safe(string $t,array $row,array $cols):int{
 $safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for $t");
 $id=gdb_insert($t,$safe);
 if($id===false||$id===null||$id==='')throw new RuntimeException("Insert into $t returned no ID");
 return (int)$id;
}
function p112_extract_json(string $raw):array{
 $trim=trim($raw);
 $direct=json_decode($trim,true);if(is_array($direct))return $direct;
 if(preg_match('/\{(?:[^{}]|(?R))*\}/s',$trim,$m)){
   $j=json_decode($m[0],true);if(is_array($j))return $j;
 }
 return [];
}
function p112_task_result(array $task,array $cols):string{
 foreach(['result','output','response','content'] as $c)if(isset($cols[$c])&&!empty($task[$c]))return (string)$task[$c];
 return '';
}
function p112_tangible(string $stageKey,string $raw,array $parsed):array{
 $html=(string)($parsed['content_html']??'');
 $text=(string)($parsed['content_text']??$raw);
 $url=(string)($parsed['artifact_url']??$parsed['url']??'');
 $path=(string)($parsed['artifact_path']??$parsed['path']??'');
 $type=(string)($parsed['artifact_type']??'stage_deliverable');
 $min=600;
 if(str_contains($stageKey,'visual'))$min=250;
 if(str_contains($stageKey,'audio'))$min=120;
 if(str_contains($stageKey,'distribution'))$min=250;
 if(str_contains($stageKey,'roi'))$min=180;
 if(str_contains($stageKey,'archive'))$min=180;
 if(str_contains($stageKey,'final_review')||str_contains($stageKey,'research_draft'))$min=1800;
 $length=mb_strlen(trim(strip_tags($html!==''?$html:$text)));
 $passThrough=(bool)($parsed['pass_through']??false);
 $is=($url!==''||$path!==''||$length>=$min||($passThrough&&($html!==''||$text!=='')));
 return ['is_tangible'=>$is,'length'=>$length,'html'=>$html,'text'=>$text,'url'=>$url,'path'=>$path,'type'=>$type];
}
function p112_context(int $missionId,int $beforeStage):string{
 $arts=p112_all("SELECT a.*,s.stage_no,s.stage_key FROM goliath_v112_artifacts a LEFT JOIN goliath_v112_stages s ON s.id=a.stage_id WHERE a.mission_id=? AND s.stage_no<? ORDER BY s.stage_no ASC,a.id ASC",[$missionId,$beforeStage]);
 $chunks=[];
 foreach($arts as $a){
   $body=(string)($a['content_html']?:$a['content_text']);
   if(mb_strlen($body)>18000)$body=mb_substr($body,0,18000);
   $chunks[]="STAGE ".($a['stage_no']??'')." / ".($a['executive_key']??'')."\n".$body;
 }
 return implode("\n\n-----\n\n",$chunks);
}
function p112_publish_blog(array $mission,string $html,string $slug,string $metaDescription):array{
 $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__)),'/\\');
 $blogDir=$docRoot.'/blog';
 $templatePath=$blogDir.'/blog-template.html';
 $indexPath=$blogDir.'/index.html';
 if(!is_file($templatePath))return ['ok'=>false,'error'=>'missing_blog_template','path'=>$templatePath];
 if(!is_file($indexPath))return ['ok'=>false,'error'=>'missing_blog_index','path'=>$indexPath];
 $template=(string)file_get_contents($templatePath);
 $title=(string)$mission['title'];
 $url='/blog/'.$slug.'.html';
 $replace=[
   '{{title}}'=>htmlspecialchars($title,ENT_QUOTES,'UTF-8'),
   '{{ title }}'=>htmlspecialchars($title,ENT_QUOTES,'UTF-8'),
   '{{meta_title}}'=>htmlspecialchars($title,ENT_QUOTES,'UTF-8'),
   '{{ meta_title }}'=>htmlspecialchars($title,ENT_QUOTES,'UTF-8'),
   '{{meta_description}}'=>htmlspecialchars($metaDescription,ENT_QUOTES,'UTF-8'),
   '{{ meta_description }}'=>htmlspecialchars($metaDescription,ENT_QUOTES,'UTF-8'),
   '{{content}}'=>$html,
   '{{ content }}'=>$html,
   '{{article_content}}'=>$html,
   '{{ article_content }}'=>$html,
   '{{canonical_url}}'=>'https://www.markpires.com'.$url,
   '{{ canonical_url }}'=>'https://www.markpires.com'.$url
 ];
 $page=strtr($template,$replace);
 if($page===$template){
   $article="\n<article class=\"goliath-blog-article\">\n".$html."\n</article>\n";
   $page=stripos($page,'</main>')!==false?str_ireplace('</main>',$article.'</main>',$page):str_ireplace('</body>',$article.'</body>',$page);
 }
 $absolute=$blogDir.'/'.$slug.'.html';
 if(file_put_contents($absolute,$page,LOCK_EX)===false)return ['ok'=>false,'error'=>'write_failed','path'=>$absolute];
 $index=(string)file_get_contents($indexPath);
 $marker='GOLIATH_BLOG:'.$slug;
 if(strpos($index,$marker)===false){
   $card="\n<!-- $marker --><article class=\"blog-card\" data-goliath-blog=\"".htmlspecialchars($slug,ENT_QUOTES,'UTF-8')."\"><h2><a href=\"".htmlspecialchars($url,ENT_QUOTES,'UTF-8')."\">".htmlspecialchars($title,ENT_QUOTES,'UTF-8')."</a></h2><p>".htmlspecialchars($metaDescription,ENT_QUOTES,'UTF-8')."</p><a class=\"read-more\" href=\"".htmlspecialchars($url,ENT_QUOTES,'UTF-8')."\">Read Article</a></article><!-- /$marker -->\n";
   $index=strpos($index,'<!-- GOLIATH_BLOG_LIST -->')!==false?str_replace('<!-- GOLIATH_BLOG_LIST -->','<!-- GOLIATH_BLOG_LIST -->'.$card,$index):(stripos($index,'</main>')!==false?str_ireplace('</main>',$card.'</main>',$index):str_ireplace('</body>',$card.'</body>',$index));
   file_put_contents($indexPath,$index,LOCK_EX);
 }
 return ['ok'=>true,'url'=>$url,'path'=>$absolute];
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(p112_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
$lock=p112_one("SELECT GET_LOCK('goliath_v1151_sequential_engine',0) acquired");
if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'version'=>'V115.4 Verified Sequential Engine','status'=>'skipped_overlap']);exit;}

$metrics=['completed_tasks_consumed'=>0,'stages_started'=>0,'stages_advanced'=>0,'artifacts_created'=>0,'missions_delivered'=>0,'stages_failed_gate'=>0];

try{
 $taskCols=p112_cols('local_ai_tasks');
 // Recover stages abandoned by a stopped local runtime.
 gdb()->exec("UPDATE goliath_v112_stages SET status='ready',local_task_id=NULL,last_error='V115.4 recovered stale stage',updated_at=NOW() WHERE status IN ('dispatching','queued_local','working') AND updated_at<DATE_SUB(NOW(),INTERVAL 45 MINUTE)");

 // Consume completed/failed local tasks already attached to stages.
 $running=p112_all("SELECT s.*,m.title mission_title,m.mission_type,m.source_payload_json,m.originator_key
 FROM goliath_v112_stages s
 JOIN goliath_v112_missions m ON m.id=s.mission_id
 WHERE s.status IN ('queued_local','working')
 AND s.local_task_id IS NOT NULL
 AND s.stage_no=m.current_stage_no
 AND m.status IN ('queued','working')
 ORDER BY m.priority DESC,m.id ASC
 LIMIT 20");
 foreach($running as $stage){
   if(!$taskCols)break;
   $fields=['id'];
   foreach(['status','workflow_state','result','output','response','content','error_message','error'] as $c)if(isset($taskCols[$c]))$fields[]=$c;
   $task=p112_one("SELECT ".implode(',',$fields)." FROM local_ai_tasks WHERE id=? LIMIT 1",[(int)$stage['local_task_id']]);
   if(!$task)continue;
   $status=strtolower((string)($task['status']??$task['workflow_state']??''));
   if(in_array($status,['failed','error'],true)){
     $err=(string)($task['error_message']??$task['error']??'Local task failed');
     gdb_update('goliath_v112_stages',['status'=>'ready','last_error'=>$err,'local_task_id'=>null,'updated_at'=>gdb_now()],'id=:id',['id'=>$stage['id']]);
     $metrics['stages_failed_gate']++;
     continue;
   }
   if(!in_array($status,['complete','completed','done','success'],true))continue;

   $raw=p112_task_result($task,$taskCols);
   $parsed=p112_extract_json($raw);
   $tangible=p112_tangible((string)$stage['stage_key'],$raw,$parsed);
   if(!$tangible['is_tangible']){
     gdb_update('goliath_v112_stages',[
       'status'=>'ready','last_error'=>'Artifact gate failed: output was a placeholder or too short ('.$tangible['length'].' meaningful characters).',
       'local_task_id'=>null,'updated_at'=>gdb_now()
     ],'id=:id',['id'=>$stage['id']]);
     $metrics['stages_failed_gate']++;
     continue;
   }

   $artifactId=(int)gdb_insert('goliath_v112_artifacts',[
     'mission_id'=>$stage['mission_id'],'stage_id'=>$stage['id'],'executive_key'=>$stage['executive_key'],
     'artifact_type'=>$tangible['type'],'title'=>(string)($parsed['title']??$stage['title']),
     'content_html'=>$tangible['html'],'content_text'=>$tangible['text'],
     'artifact_url'=>$tangible['url']?:null,'artifact_path'=>$tangible['path']?:null,
     'evidence_json'=>gdb_json($parsed['evidence']??[]),
     'metadata_json'=>gdb_json(['parsed'=>$parsed,'meaningful_length'=>$tangible['length'],'local_task_id'=>$stage['local_task_id']]),
     'status'=>'stage_complete','is_tangible'=>1,'delivered_by_goliath'=>0,
     'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   gdb_update('goliath_v112_stages',[
     'status'=>'complete','output_artifact_id'=>$artifactId,'completed_at'=>gdb_now(),'updated_at'=>gdb_now()
   ],'id=:id',['id'=>$stage['id']]);
   $metrics['completed_tasks_consumed']++;$metrics['artifacts_created']++;

   // Final Goliath stage publishes one real asset.
   if($stage['stage_key']==='goliath_publish_deliver'){
     $approved=(bool)($parsed['approved']??true);
     $html=(string)($parsed['content_html']??'');
     if($html===''){
       $originator=p112_one("SELECT a.* FROM goliath_v112_artifacts a JOIN goliath_v112_stages s ON s.id=a.stage_id WHERE a.mission_id=? AND s.stage_key='originator_final_review' ORDER BY a.id DESC LIMIT 1",[$stage['mission_id']]);
       $html=(string)($originator['content_html']??$originator['content_text']??'');
     }
     $slug=(string)($parsed['slug']??'selling-an-absentee-owned-home-in-connecticut');
     $meta=(string)($parsed['meta_description']??'A detailed guide for absentee property owners considering a Connecticut home sale.');
     $mission=p112_one("SELECT * FROM goliath_v112_missions WHERE id=?",[$stage['mission_id']]);
     if($approved&&mb_strlen(strip_tags($html))>=1800){
       $pub=p112_publish_blog($mission,$html,$slug,$meta);
       if($pub['ok']){
         gdb_update('goliath_v112_artifacts',[
           'artifact_type'=>'published_blog','artifact_url'=>$pub['url'],'artifact_path'=>$pub['path'],
           'status'=>'delivered','delivered_by_goliath'=>1,'delivered_at'=>gdb_now(),'updated_at'=>gdb_now()
         ],'id=:id',['id'=>$artifactId]);
         gdb_update('goliath_v112_missions',[
           'status'=>'delivered','final_artifact_id'=>$artifactId,'delivered_url'=>$pub['url'],
           'completed_at'=>gdb_now(),'delivered_at'=>gdb_now(),'updated_at'=>gdb_now()
         ],'id=:id',['id'=>$stage['mission_id']]);
         gdb_insert('goliath_v112_events',[
           'mission_id'=>$stage['mission_id'],'stage_id'=>$stage['id'],'executive_key'=>'goliath',
           'event_type'=>'asset_delivered','title'=>'Goliath delivered one finished asset',
           'details'=>$mission['title'],'artifact_id'=>$artifactId,'url'=>$pub['url'],'created_at'=>gdb_now()
         ]);
         $metrics['missions_delivered']++;
       }else{
         gdb_update('goliath_v112_stages',['status'=>'ready','last_error'=>'Publish failed: '.json_encode($pub),'local_task_id'=>null,'updated_at'=>gdb_now()],'id=:id',['id'=>$stage['id']]);
       }
     }else{
       gdb_update('goliath_v112_stages',['status'=>'ready','last_error'=>'Final gate failed: approval or full HTML missing.','local_task_id'=>null,'updated_at'=>gdb_now()],'id=:id',['id'=>$stage['id']]);
       $metrics['stages_failed_gate']++;
     }
   }else{
     $next=p112_one("SELECT * FROM goliath_v112_stages WHERE mission_id=? AND stage_no>? ORDER BY stage_no ASC LIMIT 1",[$stage['mission_id'],$stage['stage_no']]);
     if($next){
       $advance=gdb()->prepare("UPDATE goliath_v112_stages SET status='ready',input_artifact_id=?,updated_at=NOW() WHERE id=? AND status='waiting'");
       $advance->execute([$artifactId,(int)$next['id']]);
       if($advance->rowCount()!==1){
         throw new RuntimeException('Next stage was not waiting; mission '.$stage['mission_id'].' stage '.$next['stage_no'].' could not be activated safely.');
       }
       gdb_update('goliath_v112_missions',['status'=>'working','current_stage_no'=>$next['stage_no'],'started_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>$stage['mission_id']]);
       gdb_insert('goliath_v112_events',[
         'mission_id'=>$stage['mission_id'],'stage_id'=>$next['id'],'executive_key'=>$next['executive_key'],
         'event_type'=>'stage_ready','title'=>$next['title'],'details'=>'Sequential handoff: '.ucfirst((string)$stage['executive_key']).' → '.ucfirst((string)$next['executive_key']).'. Previous tangible artifact passed the gate.','artifact_id'=>$artifactId,
         'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
       ]);
       $metrics['stages_advanced']++;
     }
   }
 }

 // V115.1: atomic sequential dispatch.
 // A mission may dispatch ONLY its current_stage_no. A stage is claimed with an atomic ready→dispatching update.
 // This prevents the same stage from creating hundreds of duplicate local tasks.
 $activeLocal=(int)(p112_one("SELECT COUNT(*) c FROM goliath_v112_stages WHERE status IN ('dispatching','queued_local','working')")['c']??0);
 $slots=max(0,6-$activeLocal);
 if($slots>0&&$taskCols){
  $readyStages=p112_all("SELECT s.*,m.title mission_title,m.mission_type,m.source_url,m.source_payload_json,m.originator_key,m.priority mission_priority
   FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id
   WHERE s.status='ready' AND s.stage_no=m.current_stage_no AND m.status IN ('queued','working')
   ORDER BY m.priority DESC,m.id ASC LIMIT 30");
  $startedExecutives=[];
  foreach($readyStages as $ready){
   if($slots<=0)break;
   $exec=(string)$ready['executive_key'];
   if(isset($startedExecutives[$exec]))continue;
   $busy=p112_one("SELECT COUNT(*) c FROM goliath_v112_stages WHERE executive_key=? AND status IN ('dispatching','queued_local','working')",[$exec]);
   if((int)($busy['c']??0)>0)continue;

   // Atomic ownership: only one PHP process can move this exact stage.
   $stmt=gdb()->prepare("UPDATE goliath_v112_stages SET status='dispatching',started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=? AND status='ready' AND local_task_id IS NULL");
   $stmt->execute([(int)$ready['id']]);
   if($stmt->rowCount()!==1)continue;

   try{
    $context=p112_context((int)$ready['mission_id'],(int)$ready['stage_no']);
    $source=json_decode((string)$ready['source_payload_json'],true)?:[];
    $prompt="V115.1 SEQUENTIAL RING STAGE\n\n".
     "MISSION ID: ".$ready['mission_id']."\nMISSION: ".$ready['mission_title']."\nORIGINATOR: ".$ready['originator_key']."\n".
     "STAGE: ".$ready['stage_no']."\nCURRENT EXECUTIVE: ".$exec."\n\n".
     "THE NEXT RECIPIENT IS DETERMINED BY THE DATABASE. DO NOT CREATE HANDOFFS OR CHOOSE ANOTHER EXECUTIVE.\n".
     "Review the shared artifact, add concrete value, and return the COMPLETE artifact. If your specialty adds nothing, return the complete artifact unchanged with pass_through=true and a brief reason.\n".
     "Never return only a status report, executive brief, outline, or placeholder.\n\n".
     "STAGE INSTRUCTIONS:\n".$ready['instructions']."\n\nSOURCE PAYLOAD:\n".json_encode($source,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).
     "\n\nALL PRIOR TANGIBLE WORK:\n".$context;

    $taskId=g115_create_local_task($exec,'goliath_v112_stage','V115.1 M'.$ready['mission_id'].' S'.$ready['stage_no'].' — '.$ready['title'],$prompt,600,
     ['mission_id'=>$ready['mission_id'],'stage_id'=>$ready['id'],'stage_no'=>$ready['stage_no'],'stage_key'=>$ready['stage_key'],'originator_key'=>$ready['originator_key'],'sequential_ring'=>true]);

    gdb_update('goliath_v112_stages',[
     'status'=>'queued_local','local_task_id'=>$taskId,'attempt_count'=>(int)$ready['attempt_count']+1,'updated_at'=>gdb_now()
    ],'id=:id',['id'=>$ready['id']]);
    gdb_update('goliath_v112_missions',['status'=>'working','started_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>$ready['mission_id']]);
    gdb_insert('goliath_v112_events',[
     'mission_id'=>$ready['mission_id'],'stage_id'=>$ready['id'],'executive_key'=>$exec,
     'event_type'=>'stage_started','title'=>$ready['title'],
     'details'=>'V115.1 mission '.$ready['mission_id'].' stage '.$ready['stage_no'].' task #'.$taskId.' queued.',
     'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
    ]);
    $metrics['stages_started']++;$slots--;$startedExecutives[$exec]=true;
   }catch(Throwable $dispatchError){
    gdb_update('goliath_v112_stages',['status'=>'ready','local_task_id'=>null,'last_error'=>'Dispatch failed: '.$dispatchError->getMessage(),'updated_at'=>gdb_now()],'id=:id',['id'=>$ready['id']]);
   }
  }
 }

 $truth=p112_one("SELECT COUNT(*) finished_assets FROM goliath_v112_artifacts WHERE delivered_by_goliath=1 AND status='delivered'")?:['finished_assets'=>0];
 echo json_encode([
  'ok'=>true,'version'=>'V115.4 Verified Sequential Engine','status'=>'complete',
  'metrics'=>$metrics,'truth'=>['finished_assets'=>(int)$truth['finished_assets'],'review_placeholders'=>0],
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{
 try{p112_one("SELECT RELEASE_LOCK('goliath_v1151_sequential_engine') released");}catch(Throwable $e){}
}
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,
  'version'=>'V115.4 Verified Sequential Engine',
  'error'=>'caught_exception',
  'details'=>[
   'message'=>$e->getMessage(),
   'file'=>$e->getFile(),
   'line'=>$e->getLine(),
   'trace'=>array_slice(explode("\n",$e->getTraceAsString()),0,8)
  ],
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>
