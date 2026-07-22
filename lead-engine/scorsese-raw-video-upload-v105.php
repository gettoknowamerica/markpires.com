<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 if(empty($_FILES['raw_video'])||!is_uploaded_file($_FILES['raw_video']['tmp_name'])){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'raw_video_required']);exit;}
 function uid105($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function col105($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins105($t,$row){$safe=[];foreach($row as $k=>$v){if(col105($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 $orig=$_FILES['raw_video']['name']??'raw-video.mp4'; $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
 $allowed=['mp4','mov','m4v','webm'];
 if(!in_array($ext,$allowed,true)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'video_type_not_allowed']);exit;}
 $sub=date('Y/m'); $dir=dirname(__DIR__).'/dashboard/assets/scorsese/raw/'.$sub; if(!is_dir($dir))mkdir($dir,0775,true);
 $safe=preg_replace('/[^a-zA-Z0-9._-]+/','-',pathinfo($orig,PATHINFO_FILENAME));
 $name=date('Ymd-His').'-'.$safe.'.'.$ext; $dest=$dir.'/'.$name;
 if(!move_uploaded_file($_FILES['raw_video']['tmp_name'],$dest))throw new Exception('could_not_save_video');
 $url='/dashboard/assets/scorsese/raw/'.$sub.'/'.$name;
 $title=trim($_POST['title']??$safe); $style=trim($_POST['brand_style']??'Discover Connecticut'); $prompt=trim($_POST['prompt']??'Find the strongest hooks, emotional moments, useful clips, captions, titles, and shorts.');
 $projectUid=uid105('rawvid');
 $deliverables=['shorts'=>'pending','reels'=>'pending','tiktok'=>'pending','youtube_clips'=>'pending','captions'=>'pending','thumbnails'=>'pending','score'=>'pending'];
 ins105('scorsese_raw_projects',['project_uid'=>$projectUid,'title'=>$title,'brand_style'=>$style,'source_type'=>'raw_video_upload','original_filename'=>$orig,'file_url'=>$url,'prompt'=>$prompt,'status'=>'uploaded','score'=>0,'deliverables_json'=>json_encode($deliverables,JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 if(col105('goliath_missions','mission_uid')){
   ins105('goliath_missions',['mission_uid'=>uid105('mission'),'title'=>'Repurpose raw video: '.$title,'mission_type'=>'scorsese_raw_video_repurpose','source'=>'scorsese_raw_projects','priority'=>92,'status'=>'proposed','owner_executive'=>'scorsese','assigned_executives_json'=>json_encode(['goliath','scorsese','shakespeare','mozart','jessica'],JSON_UNESCAPED_SLASHES),'mission_packet_json'=>json_encode(['project_uid'=>$projectUid,'file_url'=>$url,'brand_style'=>$style,'prompt'=>$prompt],JSON_UNESCAPED_SLASHES),'outcome_goal'=>'Turn Mark’s raw footage into shorts, reels, captions, thumbnails, and publishable clips.','next_action'=>'Scorsese should identify hooks, clip points, titles, captions, and thumbnail concepts.','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 }
 echo json_encode(['ok'=>true,'version'=>'V105.0 Scorsese Raw Video Upload','project_uid'=>$projectUid,'file_url'=>$url,'next'=>'Open /dashboard/scorsese-studio-pro-v105.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V105.0 Raw Upload','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>