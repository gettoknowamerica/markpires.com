<?php
/**
 * V100 Production Collaboration Engine
 * Converts individual executive work into coordinated production packages.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 if(file_exists(__DIR__.'/scorsese-comfy-bridge.php')) require_once __DIR__.'/scorsese-comfy-bridge.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function v100_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function v100_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v100_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v100_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(v100_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function v100_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(v100_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function v100_slug($s){$s=strtolower(trim((string)$s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
 function v100_title_case($s){
   $s=strtolower(str_replace(['-','_'], ' ', trim((string)$s)));
   $s=preg_replace('/\s+/', ' ', $s);
   $words=explode(' ', $s);
   $small=['a','an','and','as','at','but','by','for','from','in','into','nor','of','on','or','the','to','with'];
   $out=[];
   foreach($words as $i=>$w){
     if($w==='ct') $out[]='CT';
     elseif($w==='cma') $out[]='CMA';
     elseif($w==='connecticut') $out[]='Connecticut';
     elseif($w==='fairfield') $out[]='Fairfield';
     elseif($w==='westport') $out[]='Westport';
     elseif($w==='stamford') $out[]='Stamford';
     elseif($w==='greenwich') $out[]='Greenwich';
     elseif($w==='norwalk') $out[]='Norwalk';
     elseif($i>0 && in_array($w,$small,true)) $out[]=$w;
     else $out[]=ucfirst($w);
   }
   return implode(' ', $out);
 }
 function v100_task($pkg,$from,$to,$type,$title,$instructions,$sourceTable=null,$sourceId=null,$priority=800,$meta=[]){
   $exists=gdb_one("SELECT id FROM executive_collaboration_tasks WHERE package_id=? AND to_executive=? AND task_type=? AND status IN ('queued','working','complete') LIMIT 1",[$pkg,$to,$type]);
   if($exists) return (int)$exists['id'];
   return v100_insert('executive_collaboration_tasks',[
    'task_uid'=>v100_uid('xct'),
    'package_id'=>$pkg,
    'from_executive'=>$from,
    'to_executive'=>$to,
    'task_type'=>$type,
    'title'=>$title,
    'instructions'=>$instructions,
    'status'=>'queued',
    'priority'=>$priority,
    'source_table'=>$sourceTable,
    'source_id'=>$sourceId,
    'metadata'=>json_encode($meta,JSON_UNESCAPED_SLASHES),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);
 }
 function v100_item($pkg,$exec,$type,$title,$status='needed',$sourceTable=null,$sourceId=null,$url=null,$preview=''){
   $exists=gdb_one("SELECT id FROM production_package_items WHERE package_id=? AND executive_key=? AND item_type=? LIMIT 1",[$pkg,$exec,$type]);
   if($exists) return (int)$exists['id'];
   return v100_insert('production_package_items',[
    'item_uid'=>v100_uid('ppi'),
    'package_id'=>$pkg,
    'executive_key'=>$exec,
    'item_type'=>$type,
    'title'=>$title,
    'status'=>$status,
    'source_table'=>$sourceTable,
    'source_id'=>$sourceId,
    'direct_url'=>$url,
    'preview_text'=>$preview,
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);
 }
 function v100_scorsese_job($pkg,$title,$prompt,$sourceTable=null,$sourceId=null){
   if(!v100_table('scorsese_comfy_jobs')) return null;
   $exists=gdb_one("SELECT id FROM scorsese_comfy_jobs WHERE production_package_id=? AND status IN ('queued','working','rendering','complete','completed') LIMIT 1",[$pkg]);
   if($exists) return (int)$exists['id'];
   $positive="Premium cinematic real estate and brand marketing video for Mark Pires. {$title}. {$prompt}. Elegant, high-end, emotional, polished lighting, dynamic camera movement, no text artifacts, no distorted people.";
   $workflow=null;
   if(function_exists('scb_build_wan_workflow')) $workflow=scb_build_wan_workflow($positive,null,$title);
   return v100_insert('scorsese_comfy_jobs',[
    'job_uid'=>v100_uid('comfy'),
    'source_completion_id'=>null,
    'source_commission_id'=>null,
    'production_package_id'=>$pkg,
    'title'=>$title,
    'prompt'=>$positive,
    'workflow_json'=>$workflow?json_encode($workflow,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,
    'status'=>'queued',
    'priority'=>96,
    'progress'=>0,
    'media_type'=>'video',
    'metadata'=>json_encode(['source'=>'v100_production_collaboration','source_table'=>$sourceTable,'source_id'=>$sourceId,'workflow_injected'=>(bool)$workflow],JSON_UNESCAPED_SLASHES),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
   ]);
 }

 $limit=max(1,min(200,(int)($_GET['limit']??50)));
 $created=[];$updated=[];$tasks=[];$jobs=[];

 if(v100_table('shakespeare_content_packages')){
   $rows=gdb_all("SELECT * FROM shakespeare_content_packages WHERE COALESCE(production_package_id,0)=0 ORDER BY created_at DESC LIMIT {$limit}")?:[];
   foreach($rows as $r){
     $title=$r['title'] ?: v100_title_case($r['slug']??'Goliath Production Package');
     $direct=$r['published_path'] ?: ('/dashboard/shakespeare-authority-center.php#pkg-'.$r['id']);
     $pkg=v100_insert('production_packages',[
      'package_uid'=>v100_uid('pkg'),
      'title'=>$title,
      'package_type'=>'content_campaign',
      'primary_executive'=>'shakespeare',
      'source_table'=>'shakespeare_content_packages',
      'source_id'=>(int)$r['id'],
      'status'=>'assembling',
      'priority'=>(int)($r['priority']??850),
      'completion_score'=>25,
      'approval_status'=>'needs_review',
      'package_summary'=>'V100 package generated from Shakespeare content. Goliath will coordinate Pandora, Scorsese, Einstein, Jessica, Mozart, and Sherlock.',
      'direct_url'=>'/dashboard/shakespeare-authority-center.php#pkg-'.$r['id'],
      'tv_payload_json'=>json_encode(['open'=>'shakespeare','package_id'=>$r['id'],'title'=>$title],JSON_UNESCAPED_SLASHES),
      'metadata'=>json_encode(['source'=>'shakespeare','published_path'=>$r['published_path']??null],JSON_UNESCAPED_SLASHES),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
     ]);
     v100_update('shakespeare_content_packages',(int)$r['id'],['production_package_id'=>$pkg,'updated_at'=>gdb_now()]);
     v100_item($pkg,'shakespeare','article',$title,'created','shakespeare_content_packages',(int)$r['id'],'/dashboard/shakespeare-authority-center.php#pkg-'.$r['id'],$r['summary']??'');
     v100_item($pkg,'pandora','hero_graphic','Hero graphic for '.$title,'needed');
     v100_item($pkg,'scorsese','short_video','Companion video for '.$title,'needed');
     v100_item($pkg,'einstein','seo_review','SEO/AEO review for '.$title,'needed');
     v100_item($pkg,'sherlock','qa_review','QA and link verification for '.$title,'needed');
     v100_item($pkg,'jessica','email_campaign','Email/use-case for '.$title,'needed');
     v100_item($pkg,'mozart','audio_recommendation','Music/audio recommendation for '.$title,'needed');

     $brief="Source article: {$title}\nPublished path: ".($r['published_path']?:'not published yet')."\nNeed a complete production package, not a standalone blog.";
     $tasks[]=v100_task($pkg,'shakespeare','pandora','design_assets','Design visuals: '.$title,"Create hero image brief, email header, social square, and any infographic ideas. {$brief}",'shakespeare_content_packages',(int)$r['id'],880);
     $tasks[]=v100_task($pkg,'shakespeare','scorsese','companion_video','Create companion video: '.$title,"Create a cinematic 30–60 second vertical video concept and render prompt. {$brief}",'shakespeare_content_packages',(int)$r['id'],890);
     $tasks[]=v100_task($pkg,'shakespeare','einstein','seo_aeo_review','SEO/AEO review: '.$title,"Score SEO, AEO, EEAT, schema, internal links, FAQs, local keywords, and missing CT terms. {$brief}",'shakespeare_content_packages',(int)$r['id'],870);
     $tasks[]=v100_task($pkg,'shakespeare','sherlock','quality_assurance','QA package: '.$title,"Check links, title casing, spelling, CTA, missing visuals, factual gaps, and broken assets. {$brief}",'shakespeare_content_packages',(int)$r['id'],860);
     $tasks[]=v100_task($pkg,'shakespeare','jessica','campaign_match','Add to Jessica content library: '.$title,"Decide which lead types should receive this article and create email-use guidance. {$brief}",'shakespeare_content_packages',(int)$r['id'],820);
     $tasks[]=v100_task($pkg,'shakespeare','mozart','audio_direction','Audio direction: '.$title,"If Scorsese makes video, recommend music mood, pacing, voice tone, and intro/outro style. {$brief}",'shakespeare_content_packages',(int)$r['id'],760);

     $job=v100_scorsese_job($pkg,'Companion video — '.$title,$brief,'shakespeare_content_packages',(int)$r['id']);
     if($job)$jobs[]=$job;

     $created[]=['package_id'=>$pkg,'source'=>'shakespeare','title'=>$title,'scorsese_job_id'=>$job];
   }
 }

 if(v100_table('jessica_email_drafts')){
   $rows=gdb_all("SELECT * FROM jessica_email_drafts WHERE COALESCE(production_package_id,0)=0 ORDER BY created_at DESC LIMIT {$limit}")?:[];
   foreach($rows as $r){
     $title=$r['subject'] ?: ('Jessica Email for '.($r['to_name']?:'Lead'));
     $pkg=v100_insert('production_packages',[
      'package_uid'=>v100_uid('pkg'),
      'title'=>$title,
      'package_type'=>'relationship_touch',
      'primary_executive'=>'jessica',
      'source_table'=>'jessica_email_drafts',
      'source_id'=>(int)$r['id'],
      'status'=>'assembling',
      'priority'=>820,
      'completion_score'=>35,
      'approval_status'=>'needs_review',
      'package_summary'=>'V100 package generated from Jessica draft. Goliath will verify links and supporting content before send.',
      'direct_url'=>'/dashboard/jessica-relationship-center.php#draft-'.$r['id'],
      'metadata'=>json_encode(['recommended_blog'=>$r['recommended_blog']??null],JSON_UNESCAPED_SLASHES),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
     ]);
     v100_update('jessica_email_drafts',(int)$r['id'],['production_package_id'=>$pkg,'updated_at'=>gdb_now()]);
     v100_item($pkg,'jessica','email_draft',$title,'created','jessica_email_drafts',(int)$r['id'],'/dashboard/jessica-relationship-center.php#draft-'.$r['id'],$r['body_text']??'');
     $tasks[]=v100_task($pkg,'jessica','sherlock','link_qa','Verify Jessica email links: '.$title,'Check every link in this email and confirm the supporting page exists before Mark approves send.','jessica_email_drafts',(int)$r['id'],930);
     if(!empty($r['recommended_blog'])){
       $tasks[]=v100_task($pkg,'jessica','shakespeare','supporting_article','Ensure supporting article exists: '.$r['recommended_blog'],'If this blog page is missing, create it immediately and queue improvements for visuals, SEO, and video.','jessica_email_drafts',(int)$r['id'],940,['recommended_blog'=>$r['recommended_blog']]);
     }
     $created[]=['package_id'=>$pkg,'source'=>'jessica','title'=>$title];
   }
 }

 if(v100_table('scout_intel_dossiers')){
   $rows=gdb_all("SELECT * FROM scout_intel_dossiers WHERE COALESCE(production_package_id,0)=0 AND handoff_status='ready_for_mark' ORDER BY updated_at DESC LIMIT {$limit}")?:[];
   foreach($rows as $r){
     $title='Call-ready dossier — '.($r['owner_name']?:('Contact #'.$r['id']));
     $pkg=v100_insert('production_packages',[
      'package_uid'=>v100_uid('pkg'),
      'title'=>$title,
      'package_type'=>'lead_opportunity',
      'primary_executive'=>'scout',
      'source_table'=>'scout_intel_dossiers',
      'source_id'=>(int)$r['id'],
      'status'=>'ready_for_review',
      'priority'=>900,
      'completion_score'=>(int)($r['completion_score']??70),
      'approval_status'=>'needs_review',
      'package_summary'=>'Scout-ready opportunity. Jessica and Sherlock should support follow-up.',
      'direct_url'=>'/dashboard/scout-ready-contacts.php#contact-'.$r['id'],
      'metadata'=>json_encode(['town'=>$r['town']??null,'source_label'=>$r['source_label']??null],JSON_UNESCAPED_SLASHES),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
     ]);
     v100_update('scout_intel_dossiers',(int)$r['id'],['production_package_id'=>$pkg,'updated_at'=>gdb_now()]);
     v100_item($pkg,'scout','dossier',$title,'created','scout_intel_dossiers',(int)$r['id'],'/dashboard/scout-ready-contacts.php#contact-'.$r['id'],$r['call_strategy']??'');
     $tasks[]=v100_task($pkg,'scout','jessica','first_touch','Prepare first touch: '.$title,'Draft or verify the first human-touch email, calendar/follow-up reminder, and recommended supporting article.','scout_intel_dossiers',(int)$r['id'],900);
     $tasks[]=v100_task($pkg,'scout','sherlock','dossier_qa','Verify dossier: '.$title,'Check contact info, source logic, expired/withdrawn/absentee classification, and missing fields.','scout_intel_dossiers',(int)$r['id'],890);
     $created[]=['package_id'=>$pkg,'source'=>'scout','title'=>$title];
   }
 }

 if(v100_table('relationship_timeline')){
   foreach($created as $c){
     v100_insert('relationship_timeline',[
      'event_uid'=>v100_uid('rel'),
      'executive_key'=>'goliath',
      'event_type'=>'v100_package_created',
      'title'=>'V100 package created: '.$c['title'],
      'details'=>'Goliath grouped work into a production package and assigned collaboration tasks.',
      'metadata'=>json_encode($c,JSON_UNESCAPED_SLASHES),
      'priority'=>95,
      'is_new'=>1,
      'created_at'=>gdb_now()
     ]);
   }
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V100 Production Collaboration Engine',
  'created_count'=>count($created),
  'created'=>$created,
  'tasks_touched'=>array_values(array_unique(array_filter($tasks))),
  'scorsese_jobs_created'=>array_values(array_unique(array_filter($jobs))),
  'next'=>'Run /lead-engine/v100-scorsese-force-push.php?key=timetomakethedonuts, then keep the local Scorsese worker running.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100 Production Collaboration Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>