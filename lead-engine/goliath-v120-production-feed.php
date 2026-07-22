<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function f120_key():string{
 if(defined('AFTER_HOURS_CRON_KEY')) return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY')) return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(f120_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
try{
 $versions=gdb_all("SELECT v.id,v.mission_id,v.stage_no,v.executive_key,v.artifact_type,v.title,v.status,v.created_at,
   LEFT(COALESCE(NULLIF(v.content_text,''),v.content_html,''),500) preview,
   CHAR_LENGTH(COALESCE(NULLIF(v.content_text,''),v.content_html,'')) content_length
   FROM goliath_v118_asset_versions v
   JOIN goliath_v112_missions m ON m.id=v.mission_id
   WHERE COALESCE(v.is_tangible,1)=1 AND COALESCE(m.visible_in_production_studio,1)=1
   ORDER BY v.id DESC LIMIT 100")?:[];
 foreach($versions as &$v){
  $v['review_url']='/dashboard/goliath-workflow-review-v119-2.php?mission_id='.(int)$v['mission_id'].'&stage='.(int)$v['stage_no'].'&embed=1';
 }
 $exec=gdb_all("SELECT executive_key,status,COUNT(*) count,MAX(updated_at) latest
   FROM goliath_autonomous_backlog GROUP BY executive_key,status ORDER BY executive_key,status")?:[];
 $crm=[
  'website_leads'=>(int)(gdb_one("SELECT COUNT(*) c FROM leads")['c']??0),
  'new_leads'=>(int)(gdb_one("SELECT COUNT(*) c FROM leads WHERE status='new'")['c']??0),
  'contacts_missing_phone'=>(int)(gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(best_phone,phone,'')=''")['c']??0),
  'contacts_missing_email'=>(int)(gdb_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(best_email,email,'')=''")['c']??0),
  'enrichment_ready'=>(int)(gdb_one("SELECT COUNT(*) c FROM goliath_contact_enrichment_queue WHERE status='ready_for_mark'")['c']??0)
 ];
 echo json_encode(['ok'=>true,'version'=>'V120 Production Studio Feed','crm'=>$crm,'executive_backlog'=>$exec,'actual_work'=>$versions,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);
}
?>