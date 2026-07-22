<?php
/**
 * V95.4 Scout Contact Update
 * Saves Mark's notes + extra phones/emails back to CRM and dossier.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function c954($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function u954($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(c954($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function clean($v){return trim(strip_tags((string)$v));}
 $dossier=(int)($_POST['dossier_id']??0); $contact=(int)($_POST['contact_id']??0);
 $extraPhone=clean($_POST['extra_phone']??''); $extraEmail=strtolower(clean($_POST['extra_email']??'')); $notes=clean($_POST['mark_notes']??''); $status=clean($_POST['lead_status']??'');
 if(!$dossier && !$contact){echo json_encode(['ok'=>false,'error'=>'missing_ids']);exit;}
 $d=$dossier?gdb_one("SELECT * FROM scout_intel_dossiers WHERE id=?",[$dossier]):[];
 $c=$contact?gdb_one("SELECT * FROM internal_crm_contacts WHERE id=?",[$contact]):[];
 $phone1=$d['phone_1']??($c['phone_1']??''); $phone2=$d['phone_2']??($c['phone_2']??''); $phone3=$d['phone_3']??($c['phone_3']??'');
 if($extraPhone){ if(!$phone1)$phone1=$extraPhone; elseif(!$phone2)$phone2=$extraPhone; elseif(!$phone3)$phone3=$extraPhone; }
 $email1=$d['email_1']??($c['email_1']??''); $email2=$d['email_2']??($c['email_2']??'');
 if($extraEmail){ if(!$email1)$email1=$extraEmail; elseif(!$email2)$email2=$extraEmail; }
 $bestPhone=$phone1?:$phone2?:$phone3; $bestEmail=$email1?:$email2;
 $append="\n\n[V95.4 Mark Note ".date('Y-m-d H:i')."]\n".$notes;
 if($contact) u954('internal_crm_contacts',$contact,['phone_1'=>$phone1,'phone_2'=>$phone2,'phone_3'=>$phone3,'best_phone'=>$bestPhone,'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,'contact_status'=>$status?:($c['contact_status']??null),'research_status'=>($status==='do_not_contact'?'do_not_contact':($bestPhone||$bestEmail?'ready_for_mark':'needs_external_search')),'notes'=>trim(($c['notes']??'').$append),'updated_at'=>gdb_now()]);
 if($dossier) u954('scout_intel_dossiers',$dossier,['phone_1'=>$phone1,'phone_2'=>$phone2,'phone_3'=>$phone3,'best_phone'=>$bestPhone,'phone'=>trim(implode(' | ',array_filter([$phone1,$phone2,$phone3]))),'email_1'=>$email1,'email_2'=>$email2,'best_email'=>$bestEmail,'email'=>trim(implode(' | ',array_filter([$email1,$email2]))),'handoff_status'=>($status==='do_not_contact'?'do_not_contact':($bestPhone||$bestEmail?'ready_for_mark':'not_ready')),'research_status'=>($status==='do_not_contact'?'do_not_contact':($bestPhone||$bestEmail?'ready_for_mark':'needs_external_search')),'public_notes'=>trim(($d['public_notes']??'').$append),'next_action'=>$status?:'Updated by Mark.','updated_at'=>gdb_now()]);
 echo json_encode(['ok'=>true,'version'=>'V95.4 Scout Contact Update','dossier_id'=>$dossier,'contact_id'=>$contact,'phone'=>$bestPhone,'email'=>$bestEmail,'status'=>$status,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V95.4 Scout Contact Update','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>