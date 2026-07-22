<?php
/**
 * V96.2 Appointment Draft Creator
 * Creates appointment confirmation draft/timeline. Calendar send can be wired to existing Google Calendar webhook next.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function ap962_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ap962_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function ap962_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(ap962_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 $dossierId=(int)($_POST['dossier_id']??0); $contactId=(int)($_POST['contact_id']??0);
 $name=trim($_POST['client_name']??''); $email=trim($_POST['client_email']??''); $phone=trim($_POST['client_phone']??'');
 $location=trim($_POST['location']??''); $start=trim($_POST['start_time']??''); $end=trim($_POST['end_time']??''); $notes=trim($_POST['notes']??'');
 $title=trim($_POST['title']??'Appointment with Mark Pires');
 $id=ap962_insert('appointment_requests',['appointment_uid'=>ap962_uid('appt'),'contact_id'=>$contactId?:null,'dossier_id'=>$dossierId?:null,'title'=>$title,'location'=>$location,'start_time'=>$start?:null,'end_time'=>$end?:null,'client_name'=>$name,'client_email'=>$email,'client_phone'=>$phone,'status'=>'draft','calendar_status'=>'pending','email_status'=>'pending','sms_status'=>'pending','notes'=>$notes,'metadata'=>json_encode($_POST),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 ap962_insert('relationship_timeline',['event_uid'=>ap962_uid('rel'),'contact_id'=>$contactId?:null,'dossier_id'=>$dossierId?:null,'executive_key'=>'jessica','event_type'=>'appointment_drafted','title'=>'Jessica drafted appointment confirmation','details'=>$title.' at '.$location.' '.$start,'metadata'=>json_encode(['appointment_id'=>$id]),'created_at'=>gdb_now()]);
 echo json_encode(['ok'=>true,'version'=>'V96.2 Appointment Draft Creator','appointment_id'=>$id,'next'=>'Calendar/email/SMS confirmation is drafted. Wire send action next.','time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>