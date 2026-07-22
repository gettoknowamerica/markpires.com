<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
function a1159_key():string{if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;return 'timetomakethedonuts';}
function a1159_table(string $t):bool{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return (int)($r['c']??0)>0;}
function a1159_one(string $s,array $p=[]):array{try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return ['_error'=>$e->getMessage()];}}
function a1159_all(string $s,array $p=[]):array{try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [['_error'=>$e->getMessage()]];}}
$key=(string)($_GET['key']??'');if(!hash_equals(a1159_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 $tables=['goliath_v112_missions','goliath_v112_stages','goliath_v112_artifacts','local_ai_tasks','goliath_conversations_v111','goliath_messages_v111','scorsese_comfy_jobs','goliath_social_accounts','goliath_social_queue','goliath_review_queue','goliath_notifications'];
 $present=[];foreach($tables as $t)$present[$t]=a1159_table($t);
 $missions=$present['goliath_v112_missions']?a1159_one("SELECT COUNT(*) total,SUM(status='queued') queued,SUM(status='working') working,SUM(status='delivered') delivered,SUM(status='failed') failed FROM goliath_v112_missions"):[];
 $stageHealth=$present['goliath_v112_stages']?a1159_all("SELECT status,COUNT(*) count FROM goliath_v112_stages GROUP BY status ORDER BY status"):[];
 $missingOriginator=[];
 if($present['goliath_v112_missions']&&$present['goliath_v112_stages'])$missingOriginator=a1159_all("SELECT m.id,m.title,m.originator_key,m.status FROM goliath_v112_missions m WHERE NOT EXISTS(SELECT 1 FROM goliath_v112_stages s WHERE s.mission_id=m.id AND s.stage_key='originator_final_review') ORDER BY m.id DESC LIMIT 30");
 $currentStages=[];
 if($present['goliath_v112_missions']&&$present['goliath_v112_stages'])$currentStages=a1159_all("SELECT m.id mission_id,m.title,m.originator_key,m.status mission_status,m.current_stage_no,s.executive_key,s.stage_key,s.status stage_status,s.local_task_id,s.last_error FROM goliath_v112_missions m LEFT JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no WHERE m.status IN ('queued','working') ORDER BY m.priority DESC,m.id LIMIT 50");
 $tasks=$present['local_ai_tasks']?a1159_all("SELECT task_type,status,COUNT(*) count FROM local_ai_tasks GROUP BY task_type,status ORDER BY task_type,status"):[];
 $ask=$present['local_ai_tasks']?a1159_all("SELECT id,status,task_type,created_at,updated_at,LEFT(prompt,120) prompt_preview FROM local_ai_tasks WHERE task_type='ask_goliath_live_v111' ORDER BY id DESC LIMIT 10"):[];
 $comfy=$present['scorsese_comfy_jobs']?a1159_all("SELECT status,COUNT(*) count FROM scorsese_comfy_jobs GROUP BY status ORDER BY status"):[];
 $social=$present['goliath_social_accounts']?a1159_all("SELECT platform_key,platform_name,username,status,last_checked_at FROM goliath_social_accounts ORDER BY platform_key"):[];
 $socialQueue=$present['goliath_social_queue']?a1159_all("SELECT status,COUNT(*) count FROM goliath_social_queue GROUP BY status ORDER BY status"):[];
 $legacySocialFiles=[
  'social_core_uses_supabase'=>is_file(__DIR__.'/social/social-core.php') && strpos((string)file_get_contents(__DIR__.'/social/social-core.php'),'SUPABASE_URL')!==false,
  'platform_publishers_are_stubs'=>true
 ];
 echo json_encode(['ok'=>true,'version'=>'V115.9 Full OS Orchestration Audit','tables'=>$present,'mission_counts'=>$missions,'stage_health'=>$stageHealth,'missions_missing_originator_final_review'=>$missingOriginator,'active_mission_positions'=>$currentStages,'local_task_counts'=>$tasks,'recent_ask_goliath'=>$ask,'scorsese_comfy_counts'=>$comfy,'social_accounts'=>$social,'social_queue'=>$socialQueue,'architecture_flags'=>$legacySocialFiles,'verdict'=>[
  'orchestration'=>'The sequential ring exists. A mission is production-safe only when every mission has an originator_final_review stage and current_stage_no advances one tangible artifact at a time.',
  'voice'=>'Kokoro must be TTS only. Ollama/Hermes/OpenClaw remains the reasoning layer. V115.9 rejects audio uploads for every non-conversation task.',
  'social'=>'The included platform publisher files are draft-mode stubs and social/social-core.php still references Supabase. Live OAuth/API publishing is not complete.',
  'scorsese'=>'ComfyUI has previously returned real assets, but current connection health and queued/error counts must be checked locally against the active workflow and model files.'
 ],'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>
