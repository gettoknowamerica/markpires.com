<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-internal-crm-v119-3.php';

function d1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(d1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$send=(string)($_GET['send']??'0')==='1';$limit=max(1,min(100,(int)($_GET['limit']??25)));

try{
 // Ensure recent internal leads are enrolled.
 $recent=gdb_all("SELECT l.*,c.contact_uid FROM leads l LEFT JOIN internal_crm_contacts c ON c.id=l.crm_contact_id
  WHERE l.email IS NOT NULL AND l.email<>'' AND COALESCE(l.drip_status,'not_enrolled')<>'enrolled'
  ORDER BY l.created_at DESC LIMIT 100")?:[];
 $seeded=0;
 foreach($recent as $r){
  $lead=g1193_normalize($r);$lead['lead_uid']=$r['uid'];
  $result=g1193_seed_drip($lead,(int)$r['crm_contact_id'],(int)$r['id']);
  $seeded+=(int)($result['created']??0);
 }

 $due=gdb_all("SELECT * FROM goliath_email_drip_queue WHERE status='pending' AND scheduled_at<=NOW() ORDER BY scheduled_at ASC LIMIT $limit")?:[];
 $processed=[];

 foreach($due as $row){
  if(!$send){$processed[]=['id'=>(int)$row['id'],'status'=>'due_not_sent','subject'=>$row['subject'],'email'=>$row['recipient_email']];continue;}
  $sent=false;$error='';
  try{
   if(function_exists('mail')){
    $headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Mark Pires <mark@markpires.com>\r\nReply-To: mark@markpires.com\r\n";
    $sent=@mail((string)$row['recipient_email'],(string)$row['subject'],(string)$row['body_html'],$headers);
   }
  }catch(Throwable $e){$error=$e->getMessage();}
  if($sent){
   g1193_update('goliath_email_drip_queue',(int)$row['id'],['status'=>'sent','sent_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $processed[]=['id'=>(int)$row['id'],'status'=>'sent'];
  }else{
   g1193_update('goliath_email_drip_queue',(int)$row['id'],['status'=>'failed','error_message'=>$error?:'PHP mail returned false. Use the existing Resend/SMTP dispatcher for production sending.','updated_at'=>gdb_now()]);
   $processed[]=['id'=>(int)$row['id'],'status'=>'failed','error'=>$error?:'mail_false'];
  }
 }
 echo json_encode(['ok'=>true,'version'=>'V119.3 Internal Drip','send_enabled'=>$send,'steps_seeded'=>$seeded,'due_count'=>count($due),'processed'=>$processed,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>