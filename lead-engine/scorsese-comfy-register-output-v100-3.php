<?php
/**
 * V100.3 Scorsese Comfy Output Register
 * Receives completed ComfyUI MP4/images from the local worker and attaches them to Scorsese jobs.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_POST['key']??$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function s103_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function s103_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function s103_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function s103_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(s103_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function s103_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(s103_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function s103_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}

 if(!s103_table('scorsese_comfy_jobs')) throw new Exception('scorsese_comfy_jobs missing');

 $jobId=(int)($_POST['job_id']??$_POST['id']??0);
 if(!$jobId) throw new Exception('missing job_id');

 $job=gdb_one("SELECT * FROM scorsese_comfy_jobs WHERE id=? LIMIT 1",[$jobId]);
 if(!$job) throw new Exception('job not found');

 $promptId=$_POST['remote_prompt_id']??$_POST['prompt_id']??($job['remote_prompt_id']??'');
 $original=$_POST['original_filename']??('scorsese-job-'.$jobId.'.mp4');
 $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
 if(!$ext)$ext='mp4';
 $allowed=['mp4','webm','mov','png','jpg','jpeg','gif'];
 if(!in_array($ext,$allowed,true))$ext='mp4';

 $dir=__DIR__.'/../videos/scorsese';
 if(!is_dir($dir)) mkdir($dir,0755,true);

 $slug=preg_replace('/[^a-z0-9]+/','-',strtolower((string)($job['title']??('job-'.$jobId))));
 $slug=trim($slug,'-')?:('job-'.$jobId);
 $fileName='scorsese-'.$jobId.'-'.$slug.'-'.date('Ymd-His').'.'.$ext;
 $target=$dir.'/'.$fileName;

 if(isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])){
   move_uploaded_file($_FILES['file']['tmp_name'],$target);
 } elseif(!empty($_POST['file_base64'])){
   $data=base64_decode($_POST['file_base64']);
   if($data===false) throw new Exception('invalid base64');
   file_put_contents($target,$data);
 } else {
   throw new Exception('missing file');
 }

 $public='/videos/scorsese/'.$fileName;
 $score=0;
 $title=strtolower((string)($job['title']??''));
 foreach(['luxury'=>15,'waterfront'=>15,'california'=>12,'modern'=>10,'expired'=>10,'seller'=>8,'buyer'=>8,'connecticut'=>8,'darien'=>6,'greenwich'=>8,'stamford'=>6] as $kw=>$pts){
   if(strpos($title,$kw)!==false)$score+=$pts;
 }
 $score+=min(25,(int)($job['priority']??0)/4);
 $score=max(10,min(100,(int)round($score)));

 $meta=['registered_by'=>'v100_3','prompt_id'=>$promptId,'original_filename'=>$original,'viral_score'=>$score,'registered_at'=>date('c')];

 s103_update('scorsese_comfy_jobs',$jobId,[
  'status'=>'complete',
  'progress'=>100,
  'output_url'=>$public,
  'output_path'=>$target,
  'completed_at'=>gdb_now(),
  'updated_at'=>gdb_now(),
  'result'=>'Registered completed Comfy output at '.$public,
  'metadata'=>json_encode(array_merge(json_decode($job['metadata']??'[]',true)?:[],$meta),JSON_UNESCAPED_SLASHES)
 ]);

 if(s103_table('production_package_items') && !empty($job['production_package_id'])){
   $item=gdb_one("SELECT id FROM production_package_items WHERE package_id=? AND executive_key='scorsese' AND item_type IN ('short_video','video','companion_video') LIMIT 1",[(int)$job['production_package_id']]);
   if($item){
     s103_update('production_package_items',(int)$item['id'],['status'=>'created','asset_url'=>$public,'direct_url'=>'/dashboard/scorsese-studio-pro.php?job='.$jobId,'updated_at'=>gdb_now()]);
   }
 }
 if(s103_table('relationship_timeline')){
   s103_insert('relationship_timeline',[
    'event_uid'=>s103_uid('rel'),
    'executive_key'=>'scorsese',
    'event_type'=>'video_rendered',
    'title'=>'Scorsese rendered video: '.($job['title']??('Job '.$jobId)),
    'details'=>'Video is ready in Scorsese Studio Pro. Viral score: '.$score.'/100.',
    'metadata'=>json_encode(['job_id'=>$jobId,'output_url'=>$public,'viral_score'=>$score],JSON_UNESCAPED_SLASHES),
    'priority'=>95,
    'is_new'=>1,
    'created_at'=>gdb_now()
   ]);
 }

 echo json_encode(['ok'=>true,'version'=>'V100.3 Scorsese Comfy Output Register','job_id'=>$jobId,'output_url'=>$public,'viral_score'=>$score,'next'=>'Open /dashboard/scorsese-studio-pro.php?job='.$jobId,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.3 Scorsese Comfy Output Register','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>