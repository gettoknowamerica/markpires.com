<?php
/**
 * V118 Jessica Outbound Email: approve, save, send
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function a962_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function a962_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function a962_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function a962_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
 function a962_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(a962_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function a962_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(a962_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function a962_send($to,$subject,$html,$text=''){
   if(!$to)return ['ok'=>false,'error'=>'missing_to'];
   if(!defined('RESEND_API_KEY')||!RESEND_API_KEY)return ['ok'=>false,'error'=>'RESEND_API_KEY missing'];
   $from=(defined('RESEND_FROM_EMAIL')&&RESEND_FROM_EMAIL)?RESEND_FROM_EMAIL:(defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com');
   $payload=['from'=>(defined('MARK_NAME')?MARK_NAME:'Mark Pires').' <'.$from.'>','to'=>[$to],'reply_to'=>[defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com'],'subject'=>$subject,'html'=>$html,'text'=>$text?:strip_tags($html)];
   $ch=curl_init('https://api.resend.com/emails');
   curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
   $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
   return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
 }
 $action=$_POST['action']??'save';
 $draftId=(int)($_POST['draft_id']??0);
 if(!$draftId){echo json_encode(['ok'=>false,'error'=>'missing_draft_id']);exit;}
 $draft=gdb_one("SELECT * FROM jessica_email_drafts WHERE id=?",[$draftId]);
 if(!$draft){echo json_encode(['ok'=>false,'error'=>'draft_not_found']);exit;}
 $subject=trim((string)($_POST['subject']??$draft['subject']));
 $bodyHtml=(string)($_POST['body_html']??$draft['body_html']);
 $bodyText=trim((string)($_POST['body_text']??$draft['body_text']));
 $res=['ok'=>true,'action'=>$action];

 if($action==='save'){
   a962_update('jessica_email_drafts',$draftId,['subject'=>$subject,'body_html'=>$bodyHtml,'body_text'=>$bodyText,'updated_at'=>gdb_now()]);
 } elseif($action==='approve'){
   a962_update('jessica_email_drafts',$draftId,['subject'=>$subject,'body_html'=>$bodyHtml,'body_text'=>$bodyText,'status'=>'approved','approved_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   if(!empty($draft['queue_id'])) a962_update('jessica_relationship_queue',(int)$draft['queue_id'],['status'=>'approved','updated_at'=>gdb_now()]);
 } elseif($action==='send'){
   $send=a962_send($draft['to_email'],$subject,$bodyHtml,$bodyText);
   $res['send']=$send;
   if($send['ok']){
     a962_update('jessica_email_drafts',$draftId,['subject'=>$subject,'body_html'=>$bodyHtml,'body_text'=>$bodyText,'status'=>'sent','sent_at'=>gdb_now(),'send_result'=>a962_json($send),'updated_at'=>gdb_now()]);
     if(!empty($draft['queue_id'])) a962_update('jessica_relationship_queue',(int)$draft['queue_id'],['status'=>'sent','last_action_at'=>gdb_now(),'relationship_stage'=>'contacted','updated_at'=>gdb_now()]);
     if(!empty($draft['dossier_id'])) a962_update('scout_intel_dossiers',(int)$draft['dossier_id'],['jessica_status'=>'sent','relationship_stage'=>'contacted','updated_at'=>gdb_now()]);
     if(!empty($draft['contact_id'])) a962_update('internal_crm_contacts',(int)$draft['contact_id'],['relationship_stage'=>'contacted','last_jessica_touch_at'=>gdb_now(),'updated_at'=>gdb_now()]);
     if(a962_table('relationship_timeline')) a962_insert('relationship_timeline',['event_uid'=>a962_uid('rel'),'contact_id'=>$draft['contact_id'],'dossier_id'=>$draft['dossier_id'],'executive_key'=>'jessica','event_type'=>'email_sent','title'=>'Jessica sent first-touch email','details'=>'Sent: '.$subject,'metadata'=>a962_json($send),'created_at'=>gdb_now()]);
   }
 } else {echo json_encode(['ok'=>false,'error'=>'unknown_action']);exit;}
 echo json_encode(['ok'=>true,'version'=>'V118 Jessica Outbound Email','result'=>$res,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V118 Jessica Outbound Email','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>