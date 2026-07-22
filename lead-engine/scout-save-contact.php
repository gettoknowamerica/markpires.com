<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$key=$_POST['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo 'bad key';exit;}
function col926($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function upd926($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col926($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
function clean926($v){return trim(strip_tags((string)$v));}
$dossierId=(int)($_POST['dossier_id']??0);$contactId=(int)($_POST['contact_id']??0);
$phone1=clean926($_POST['phone_1']??'');$phone2=clean926($_POST['phone_2']??'');$phone3=clean926($_POST['phone_3']??'');$mobile=clean926($_POST['phone_mobile']??'');
$email1=strtolower(clean926($_POST['email_1']??''));$email2=strtolower(clean926($_POST['email_2']??''));
$phoneConf=(int)preg_replace('/[^0-9]/','',$_POST['phone_confidence']??'75');$emailConf=(int)preg_replace('/[^0-9]/','',$_POST['email_confidence']??'75');
$source=clean926($_POST['contact_source']??'manual_verified');$sourceUrl=clean926($_POST['contact_source_url']??'');$evidence=clean926($_POST['evidence']??'');$notes=clean926($_POST['notes']??'');
$ready=($_POST['mark_ready']??'0')==='1' && ($phone1||$phone2||$phone3||$mobile||$email1||$email2);
$bestPhone=$phone1 ?: ($mobile ?: ($phone2 ?: $phone3));$bestEmail=$email1 ?: $email2;
if($contactId){
 upd926('internal_crm_contacts',$contactId,['phone_1'=>$phone1,'phone_2'=>$phone2,'phone_3'=>$phone3,'phone_mobile'=>$mobile,'best_phone'=>$bestPhone,'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,'phone_confidence'=>($bestPhone?$phoneConf:0),'email_confidence'=>($bestEmail?$emailConf:0),'contact_source'=>$source,'contact_source_url'=>$sourceUrl,'contact_verified_at'=>($ready?gdb_now():null),'contact_enrichment_status'=>$ready?'verified':'needs_contact_research','contact_enrichment_notes'=>$notes,'research_status'=>$ready?'ready_for_mark':'needs_contact_research','evidence'=>$evidence,'notes'=>$notes,'last_researched_at'=>gdb_now(),'updated_at'=>gdb_now()]);
}
if($dossierId){
 upd926('scout_intel_dossiers',$dossierId,['phone_1'=>$phone1,'phone_2'=>$phone2,'phone_3'=>$phone3,'phone_mobile'=>$mobile,'best_phone'=>$bestPhone,'phone'=>trim(implode(' | ',array_filter([$phone1,$phone2,$phone3,$mobile]))),'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,'email'=>trim(implode(' | ',array_filter([$email1,$email2]))),'contact_source'=>$source,'contact_source_url'=>$sourceUrl,'contact_verified_at'=>($ready?gdb_now():null),'contact_confidence'=>max($bestPhone?$phoneConf:0,$bestEmail?$emailConf:0),'confidence_score'=>$ready?90:65,'research_status'=>$ready?'ready_for_mark':'needs_contact_research','handoff_status'=>$ready?'ready_for_mark':'not_ready','next_action'=>$ready?'Ready for Mark: verified contact saved.':'Saved notes; still needs contact verification.','evidence_log'=>$evidence,'public_notes'=>$notes,'completed_at'=>$ready?gdb_now():null,'updated_at'=>gdb_now()]);
}
header('Location:/dashboard/scout-search-workbench.php?saved=1');
?>