<?php
/**
 * V96.3 Morning Brief Generator
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 require_once __DIR__.'/relationship-memory-v96-3.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function one963($sql){try{return gdb_one($sql)?:[];}catch(Throwable $e){return [];}}
 function all963($sql){try{return gdb_all($sql)?:[];}catch(Throwable $e){return [];}}
 function ins963($t,$row){return rel963_insert($t,$row);}
 $today=date('Y-m-d');
 $metrics=[
  'scout_ready'=>(int)(one963("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),
  'scout_new_24h'=>(int)(one963("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE updated_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")['c']??0),
  'jessica_drafts'=>(int)(one963("SELECT COUNT(*) c FROM jessica_email_drafts WHERE status='pending_approval'")['c']??0),
  'jessica_sent'=>(int)(one963("SELECT COUNT(*) c FROM jessica_email_drafts WHERE status='sent' AND sent_at>=CURDATE()")['c']??0),
  'appointments_today'=>(int)(one963("SELECT COUNT(*) c FROM appointment_requests WHERE DATE(start_time)=CURDATE() AND status IN ('draft','pending','confirmed')")['c']??0),
  'browser_queue'=>(int)(one963("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE status IN ('queued','working')")['c']??0),
  'new_timeline'=>(int)(one963("SELECT COUNT(*) c FROM relationship_timeline WHERE is_new=1")['c']??0)
 ];
 $topDossiers=all963("SELECT id,owner_name,property_address,town,completion_score,phone,best_phone,email,best_email,recommended_blog FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark' ORDER BY COALESCE(completion_score,0) DESC,updated_at DESC LIMIT 8");
 $drafts=all963("SELECT id,to_name,to_email,subject,recommended_blog,created_at FROM jessica_email_drafts WHERE status='pending_approval' ORDER BY created_at DESC LIMIT 8");
 $appts=all963("SELECT id,title,client_name,location,start_time,status FROM appointment_requests WHERE DATE(start_time)=CURDATE() ORDER BY start_time ASC LIMIT 8");
 $actions=[];
 if($metrics['jessica_drafts']>0)$actions[]='Review '.$metrics['jessica_drafts'].' Jessica email drafts waiting for approval.';
 if($metrics['appointments_today']>0)$actions[]='Confirm today’s '.$metrics['appointments_today'].' appointment(s) and reminders.';
 if($metrics['scout_ready']>0)$actions[]='Call top Scout-ready contacts from the mobile Scout OS.';
 if($metrics['browser_queue']>0)$actions[]='Keep OpenClaw bridge running; '.$metrics['browser_queue'].' browser job(s) remain queued/working.';
 $summary="Good morning, Mark.\n\nScout ready contacts: {$metrics['scout_ready']}\nNew/updated Scout files in 24h: {$metrics['scout_new_24h']}\nJessica drafts awaiting review: {$metrics['jessica_drafts']}\nAppointments today: {$metrics['appointments_today']}\nBrowser queue: {$metrics['browser_queue']}\n\nSuggested first action: ".($actions[0]??'Open Mission Control and review latest executive activity.')."\n";
 $existing=one963("SELECT id FROM daily_briefs WHERE brief_date=CURDATE() ORDER BY id DESC LIMIT 1");
 $payload=['metrics'=>$metrics,'top_dossiers'=>$topDossiers,'drafts'=>$drafts,'appointments'=>$appts];
 if($existing){
   $pdo=gdb();$st=$pdo->prepare("UPDATE daily_briefs SET summary=?,metrics_json=?,action_items_json=?,updated_at=NOW() WHERE id=?");$st->execute([$summary,json_encode($payload),json_encode($actions),(int)$existing['id']]);$id=(int)$existing['id'];
 } else {
   $id=ins963('daily_briefs',['brief_uid'=>rel963_uid('brief'),'brief_date'=>$today,'title'=>'Morning Brief — '.$today,'summary'=>$summary,'metrics_json'=>json_encode($payload),'action_items_json'=>json_encode($actions),'status'=>'new','viewed'=>0,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 }
 rel963_timeline('goliath','morning_brief','Goliath prepared morning brief',$summary,$payload,null,null,90);
 echo json_encode(['ok'=>true,'version'=>'V96.3 Morning Brief','brief_id'=>$id,'metrics'=>$metrics,'actions'=>$actions,'next'=>'Open /dashboard/goliath-mobile-command.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V96.3 Morning Brief','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>