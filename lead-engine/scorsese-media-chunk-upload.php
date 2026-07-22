<?php
/** Goliath Omni OS v58.2 - Scorsese Media Center chunked upload handler */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
$key=$_POST['key'] ?? $_GET['key'] ?? '';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($expected && !hash_equals($expected,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function safe($s){return preg_replace('/[^a-zA-Z0-9._-]+/','-',basename((string)$s));}
function sb($method,$endpoint,$payload=null){
 if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'error'=>'Supabase missing'];
 $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
 $h=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>45]);
 if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
 $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$data=json_decode($body,true);
 return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'data'=>$data,'raw'=>$body];
}
$uploadId=safe($_POST['upload_id'] ?? '');$idx=(int)($_POST['chunk_index'] ?? -1);$total=(int)($_POST['total_chunks'] ?? 0);$filename=safe($_POST['filename'] ?? 'upload.bin');
if(!$uploadId || $idx<0 || $total<1 || empty($_FILES['chunk']['tmp_name'])){echo json_encode(['success'=>false,'error'=>'Missing chunk payload']);exit;}
$base=realpath(__DIR__.'/..');
$rawDir=$base.'/data/scorsese_raw';$tmpDir=$base.'/data/scorsese_raw/_chunks/'.$uploadId;
if(!is_dir($rawDir)) mkdir($rawDir,0755,true); if(!is_dir($tmpDir)) mkdir($tmpDir,0755,true);
$chunkPath=$tmpDir.'/chunk-'.str_pad((string)$idx,6,'0',STR_PAD_LEFT);
if(!move_uploaded_file($_FILES['chunk']['tmp_name'],$chunkPath)){echo json_encode(['success'=>false,'error'=>'Could not save chunk']);exit;}
$received=count(glob($tmpDir.'/chunk-*'));
if($received < $total){echo json_encode(['success'=>true,'status'=>'chunk_saved','received'=>$received,'total'=>$total,'progress'=>round(($received/$total)*100)]);exit;}
$finalName=date('Ymd-His').'-'.$uploadId.'-'.$filename;$finalPath=$rawDir.'/'.$finalName;$out=fopen($finalPath,'wb');
for($i=0;$i<$total;$i++){ $p=$tmpDir.'/chunk-'.str_pad((string)$i,6,'0',STR_PAD_LEFT); if(!file_exists($p)){fclose($out); echo json_encode(['success'=>false,'error'=>'Missing chunk '.$i]); exit;} $in=fopen($p,'rb'); stream_copy_to_stream($in,$out); fclose($in); }
fclose($out); foreach(glob($tmpDir.'/chunk-*') as $p) @unlink($p); @rmdir($tmpDir);
$url='/data/scorsese_raw/'.$finalName;
$row=[
 'project_name'=>$_POST['project_name'] ?: $filename,
 'brand'=>$_POST['brand'] ?: 'Mark Pires',
 'template'=>$_POST['template'] ?: 'Custom',
 'production_template'=>$_POST['template'] ?: 'Custom',
 'town'=>$_POST['town'] ?: null,
 'aspect_ratio'=>$_POST['aspect_ratio'] ?: '9:16',
 'director_notes'=>$_POST['director_notes'] ?: '',
 'original_filename'=>$filename,
 'stored_path'=>$finalPath,
 'stored_url'=>$url,
 'media_url'=>$url,
 'file_size'=>filesize($finalPath),
 'status'=>'queued',
 'phase'=>'stored_and_commissioned',
 'progress'=>15,
 'metadata'=>['upload_id'=>$uploadId,'chunks'=>$total,'source'=>'scorsese_media_center_v58_6'],
 'updated_at'=>gmdate('c')
];
$ins=sb('POST','scorsese_media_projects',[$row]);$projectId=$ins['ok'] && !empty($ins['data'][0]['id']) ? $ins['data'][0]['id'] : null;
sb('POST','goliath_events',[[
 'department'=>'Scorsese','event_type'=>'scorsese_media_uploaded','title'=>'Scorsese production commissioned','detail'=>($row['project_name'].' · '.$row['template'].' · '.round($row['file_size']/1048576,1).' MB'),'roi_estimate'=>0,'confidence'=>90,'status'=>'queued','phase'=>'media_center','progress'=>15,'link_url'=>'/dashboard/scorsese-media-center.php','metadata'=>['project_id'=>$projectId,'stored_url'=>$url]
]]);
// Queue Scorsese command when command bus exists.
sb('POST','local_ai_tasks',[['task_type'=>'scorsese_media_production','model'=>'llama3.1:8b','prompt'=>'Scorsese: immediately analyze media production '.$row['project_name'].' using template '.$row['template'].'. Director notes: '.$row['director_notes'],'status'=>'queued','priority'=>85,'metadata'=>['agent'=>'Scorsese','project_id'=>$projectId,'stored_url'=>$url,'source'=>'scorsese_media_center_v58_6']]]);
echo json_encode(['success'=>true,'status'=>'complete','project_id'=>$projectId,'stored_url'=>$url,'filename'=>$finalName,'size'=>filesize($finalPath),'progress'=>100],JSON_PRETTY_PRINT);
