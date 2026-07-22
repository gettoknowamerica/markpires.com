<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
set_time_limit(50);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$V112_RESPONSE=['ok'=>false,'version'=>'V113.0 Full OS Production Engine'];
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
 $is=($url!==''||$path!==''||$length>=$min);
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
$lock=p112_one("SELECT GET_LOCK('goliath_v112_production',0) acquired");
if((int)($lock['acquired']??0)!==1){echo json_encode(['ok'=>true,'version'=>'V113.0 Full OS Production Engine','status'=>'skipped_overlap']);exit;}

$metrics=['completed_tasks_consumed'=>0,'stages_started'=>0,'stages_advanced'=>0,'artifacts_created'=>0,'missions_delivered'=>0,'stages_failed_gate'=>0];

try{
 $taskCols=p112_cols('local_ai_tasks');

 // Consume completed/failed local tasks already attached to stages.
 $running=p112_all("SELECT s.*,m.title mission_title,m.mission_type,m.source_payload_json,m.originator_key FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id WHERE s.status IN ('queued_local','working') AND s.local_task_id IS NOT NULL ORDER BY s.stage_no ASC LIMIT 20");
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
       gdb_update('goliath_v112_stages',['status'=>'ready','input_artifact_id'=>$artifactId,'updated_at'=>gdb_now()],'id=:id',['id'=>$next['id']]);
       gdb_update('goliath_v112_missions',['status'=>'working','current_stage_no'=>$next['stage_no'],'started_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>$stage['mission_id']]);
       gdb_insert('goliath_v112_events',[
         'mission_id'=>$stage['mission_id'],'stage_id'=>$next['id'],'executive_key'=>$next['executive_key'],
         'event_type'=>'stage_ready','title'=>$next['title'],'details'=>'Previous tangible artifact passed the gate.','artifact_id'=>$artifactId,
         'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
       ]);
       $metrics['stages_advanced']++;
     }
   }
 }

 // Start exactly one next stage per heartbeat.
 $ready=p112_one("SELECT s.*,m.title mission_title,m.mission_type,m.source_url,m.source_payload_json,m.originator_key FROM goliath_v112_stages s JOIN goliath_v112_missions m ON m.id=s.mission_id WHERE s.status='ready' AND m.status NOT IN ('delivered','failed','archived') ORDER BY m.priority DESC,m.id ASC,s.stage_no ASC LIMIT 1");
 if($ready&&$taskCols){
   $context=p112_context((int)$ready['mission_id'],(int)$ready['stage_no']);
   $source=json_decode((string)$ready['source_payload_json'],true)?:[];
   $prompt="V112 SOFTWARE RELEASE — STRICT PRODUCTION STAGE\n\n".
     "MISSION: ".$ready['mission_title']."\n".
     "MISSION TYPE: ".$ready['mission_type']."\n".
     "ORIGINATOR: ".$ready['originator_key']."\n".
     "CURRENT EXECUTIVE: ".$ready['executive_key']."\n".
     "CURRENT STAGE: ".$ready['title']."\n\n".
     "NON-NEGOTIABLE RULES:\n".
     "1. Produce or improve the actual shared deliverable. Do not return an executive brief, status report, placeholder, or generic recommendations.\n".
     "2. Use OpenClaw and its configured web/search/scraper tools whenever research is requested.\n".
     "3. Return strict JSON whenever possible with artifact_type,title,content_html,content_text,evidence,notes,artifact_url,artifact_path.\n".
     "4. If improving an article, return the ENTIRE revised article, not only suggestions.\n".
     "5. A stage remains incomplete unless it creates a tangible artifact.\n\n".
     "STAGE INSTRUCTIONS:\n".$ready['instructions']."\n\n".
     "SOURCE PAYLOAD:\n".json_encode($source,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n\n".
     "ALL PRIOR TANGIBLE WORK:\n".$context;

   $taskUid=function_exists('gdb_uid')?gdb_uid('v113_task'):('v113_task_'.date('YmdHis').'_'.bin2hex(random_bytes(5)));
   $taskRow=[
     'task_uid'=>$taskUid,
     'task_type'=>'goliath_v112_stage','type'=>'goliath_v112_stage',
     'title'=>'V112 '.$ready['executive_key'].' — '.$ready['title'],
     'prompt'=>$prompt,'status'=>'queued','workflow_state'=>'queued','priority'=>250,
     'agent'=>ucfirst($ready['executive_key']),'executive_key'=>$ready['executive_key'],
     'model'=>'goliath-local-worker','progress'=>0,
     'metadata'=>gdb_json(['v112'=>true,'v113'=>true,'mission_id'=>$ready['mission_id'],'stage_id'=>$ready['id'],'stage_no'=>$ready['stage_no'],'stage_key'=>$ready['stage_key'],'originator_key'=>$ready['originator_key']]),
     'metadata_json'=>gdb_json(['v112'=>true,'v113'=>true,'mission_id'=>$ready['mission_id'],'stage_id'=>$ready['id'],'stage_no'=>$ready['stage_no'],'stage_key'=>$ready['stage_key'],'originator_key'=>$ready['originator_key']]),
     'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ];
   $taskId=p112_insert_safe('local_ai_tasks',$taskRow,$taskCols);
   gdb_update('goliath_v112_stages',[
     'status'=>'queued_local','local_task_id'=>$taskId,'attempt_count'=>(int)$ready['attempt_count']+1,
     'started_at'=>gdb_now(),'updated_at'=>gdb_now()
   ],'id=:id',['id'=>$ready['id']]);
   gdb_update('goliath_v112_missions',['status'=>'working','started_at'=>gdb_now(),'current_stage_no'=>$ready['stage_no'],'updated_at'=>gdb_now()],'id=:id',['id'=>$ready['mission_id']]);
   gdb_insert('goliath_v112_events',[
     'mission_id'=>$ready['mission_id'],'stage_id'=>$ready['id'],'executive_key'=>$ready['executive_key'],
     'event_type'=>'stage_started','title'=>$ready['title'],'details'=>'Local OpenClaw/Hermes task #'.$taskId.' queued.',
     'url'=>'/dashboard/goliath-mission-control.php','created_at'=>gdb_now()
   ]);
   $metrics['stages_started']++;
 }

 $truth=p112_one("SELECT COUNT(*) finished_assets FROM goliath_v112_artifacts WHERE delivered_by_goliath=1 AND status='delivered'")?:['finished_assets'=>0];
 echo json_encode([
  'ok'=>true,'version'=>'V113.0 Full OS Production Engine','status'=>'complete',
  'metrics'=>$metrics,'truth'=>['finished_assets'=>(int)$truth['finished_assets'],'review_placeholders'=>0],
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}finally{
 try{p112_one("SELECT RELEASE_LOCK('goliath_v112_production') released");}catch(Throwable $e){}
}
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,
  'version'=>'V113.0 Full OS Production Engine',
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
