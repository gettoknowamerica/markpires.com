<?php
/**
 * Goliath Omni OS v58.0
 * Scorsese Media Intake Upload
 * Uploads files to /data/scorsese_raw and creates a production command.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function fail($msg, $extra = []) {
  http_response_code(400);
  echo json_encode(array_merge(['success'=>false,'error'=>$msg], $extra), JSON_PRETTY_PRINT);
  exit;
}
function ok($payload) { echo json_encode(array_merge(['success'=>true], $payload), JSON_PRETTY_PRINT); exit; }
function cfgv($name, $fallback='') { return defined($name) ? constant($name) : (getenv($name) ?: $fallback); }
function sb($method, $endpoint, $payload=null){
  if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'error'=>'Supabase config missing'];
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = ['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch = curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'data'=>$data,'raw'=>$body];
}

$key = $_POST['key'] ?? $_GET['key'] ?? '';
$expected = cfgv('AFTER_HOURS_CRON_KEY', 'timetomakethedonuts');
if ($expected && !hash_equals($expected, $key)) fail('Invalid key');

if (empty($_FILES['media']) || !is_uploaded_file($_FILES['media']['tmp_name'])) fail('No media file received.');

$project = trim($_POST['project_name'] ?? 'Scorsese Project');
$brand = trim($_POST['brand'] ?? 'mark_pires');
$notes = trim($_POST['director_notes'] ?? 'Create multiple short-form cuts, identify the best story arc, and prepare title/description ideas.');
$priority = (int)($_POST['priority'] ?? 95);

$allowed = ['mp4','mov','m4v','webm','avi','mkv','jpg','jpeg','png','webp','mp3','wav','m4a'];
$orig = $_FILES['media']['name'];
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) fail('Unsupported file type', ['extension'=>$ext]);

$uploadDir = dirname(__DIR__) . '/data/scorsese_raw';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
$slug = preg_replace('/[^a-z0-9]+/','-', strtolower($project));
$slug = trim($slug,'-') ?: 'scorsese-project';
$stamp = gmdate('Ymd-His');
$safeName = $slug . '-' . $stamp . '-' . preg_replace('/[^a-zA-Z0-9_.-]+/','-', $orig);
$dest = $uploadDir . '/' . $safeName;

if (!move_uploaded_file($_FILES['media']['tmp_name'], $dest)) fail('Upload failed while moving file to Scorsese raw vault.');
@chmod($dest, 0664);
$size = filesize($dest);
$urlPath = '/data/scorsese_raw/' . $safeName;
$kind = in_array($ext, ['jpg','jpeg','png','webp'], true) ? 'image' : (in_array($ext, ['mp3','wav','m4a'], true) ? 'audio' : 'video');

$mediaRow = [[
  'department'=>'Scorsese',
  'project_name'=>$project,
  'brand'=>$brand,
  'kind'=>$kind,
  'original_filename'=>$orig,
  'stored_filename'=>$safeName,
  'file_url'=>$urlPath,
  'file_size_bytes'=>$size ?: 0,
  'status'=>'stored',
  'director_notes'=>$notes,
  'metadata'=>['source'=>'scorsese_media_upload_v58','extension'=>$ext],
  'created_at'=>gmdate('c'),
  'updated_at'=>gmdate('c')
]];
$media = sb('POST','scorsese_media_vault', $mediaRow);
$mediaId = $media['ok'] && !empty($media['data'][0]['id']) ? $media['data'][0]['id'] : null;

$commandPayload = [[
  'department'=>'Scorsese',
  'command_type'=>'production_edit',
  'title'=>'Scorsese Production Cut — '.$project,
  'prompt'=>$notes,
  'priority'=>$priority,
  'status'=>'queued',
  'metadata'=>[
    'project_name'=>$project,
    'brand'=>$brand,
    'media_vault_id'=>$mediaId,
    'clips'=>[[ 'name'=>$safeName, 'url'=>$urlPath, 'kind'=>$kind, 'size'=>$size ]],
    'requested_outputs'=>['youtube_master','short_1','short_2','short_3','titles','descriptions']
  ],
  'created_at'=>gmdate('c'),
  'updated_at'=>gmdate('c')
]];
$cmd = sb('POST','goliath_commands', $commandPayload);

sb('POST','goliath_events', [[
  'department'=>'Scorsese',
  'event_type'=>'media_intake',
  'title'=>'Scorsese received raw media',
  'detail'=>$project.' · '.round(($size?:0)/1048576,1).' MB · '.$orig,
  'roi_estimate'=>7500,
  'confidence'=>92,
  'status'=>'queued',
  'phase'=>'media_intake',
  'progress'=>20,
  'link_url'=>'/dashboard/scorsese-media-vault.php',
  'metadata'=>['media_vault_id'=>$mediaId,'file_url'=>$urlPath]
]]);

ok([
  'version'=>'58.0',
  'message'=>'Media stored in Scorsese raw vault and production command queued.',
  'file_url'=>$urlPath,
  'file_size_mb'=>round(($size?:0)/1048576,2),
  'media_vault_id'=>$mediaId,
  'command_created'=>$cmd['ok'],
  'command'=>$cmd['data'] ?? $cmd
]);
