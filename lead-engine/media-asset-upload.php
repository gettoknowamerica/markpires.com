<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function sb($m,$ep,$p=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return ['ok'=>$h>=200&&$h<300,'body'=>$b,'data'=>is_array($d)?$d:[]];}
try{
$key=$_POST['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$type=$_POST['asset_type']??'logos'; $allowedDirs=['logos','thumbs','raw']; if(!in_array($type,$allowedDirs,true))$type='logos';
if(empty($_FILES['file']['tmp_name'])){echo json_encode(['success'=>false,'error'=>'No file']);exit;}
$name=preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES['file']['name']));
$dir=__DIR__.'/../uploads/media/'.$type; if(!is_dir($dir))mkdir($dir,0755,true);
$final=date('Ymd_His').'_'.$name; $path=$dir.'/'.$final;
if(!move_uploaded_file($_FILES['file']['tmp_name'],$path)){echo json_encode(['success'=>false,'error'=>'Move failed']);exit;}
$url='/uploads/media/'.$type.'/'.$final;
sb('POST','media_assets',[['asset_type'=>$type==='raw'?'raw':($type==='thumbs'?'thumbnail':'logo'),'file_name'=>$final,'file_url'=>$url,'mime_type'=>$_FILES['file']['type']??'','file_size'=>filesize($path),'title'=>$name,'created_at'=>date('c'),'updated_at'=>date('c')]]);
echo json_encode(['success'=>true,'url'=>$url,'file'=>$final],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>