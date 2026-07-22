<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function lf_one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
function lf_all($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function lf_table($t){$r=lf_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return (int)($r['c']??0)>0;}
$key=(string)($_GET['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$executives=[];
if(lf_table('goliath_executive_activity_v110')){
 $executives=lf_all("SELECT executive_key,display_name,department,current_mode,current_mission_uid,current_action,status,last_heartbeat_at FROM goliath_executive_activity_v110 ORDER BY FIELD(executive_key,'goliath','scout','jessica','shakespeare','scorsese','einstein','columbo','prospector','rockefeller','pandora','mozart','sherlock')");
}
$events=lf_table('goliath_live_events_v111')?lf_all("SELECT * FROM goliath_live_events_v111 ORDER BY id DESC LIMIT 30"):[];
$handoffs=lf_table('goliath_required_handoffs_v110')?lf_all("SELECT * FROM goliath_required_handoffs_v110 WHERE status NOT IN ('complete','completed','waived') ORDER BY priority DESC,id DESC LIMIT 20"):[];
$services=lf_table('goliath_local_service_status_v111')?lf_all("SELECT service_key,status,endpoint,details,last_seen_at FROM goliath_local_service_status_v111 ORDER BY service_key"):[];
$calendar=[];
if(lf_table('social_calendar_items'))$calendar=lf_all("SELECT id,title,platform,status,scheduled_at FROM social_calendar_items WHERE scheduled_at>=NOW() ORDER BY scheduled_at ASC LIMIT 8");
elseif(lf_table('goliath_social_calendar'))$calendar=lf_all("SELECT * FROM goliath_social_calendar WHERE scheduled_at>=NOW() ORDER BY scheduled_at ASC LIMIT 8");

$counts=[];
if(lf_table('scorsese_comfy_jobs'))$counts['scorsese']=lf_one("SELECT SUM(status='queued') queued,SUM(status IN ('working','rendering')) working,SUM(status IN ('complete','completed','ready')) complete,SUM(status='failed') failed FROM scorsese_comfy_jobs");
if(lf_table('goliath_missions'))$counts['missions']=lf_one("SELECT COUNT(*) total,SUM(status NOT IN ('complete','completed','delivered','archived','canceled')) active FROM goliath_missions");
if(lf_table('internal_crm_contacts'))$counts['crm']=lf_one("SELECT COUNT(*) contacts,SUM(COALESCE(best_phone,phone_1,phone,existing_phone,'')<>'') phones,SUM(COALESCE(best_email,email_1,email,existing_email,'')<>'') emails FROM internal_crm_contacts");

echo json_encode(['ok'=>true,'version'=>'V111.0 Live Feed','executives'=>$executives,'events'=>$events,'handoffs'=>$handoffs,'services'=>$services,'calendar'=>$calendar,'counts'=>$counts,'server_time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>