<?php
declare(strict_types=1);ini_set('display_errors','0');set_time_limit(50);header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function send_resend_v111($to,$subject,$text,$unsubscribe){
 if(!defined('RESEND_API_KEY')||!RESEND_API_KEY)return ['ok'=>false,'error'=>'resend_not_configured'];
 $html=nl2br(htmlspecialchars($text,ENT_QUOTES,'UTF-8')).'<hr><p style="font-size:11px;color:#777">You are receiving this because of a real-estate-related inquiry or property record. <a href="'.htmlspecialchars($unsubscribe,ENT_QUOTES,'UTF-8').'">Unsubscribe</a>.</p>';
 $payload=['from'=>'Mark Pires <'.(defined('RESEND_FROM_EMAIL')?RESEND_FROM_EMAIL:'mark@markpires.com').'>','to'=>[$to],'subject'=>$subject,'html'=>$html,'headers'=>['List-Unsubscribe'=>'<'.$unsubscribe.'>']];
 $ch=curl_init('https://api.resend.com/emails');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}
$campaigns=gdb_all("SELECT * FROM jessica_campaigns_v111 WHERE approval_status='approved' AND status='active' ORDER BY id ASC LIMIT 2")?:[];$sent=0;$queued=0;$failed=0;
foreach($campaigns as $c){
 $like=$c['lead_type']==='expired_listing'?'%expired%':'%absentee%';
 $contacts=gdb_all("SELECT id,owner_name,property_address,COALESCE(best_email,email_1,email_2) email,compliance_status,source_file,contact_source,raw_data FROM internal_crm_contacts WHERE COALESCE(best_email,email_1,email_2,'')<>'' AND COALESCE(compliance_status,'') NOT IN ('do_not_contact','suppressed','unsubscribed') AND (LOWER(COALESCE(source_file,'')) LIKE ? OR LOWER(COALESCE(contact_source,'')) LIKE ? OR LOWER(COALESCE(raw_data,'')) LIKE ?) ORDER BY priority_score DESC,id ASC LIMIT 1000",[$like,$like,$like])?:[];
 foreach($contacts as $ct){
   $sup=(int)(gdb_one("SELECT COUNT(*) c FROM jessica_suppression_v111 WHERE email=?",[$ct['email']])['c']??0);if($sup)continue;
   $x=gdb_one("SELECT id FROM jessica_campaign_recipients_v111 WHERE campaign_id=? AND contact_id=?",[$c['id'],$ct['id']]);
   if(!$x){gdb_insert('jessica_campaign_recipients_v111',['campaign_id'=>$c['id'],'contact_id'=>$ct['id'],'email'=>$ct['email'],'first_name'=>trim(explode(' ',(string)$ct['owner_name'])[0]??''),'property_address'=>$ct['property_address'],'status'=>'queued','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);$queued++;}
 }
 $batch=max(1,min(50,(int)$c['batch_size']));$recipients=gdb_all("SELECT * FROM jessica_campaign_recipients_v111 WHERE campaign_id=? AND status='queued' ORDER BY id ASC LIMIT $batch",[$c['id']])?:[];
 foreach($recipients as $r){
   $first=$r['first_name']?:'there';$body=str_replace(['[First Name]','[name]','[Name]','[Property Address]'],[$first,$first,$first,$r['property_address']?:'your property'],$c['body_text']);
   $subject=str_replace(['[First Name]','[Property Address]'],[$first,$r['property_address']?:'your property'],$c['subject_line']);
   $token=hash_hmac('sha256',strtolower($r['email']),$key);$unsub='https://www.markpires.com/lead-engine/jessica-unsubscribe-v111.php?email='.rawurlencode($r['email']).'&token='.$token;
   $res=send_resend_v111($r['email'],$subject,$body,$unsub);
   if($res['ok']){gdb_update('jessica_campaign_recipients_v111',['status'=>'sent','attempt_count'=>(int)$r['attempt_count']+1,'sent_at'=>gdb_now(),'updated_at'=>gdb_now()],'id=:id',['id'=>$r['id']]);$sent++;}
   else{gdb_update('jessica_campaign_recipients_v111',['status'=>'failed','attempt_count'=>(int)$r['attempt_count']+1,'last_error'=>json_encode($res),'updated_at'=>gdb_now()],'id=:id',['id'=>$r['id']]);$failed++;}
 }
}
echo json_encode(['ok'=>true,'version'=>'V111.2 Jessica Campaign Worker','campaigns'=>count($campaigns),'recipients_added'=>$queued,'sent'=>$sent,'failed'=>$failed,'identity'=>'Mark Pires','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>