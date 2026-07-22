<?php
/** Goliath Omni V58.6 - Scorsese prompt-only video commission */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$raw=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$raw['key'] ?? '';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($expected && !hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function sb($method,$endpoint,$payload=null){
 if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'error'=>'Supabase missing'];
 $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
 $h=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>45]);
 if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
 $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$data=json_decode($body,true);
 return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'data'=>$data,'raw'=>$body];
}
$prompt=trim($raw['prompt'] ?? '');
if($prompt===''){echo json_encode(['success'=>false,'error'=>'Prompt is required']);exit;}
$name=trim($raw['project_name'] ?? 'Scorsese Prompt Video');
$template=$raw['template'] ?? 'Video Prompt';
$row=[
 'project_name'=>$name,
 'brand'=>$raw['brand'] ?? 'LegacySaved',
 'template'=>$template,
 'production_template'=>$template,
 'aspect_ratio'=>$raw['aspect_ratio'] ?? '9:16',
 'director_notes'=>$prompt,
 'original_filename'=>null,
 'stored_path'=>null,
 'stored_url'=>null,
 'media_url'=>null,
 'file_size'=>0,
 'status'=>'queued',
 'phase'=>'prompt_video_commissioned',
 'progress'=>8,
 'metadata'=>['source'=>'scorsese_prompt_commission_v58_6','requires_upload'=>false,'plugin_policy'=>'request_best_available_tools'],
 'updated_at'=>gmdate('c')
];
$ins=sb('POST','scorsese_media_projects',[$row]);
$projectId=$ins['ok'] && !empty($ins['data'][0]['id']) ? $ins['data'][0]['id'] : null;
$directorPrompt="You are Scorsese, Executive Creative Director of Goliath Omni. Create a premium video from this prompt without requiring uploaded footage. Ask: what would a $50 million Hollywood production do to make this unforgettable, then recreate the strongest possible version using available local tools and plugins. Use the plugin registry at your discretion: ComfyUI, Remotion, AIFFmpeg, WhisperX if narration/transcript is needed, SAM2/IP Adapter/PULID for character/subject work, RIFE/Flowframes/upscalers for polish, Kokoro/Piper for voice if needed. Return review-ready content, thumbnail direction, captions, director log, and next handoff notes.

PROMPT:
".$prompt;
$task=sb('POST','local_ai_tasks',[[
 'task_type'=>'director_video',
 'model'=>'local-scorsese',
 'prompt'=>$directorPrompt,
 'status'=>'queued',
 'priority'=>140,
 'metadata'=>['agent'=>'Scorsese','version'=>'58.6','project_id'=>$projectId,'project_name'=>$name,'brand'=>$row['brand'],'template'=>$template,'source'=>'scorsese_prompt_video','requested_outputs'=>['generated_video','thumbnail','captions','description_brief','director_log'],'plugin_policy'=>'request_best_available_tools']
]]);
sb('POST','goliath_events',[['department'=>'Scorsese','event_type'=>'scorsese_prompt_video','title'=>'Scorsese prompt video commissioned','detail'=>$name.' · '.$template,'roi_estimate'=>7500,'confidence'=>92,'status'=>'queued','phase'=>'prompt_video','progress'=>8,'link_url'=>'/dashboard/scorsese-media-center.php','metadata'=>['project_id'=>$projectId]]]);
echo json_encode(['success'=>true,'version'=>'58.6','project_id'=>$projectId,'task'=>$task['data'][0]??$task['data']??null,'next'=>'Run the local Director/Scorsese worker so it can claim director_video tasks.'],JSON_PRETTY_PRINT);
?>
