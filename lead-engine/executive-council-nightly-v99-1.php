<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid991($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function one991($s){try{return gdb_one($s)?:[];}catch(Throwable $e){return [];}}
 function all991($s){try{return gdb_all($s)?:[];}catch(Throwable $e){return [];}}
 function ins991($t,$r){try{return gdb_insert($t,$r);}catch(Throwable $e){return null;}}
 $metrics=[
  'scout_ready'=>(int)(one991("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),
  'jessica_drafts'=>(int)(one991("SELECT COUNT(*) c FROM jessica_email_drafts WHERE status='pending_approval'")['c']??0),
  'shakespeare_review'=>(int)(one991("SELECT COUNT(*) c FROM shakespeare_content_packages WHERE approval_status='needs_review'")['c']??0),
  'social_queue'=>(int)(one991("SELECT COUNT(*) c FROM goliath_social_queue WHERE status='queued'")['c']??0),
  'browser_queue'=>(int)(one991("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE status IN ('queued','working')")['c']??0)
 ];
 $actions=[];
 if($metrics['jessica_drafts']>0)$actions[]=['label'=>'Review Jessica drafts','type'=>'jessica','target'=>'/dashboard/jessica-relationship-center.php','priority'=>950,'detail'=>$metrics['jessica_drafts'].' email drafts need approval.'];
 if($metrics['scout_ready']>0)$actions[]=['label'=>'Call Scout-ready contacts','type'=>'scout','target'=>'/dashboard/scout-ready-contacts.php','priority'=>925,'detail'=>$metrics['scout_ready'].' call-ready dossiers are available.'];
 if($metrics['shakespeare_review']>0)$actions[]=['label'=>'Review Shakespeare content','type'=>'shakespeare','target'=>'/dashboard/shakespeare-authority-center.php','priority'=>875,'detail'=>$metrics['shakespeare_review'].' content packages need review.'];
 if($metrics['social_queue']>0)$actions[]=['label'=>'Approve social queue','type'=>'social','target'=>'/dashboard/social-command-center.php','priority'=>800,'detail'=>$metrics['social_queue'].' posts are queued for distribution.'];
 $summary="Good morning, Mark.\n\nThe Executive Council completed its overnight review.\n\nScout-ready contacts: {$metrics['scout_ready']}\nJessica drafts: {$metrics['jessica_drafts']}\nShakespeare review items: {$metrics['shakespeare_review']}\nSocial queue: {$metrics['social_queue']}\nBrowser queue: {$metrics['browser_queue']}\n\nTop priority: ".($actions[0]['label']??'Open Goliath OS and review the latest work.')."\n";
 $payload=['metrics'=>$metrics,'actions'=>$actions,'executives'=>all991("SELECT executive_key,display_name,title,maturity_score,status,workspace_url FROM executive_registry ORDER BY maturity_score DESC")];
 $existing=one991("SELECT id FROM executive_council_sessions WHERE session_date=CURDATE() ORDER BY id DESC LIMIT 1");
 if($existing){$pdo=gdb();$st=$pdo->prepare("UPDATE executive_council_sessions SET meeting_summary=?,top_actions_json=?,replay_json=?,status='complete',completed_at=NOW(),updated_at=NOW() WHERE id=?");$st->execute([$summary,json_encode($actions),json_encode($payload),(int)$existing['id']]);$sid=(int)$existing['id'];}
 else {$sid=ins991('executive_council_sessions',['session_uid'=>uid991('council'),'session_date'=>date('Y-m-d'),'title'=>'Executive Council — '.date('Y-m-d'),'status'=>'complete','meeting_summary'=>$summary,'top_actions_json'=>json_encode($actions),'replay_json'=>json_encode($payload),'started_at'=>date('Y-m-d 19:00:00'),'completed_at'=>gdb_now(),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);}
 echo json_encode(['ok'=>true,'version'=>'V99.1 Executive Council Nightly','session_id'=>$sid,'metrics'=>$metrics,'top_actions'=>$actions,'next'=>'Open /dashboard/goliath-os.php','time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V99.1 Executive Council Nightly','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>