<?php
/**
 * V95 Universal Executive Dispatcher
 * Mirrors completed work into the new executive inbox, auto-accepts commissions,
 * updates heartbeats, and keeps executives moving.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/../config.php';
  require_once __DIR__.'/../goliath-db.php';
  require_once __DIR__.'/executive-engine.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
  }

  $limit=max(1,min(500,(int)($_GET['limit']??100)));
  $accepted=0; $mirrored=[]; $heartbeats=0;

  // 1) Auto-accept queued commissions.
  if(v95_table('executive_commissions')){
    $commissions=gdb_all("SELECT * FROM executive_commissions WHERE status IN ('queued','pending','new') ORDER BY priority DESC,id ASC LIMIT {$limit}")?:[];
    foreach($commissions as $c){
      v95_update('executive_commissions',(int)$c['id'],['status'=>'accepted','progress'=>max(5,(int)($c['progress']??0)),'current_step'=>'Accepted automatically by V95 Executive Engine','updated_at'=>gdb_now()]);
      $exec=v95_exec_name($c['executive_key']??($c['executive']??'goliath'));
      v95_heartbeat($exec,['status'=>'accepted','current_commission_id'=>(int)$c['id'],'current_step'=>'Accepted commission: '.($c['title']??'Mission'),'progress'=>5,'message'=>$c['title']??'']);
      v95_event($exec,'commission_accepted',$c['title']??'Commission accepted','Accepted automatically by V95.', ['commission_id'=>(int)$c['id']]);
      $accepted++;
    }
  }

  // 2) Mirror completed local AI tasks into executive_deliverables.
  if(v95_table('local_ai_tasks')){
    $tasks=gdb_all("SELECT * FROM local_ai_tasks WHERE status='completed' ORDER BY completed_at DESC,id DESC LIMIT {$limit}")?:[];
    foreach($tasks as $t){
      if(v95_deliverable_exists('local_ai_tasks',(int)$t['id'])) continue;
      $exec=v95_exec_name($t['agent']??'goliath');
      $result=(string)($t['result']??'');
      $title=trim((string)($t['title']??'')) ?: v95_title_from_text($t['prompt']??'', ucfirst($exec).' Task Complete');
      $preview=v95_title_from_text($result ?: ($t['prompt']??''),'Completed task ready for review.');
      $id=v95_create_deliverable([
        'source_table'=>'local_ai_tasks','source_id'=>(int)$t['id'],'commission_id'=>((int)($t['commission_id']??0) ?: null),'task_id'=>((int)$t['id'] ?: null),
        'executive_key'=>$exec,'title'=>$title,'deliverable_type'=>$t['task_type']??'task_result','priority'=>(int)($t['priority']??100),
        'status'=>'new','preview'=>$preview,'deliverable_json'=>v95_json(['result'=>$result,'metadata'=>$t['metadata']??null]),'evidence'=>'Local AI task completed.',
        'action_url'=>'/dashboard/goliath-worker-output.php'
      ]);
      if($id){$mirrored[]=['source'=>'local_ai_tasks','source_id'=>(int)$t['id'],'deliverable_id'=>$id,'executive'=>$exec];}
    }
  }

  // 3) Mirror completed browser jobs into executive_deliverables.
  if(v95_table('goliath_browser_jobs')){
    $jobs=gdb_all("SELECT * FROM goliath_browser_jobs WHERE status='complete' ORDER BY completed_at DESC,id DESC LIMIT {$limit}")?:[];
    foreach($jobs as $j){
      if(v95_deliverable_exists('goliath_browser_jobs',(int)$j['id'])) continue;
      $exec=v95_exec_name($j['executive_key']??'browser');
      $res=json_decode($j['result_json']??'',true); if(!is_array($res)) $res=[];
      $phones=0; foreach(['phone_1','phone_2','phone_3','phone_mobile','best_phone'] as $k){if(!empty($res[$k]))$phones++;}
      $emails=0; foreach(['email_1','email_2','best_email'] as $k){if(!empty($res[$k]))$emails++;}
      $title=($exec==='scout'?'Scout Research Complete: ':'Browser Job Complete: ').($j['target_name']?:('#'.$j['id']));
      $preview=($phones||$emails) ? "Found {$phones} phone candidate(s) and {$emails} email candidate(s)." : "Browser job complete. Needs manual/API review if no contact found.";
      $action='/dashboard/gbi-dashboard.php';
      if($exec==='scout') $action='/dashboard/scout-search-workbench.php';
      $id=v95_create_deliverable([
        'source_table'=>'goliath_browser_jobs','source_id'=>(int)$j['id'],'browser_job_id'=>((int)$j['id'] ?: null),'commission_id'=>((int)($j['commission_id']??0) ?: null),'task_id'=>((int)($j['task_id']??0) ?: null),
        'executive_key'=>$exec,'title'=>$title,'deliverable_type'=>$j['job_type']??'browser_job','priority'=>(int)($j['priority']??100),
        'status'=>'new','preview'=>$preview,'deliverable_json'=>v95_json($res),'evidence'=>$j['evidence']??'','source_url'=>$res['source_url']??'','action_url'=>$action
      ]);
      if($id){$mirrored[]=['source'=>'goliath_browser_jobs','source_id'=>(int)$j['id'],'deliverable_id'=>$id,'executive'=>$exec];}
    }
  }

  // 4) Heartbeat defaults for core executives.
  $core=['goliath','scout','jessica','scorsese','shakespeare','einstein','columbo','prospector','rockefeller','pandora','mozart','holmes'];
  foreach($core as $exec){
    $existing=gdb_one("SELECT id,updated_at FROM goliath_executive_heartbeat WHERE executive_key=? LIMIT 1",[$exec]);
    if(!$existing){
      v95_heartbeat($exec,['status'=>'idle','current_step'=>'Standing by for next mission','progress'=>0,'browser_status'=>'idle','message'=>'V95 Executive Engine online.']);
      $heartbeats++;
    }
  }

  echo json_encode([
    'ok'=>true,
    'version'=>'V95 Universal Executive Dispatcher',
    'accepted_commissions'=>$accepted,
    'mirrored_count'=>count($mirrored),
    'mirrored'=>$mirrored,
    'heartbeats_initialized'=>$heartbeats,
    'next'=>'Open /dashboard/goliath-executive-inbox.php and /dashboard/goliath-live-executives.php',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V95 Universal Executive Dispatcher','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>