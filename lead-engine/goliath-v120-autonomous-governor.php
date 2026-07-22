<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function g120_key():string{
 if(defined('AFTER_HOURS_CRON_KEY')) return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY')) return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function g120_uid(string $p):string{return $p.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(16));}
function g120_insert(string $table,array $row):int{
 $cols=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $allowed=[];foreach($cols as $c)$allowed[(string)$c['column_name']]=true;
 $safe=[];foreach($row as $k=>$v)if(isset($allowed[$k]))$safe[$k]=$v;
 return (int)gdb_insert($table,$safe);
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(g120_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $created=[];
 $missingContacts=gdb_all("SELECT id,name,owner_name,property_address,town,best_phone,phone,best_email,email
   FROM internal_crm_contacts
   WHERE COALESCE(best_phone,phone,'')='' OR COALESCE(best_email,email,'')=''
   ORDER BY priority DESC,id ASC LIMIT 250")?:[];
 foreach($missingContacts as $c){
  try{
   $id=g120_insert('goliath_autonomous_backlog',[
    'backlog_uid'=>g120_uid('backlog'),'executive_key'=>'scout','work_type'=>'contact_enrichment',
    'title'=>'Source verified phone/email for '.trim((string)($c['name']?:$c['owner_name'])),
    'directive'=>"Use OpenClaw and legitimate public/licensed sources to find verified phone/email for this exact contact. Never guess. Return evidence and confidence.\nContact ID: {$c['id']}\nName: ".($c['name']?:$c['owner_name'])."\nProperty: {$c['property_address']}\nTown: {$c['town']}",
    'source_table'=>'internal_crm_contacts','source_id'=>(int)$c['id'],'priority'=>1000,
    'status'=>'ready','metadata_json'=>json_encode(['contact_id'=>(int)$c['id']],JSON_UNESCAPED_SLASHES),
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   if($id)$created[]=['executive'=>'scout','backlog_id'=>$id,'source_id'=>(int)$c['id']];
  }catch(Throwable $ignored){}
 }

 $newLeads=gdb_all("SELECT id,uid,name,email,phone,address,town,message,lead_score
   FROM leads WHERE status='new' ORDER BY lead_score DESC,created_at ASC LIMIT 50")?:[];
 foreach($newLeads as $l){
  foreach([
   ['jessica','human_touch','Create the actual personalized acknowledgement, call-preparation notes and follow-up email sequence. Do not claim anything was sent.'],
   ['prospector','next_action','Create the actual call, text and email outreach package Mark can use today.'],
   ['einstein','lead_content','Create the actual personalized content brief and search-intent map for this lead.']
  ] as $spec){
   try{
    $id=g120_insert('goliath_autonomous_backlog',[
     'backlog_uid'=>g120_uid('backlog'),'executive_key'=>$spec[0],'work_type'=>$spec[1],
     'title'=>ucfirst($spec[1]).' for '.($l['name']?:'new website lead'),
     'directive'=>$spec[2]."\n\nLead JSON:\n".json_encode($l,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),
     'source_table'=>'leads','source_id'=>(int)$l['id'],'priority'=>900+(int)$l['lead_score'],
     'status'=>'ready','metadata_json'=>json_encode(['lead_id'=>(int)$l['id'],'lead_uid'=>$l['uid']],JSON_UNESCAPED_SLASHES),
     'created_at'=>gdb_now(),'updated_at'=>gdb_now()
    ]);
    if($id)$created[]=['executive'=>$spec[0],'backlog_id'=>$id,'source_id'=>(int)$l['id']];
   }catch(Throwable $ignored){}
  }
 }

 $idleRoles=[
  'shakespeare'=>['authority_audit','Audit the existing blog library and directly improve the weakest publishable article.'],
  'sherlock'=>['verification_audit','Find one current content asset needing factual verification and correct the actual artifact.'],
  'einstein'=>['seo_compounding','Find one existing page with weak SEO/AEO/schema and directly improve the artifact.'],
  'columbo'=>['archive_gold','Find one useful archive clip or transcript segment and create a complete reusable asset packet.'],
  'scorsese'=>['media_backlog','Take the highest-value queued media item and produce the actual next production artifact.'],
  'mozart'=>['audio_backlog','Take one content asset needing narration/audio and create the actual audio-production artifact.'],
  'pandora'=>['campaign_creation','Create one complete original campaign asset tied to a current business priority.'],
  'rockefeller'=>['conversion_audit','Take one existing asset and directly improve its conversion path and measurable CTA.']
 ];
 foreach($idleRoles as $exec=>$spec){
  $busy=(int)(gdb_one("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(COALESCE(executive_key,agent,''))=? AND status IN ('queued','working','claimed')",[strtolower($exec)])['c']??0);
  $backlog=(int)(gdb_one("SELECT COUNT(*) c FROM goliath_autonomous_backlog WHERE executive_key=? AND status IN ('ready','queued','working')",[$exec])['c']??0);
  if($busy===0&&$backlog===0){
   $id=g120_insert('goliath_autonomous_backlog',[
    'backlog_uid'=>g120_uid('backlog'),'executive_key'=>$exec,'work_type'=>$spec[0],
    'title'=>ucfirst(str_replace('_',' ',$spec[0])),'directive'=>$spec[1],
    'priority'=>500,'status'=>'ready','metadata_json'=>'{"autonomous":true}',
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   if($id)$created[]=['executive'=>$exec,'backlog_id'=>$id,'source_id'=>null];
  }
 }

 $ready=gdb_all("SELECT * FROM goliath_autonomous_backlog WHERE status='ready' ORDER BY priority DESC,created_at ASC LIMIT 50")?:[];
 $dispatched=[];
 foreach($ready as $b){
  $prompt="GOLIATH OMNI V120 AUTONOMOUS PRODUCTION\nExecutive: ".ucfirst((string)$b['executive_key'])."\nWork: {$b['title']}\n\n{$b['directive']}\n\nLAW: Produce the real tangible deliverable or verified research result. Never return an executive brief, overview, recommendation list, generic strategy, HubSpot/Supabase workflow, or description of work.";
  $taskId=g120_insert('local_ai_tasks',[
   'task_uid'=>g120_uid('task'),'task_type'=>'goliath_v120_autonomous','type'=>'goliath_v120_autonomous',
   'agent'=>ucfirst((string)$b['executive_key']),'executive_key'=>$b['executive_key'],
   'title'=>$b['title'],'prompt'=>$prompt,'status'=>'queued','workflow_state'=>'queued',
   'priority'=>(int)$b['priority'],'progress'=>0,'artifact_contract'=>'v120-work-only',
   'metadata_json'=>json_encode(['backlog_id'=>(int)$b['id'],'work_type'=>$b['work_type'],'source_table'=>$b['source_table'],'source_id'=>$b['source_id']],JSON_UNESCAPED_SLASHES),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  gdb_update('goliath_autonomous_backlog',['status'=>'queued','local_task_id'=>$taskId,'attempts'=>(int)$b['attempts']+1,'updated_at'=>gdb_now()],'id=:id',['id'=>(int)$b['id']]);
  $dispatched[]=['backlog_id'=>(int)$b['id'],'task_id'=>$taskId,'executive'=>$b['executive_key'],'title'=>$b['title']];
 }

 echo json_encode([
  'ok'=>true,'version'=>'V120 Autonomous Executive Governor',
  'backlog_created'=>count($created),'tasks_dispatched'=>count($dispatched),
  'created'=>$created,'dispatched'=>$dispatched,'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>