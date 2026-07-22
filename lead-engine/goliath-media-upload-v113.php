<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
set_time_limit(120);
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function mu_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function mu_uid(string $p):string{return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5));}
$key=(string)($_POST['key']??'');
if(!hash_equals(mu_key(),$key)){http_response_code(403);exit('Bad key.');}
if(empty($_FILES['media_file'])||$_FILES['media_file']['error']!==UPLOAD_ERR_OK){http_response_code(400);exit('Media upload failed.');}

$title=trim((string)($_POST['title']??'Untitled Media'));
$brand=trim((string)($_POST['brand_key']??'markpires'));
$instructions=trim((string)($_POST['instructions']??''));
$file=$_FILES['media_file'];
$allowed=[
 'video/mp4','video/quicktime','video/webm','video/x-m4v',
 'audio/mpeg','audio/mp4','audio/wav','audio/x-wav',
 'image/jpeg','image/png','image/webp','image/heic','image/heif'
];
$finfo=new finfo(FILEINFO_MIME_TYPE);
$mime=(string)$finfo->file($file['tmp_name']);
if(!in_array($mime,$allowed,true)){http_response_code(415);exit('Unsupported media type: '.$mime);}

$docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__)),'/\\');
$dir=$docRoot.'/uploads/goliath-media/'.date('Y/m');
if(!is_dir($dir)&&!mkdir($dir,0775,true)){http_response_code(500);exit('Could not create media folder.');}
$ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
if($ext==='')$ext=str_starts_with($mime,'video/')?'mp4':(str_starts_with($mime,'audio/')?'mp3':'jpg');
$uid=mu_uid('media');
$filename=$uid.'.'.$ext;
$absolute=$dir.'/'.$filename;
if(!move_uploaded_file($file['tmp_name'],$absolute)){http_response_code(500);exit('Could not save uploaded media.');}
$relative=str_replace($docRoot,'',$absolute);
$outputs=['16:9 master','9:16 vertical','short clips','captions','chapters','thumbnail','title','description','tags'];

$mediaId=gdb_insert('goliath_media_intake_v113',[
 'media_uid'=>$uid,'title'=>$title,'brand_key'=>$brand,'instructions'=>$instructions,
 'original_name'=>$file['name'],'stored_path'=>$absolute,'public_url'=>$relative,
 'mime_type'=>$mime,'size_bytes'=>(int)$file['size'],'status'=>'queued',
 'requested_outputs_json'=>gdb_json($outputs),'created_at'=>gdb_now(),'updated_at'=>gdb_now()
]);

$taskUid=mu_uid('v113_media');
$prompt="SCORSESE V113 MEDIA PRODUCTION\n\nTITLE: $title\nBRAND: $brand\nSOURCE FILE: $absolute\nSOURCE URL: $relative\nMIME: $mime\n\nMARK'S INSTRUCTIONS:\n$instructions\n\n".
"Preserve the original source file. Analyze the complete footage. Produce a high-quality 16:9 master plan, a 9:16 vertical plan, strongest short-form clips with exact time ranges, captions, chapters, title, description, tags, and three bold click-worthy thumbnail concepts. ".
"For existing long-form footage, do not treat AI generation length as an editing limit. Use FFmpeg/non-destructive editing guidance and retain source quality. Return strict JSON with artifact_type,title,content_text,evidence,notes.";
$taskId=gdb_insert('local_ai_tasks',[
 'task_uid'=>$taskUid,'agent'=>'Scorsese','task_type'=>'goliath_v113_media_edit',
 'model'=>'goliath-local-worker','prompt'=>$prompt,'status'=>'queued','priority'=>500,'progress'=>0,
 'metadata'=>gdb_json(['v113'=>true,'media_id'=>$mediaId,'source_path'=>$absolute,'source_url'=>$relative,'outputs'=>$outputs]),
 'created_at'=>gdb_now(),'updated_at'=>gdb_now()
]);
gdb_update('goliath_media_intake_v113',['scorsese_job_id'=>$taskId,'updated_at'=>gdb_now()],'id=:id',['id'=>$mediaId]);

header('Location:/dashboard/goliath-mission-control.php?media_uploaded=1#media-intake');
exit;
?>