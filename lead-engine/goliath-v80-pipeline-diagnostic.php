<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function vd_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function vd_count($sql,$p=[]){try{return (int)((gdb_one($sql,$p)?:['c'=>0])['c']);}catch(Throwable $e){return 0;}}
$out=['ok'=>true,'version'=>'V80 Pipeline Diagnostic','counts'=>[],'next_tasks'=>[],'recent_deliverables'=>[]];
foreach(['local_ai_tasks','executive_commissions','goliath_deliverables','goliath_review_queue','scorsese_comfy_jobs','internal_crm_contacts'] as $t)$out['counts'][$t]=vd_table($t)?vd_count("SELECT COUNT(*) c FROM $t"):false;
if(vd_table('local_ai_tasks'))$out['next_tasks']=gdb_all("SELECT id,commission_id,agent,task_type,status,priority,progress,updated_at FROM local_ai_tasks WHERE status IN ('queued','working') ORDER BY priority DESC, updated_at ASC LIMIT 25");
if(vd_table('goliath_deliverables'))$out['recent_deliverables']=gdb_all("SELECT id,executive,executive_key,deliverable_type,title,status,review_status,evidence_status,public_url,output_url,created_at FROM goliath_deliverables WHERE evidence_status<>'legacy_archive' ORDER BY id DESC LIMIT 20");
if(vd_table('scorsese_comfy_jobs'))$out['scorsese']=gdb_all("SELECT id,title,status,progress,output_url,video_url,image_url,updated_at FROM scorsese_comfy_jobs ORDER BY id DESC LIMIT 20");
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>