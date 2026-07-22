<?php
/**
 * V93.2.9 Worker Queue Diagnostic + Scout Queue Repair
 * Upload to /lead-engine/scout-worker-queue-diagnostic.php
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function one929($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return ['error'=>$e->getMessage()];}}
  function all929($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [['error'=>$e->getMessage()]];}}
  function col929($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function uid929($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function js929($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function ins929($t,$row){$safe=[];foreach($row as $k=>$v){if(col929($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
  function upd929($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col929($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
  function qurl929($q){return 'https://www.google.com/search?q='.rawurlencode($q);}

  $repair=($_GET['repair']??'0')==='1';
  $limit=max(1,min(50,(int)($_GET['limit']??20)));

  $counts=[
    'queued_all'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE status='queued'")['c']??0),
    'queued_scout'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued'")['c']??0),
    'queued_scout_enrich'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued' AND task_type LIKE 'scout_contact_enrichment%'")['c']??0),
    'completed_scout_enrich'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='completed' AND task_type LIKE 'scout_contact_enrichment%'")['c']??0),
    'reviewed_blank'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='reviewed_blank'")['c']??0),
    'dossiers_need_contact'=>(int)(one929("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status<>'ready_for_mark'")['c']??0)
  ];

  $recent=all929("SELECT id,agent,task_type,status,priority,LEFT(prompt,220) prompt_preview,metadata,created_at,updated_at,completed_at FROM local_ai_tasks ORDER BY id DESC LIMIT 20");

  $created=[];
  if($repair){
    // Reset stuck v927 tasks if they exist but are not queued.
    $stuck=all929("SELECT id FROM local_ai_tasks WHERE LOWER(agent)='scout' AND task_type='scout_contact_enrichment_v927' AND status NOT IN ('queued','working','assigned','completed') ORDER BY id DESC LIMIT 200");
    foreach($stuck as $s){ upd929('local_ai_tasks',(int)$s['id'],['status'=>'queued','updated_at'=>gdb_now()]); }

    // If no queued enrichment tasks remain, create fresh ones.
    $q=(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued' AND task_type='scout_contact_enrichment_v927'")['c']??0);
    if($q===0){
      $rows=all929("SELECT d.*, c.owner_name c_owner, c.property_address c_address, c.town c_town, c.state c_state, c.zip c_zip
        FROM scout_intel_dossiers d
        LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
        WHERE d.handoff_status<>'ready_for_mark'
        ORDER BY d.confidence_score DESC,d.id ASC
        LIMIT {$limit}");
      foreach($rows as $r){
        $owner=$r['owner_name'] ?: ($r['c_owner']??'');
        $address=$r['property_address'] ?: ($r['c_address']??'');
        $town=$r['town'] ?: ($r['c_town']??'');
        $state=$r['state'] ?: ($r['c_state']??'CT');
        $zip=$r['zip'] ?: ($r['c_zip']??'');
        $urls=[
          qurl929('"'.$owner.'" "'.$address.'" phone'),
          qurl929('"'.$owner.'" "'.$town.'" CT email'),
          qurl929('"'.$address.'" "'.$town.'" owner phone'),
          qurl929('"'.$owner.'" "'.$town.'" LinkedIn')
        ];
        $prompt="SCOUT CONTACT ENRICHMENT V927\n\nDossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\nState: {$state}\nZip: {$zip}\n\nReturn ONLY JSON. Do not return charter text. Do not invent phone/email.\n\nSearch URLs:\n- ".implode("\n- ",$urls)."\n\nRETURN JSON:\n{\"dossier_id\":{$r['id']},\"contact_id\":{$r['contact_id']},\"status\":\"found|needs_external_search\",\"phone_1\":\"\",\"phone_2\":\"\",\"email_1\":\"\",\"email_2\":\"\",\"phone_confidence\":0,\"email_confidence\":0,\"source_evidence\":\"\",\"research_notes\":\"\",\"ready_for_mark\":false,\"search_urls\":".json_encode($urls,JSON_UNESCAPED_SLASHES)."}";
        $taskId=ins929('local_ai_tasks',[
          'task_uid'=>uid929('lat'),
          'commission_id'=>null,
          'agent'=>'Scout',
          'task_type'=>'scout_contact_enrichment_v927',
          'model'=>'goliath-local-worker',
          'prompt'=>$prompt,
          'status'=>'queued',
          'priority'=>500,
          'progress'=>0,
          'metadata'=>js929(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'town'=>$town,'search_urls'=>$urls,'version'=>'V93.2.9']),
          'created_at'=>gdb_now(),
          'updated_at'=>gdb_now()
        ]);
        upd929('scout_intel_dossiers',(int)$r['id'],['research_status'=>'queued_for_worker','next_action'=>'Queued for V93.2.7 local worker.','updated_at'=>gdb_now()]);
        $created[]=['dossier_id'=>(int)$r['id'],'task_id'=>$taskId,'owner'=>$owner,'address'=>$address];
      }
    }
  }

  $after=[
    'queued_all'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE status='queued'")['c']??0),
    'queued_scout'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued'")['c']??0),
    'queued_scout_enrich'=>(int)(one929("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued' AND task_type LIKE 'scout_contact_enrichment%'")['c']??0)
  ];

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.9 Worker Queue Diagnostic',
    'repair_mode'=>$repair,
    'counts_before'=>$counts,
    'created_count'=>count($created),
    'created'=>$created,
    'counts_after'=>$after,
    'recent_tasks'=>$recent,
    'next'=>$after['queued_scout_enrich']>0?'Run the V93.2.7 PowerShell worker now. If it still says no queued tasks, local-ai-task-pull.php is filtering them out and needs patching.':'No queued Scout enrichment tasks exist; run with &repair=1.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V93.2.9 Worker Queue Diagnostic','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>