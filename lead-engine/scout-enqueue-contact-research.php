<?php
/**
 * Goliath V93.2.3 Scout Contact Enrichment Queue
 * Converts needs_contact_research dossiers into real Scout local AI tasks.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function tbl($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function js($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function safe_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
  function safe_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}

  $limit=max(1,min(50,(int)($_GET['limit']??20)));
  if(!tbl('scout_intel_dossiers') || !tbl('local_ai_tasks')){
    echo json_encode(['ok'=>false,'error'=>'missing_required_tables','need'=>['scout_intel_dossiers','local_ai_tasks']],JSON_PRETTY_PRINT);
    exit;
  }

  $rows=gdb_all("SELECT d.*, c.owner_name c_owner, c.property_address c_address, c.mailing_address c_mailing, c.town c_town, c.state c_state, c.zip c_zip, c.evidence c_evidence, c.notes c_notes, c.raw_data c_raw
    FROM scout_intel_dossiers d
    LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
    WHERE d.handoff_status<>'ready_for_mark'
      AND d.research_status IN ('needs_contact_research','needs_research','not_ready','queued')
      AND NOT EXISTS (
        SELECT 1 FROM local_ai_tasks t
        WHERE LOWER(t.agent)='scout'
          AND t.status IN ('queued','working','assigned')
          AND t.prompt LIKE CONCAT('%Dossier ID: ',d.id,'%')
      )
    ORDER BY d.confidence_score DESC, d.id ASC
    LIMIT {$limit}")?:[];

  $created=[];
  foreach($rows as $r){
    $owner=$r['owner_name'] ?: ($r['c_owner']??'');
    $address=$r['property_address'] ?: ($r['c_address']??'');
    $town=$r['town'] ?: ($r['c_town']??'');
    $state=$r['state'] ?: ($r['c_state']??'CT');
    $zip=$r['zip'] ?: ($r['c_zip']??'');
    $prompt="SCOUT CONTACT ENRICHMENT MISSION\n\nDossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nMailing Address: ".($r['mailing_address'] ?: ($r['c_mailing']??''))."\nTown: {$town}\nState: {$state}\nZip: {$zip}\n\nMISSION:\nFind legitimate, publicly available or licensed-source contact information for this homeowner/property. Prioritize accurate phone numbers and email addresses. Do NOT invent data. Do NOT collect sensitive personal details. Record sources/evidence and confidence.\n\nREQUIRED OUTPUT JSON:\n{\n  \"dossier_id\": {$r['id']},\n  \"phone_1\": \"\",\n  \"phone_2\": \"\",\n  \"email_1\": \"\",\n  \"email_2\": \"\",\n  \"phone_confidence\": 0,\n  \"email_confidence\": 0,\n  \"source_evidence\": \"\",\n  \"research_notes\": \"\",\n  \"ready_for_mark\": false\n}\n\nIf no contact info can be found after available sources are exhausted, set ready_for_mark=false and explain exactly what was checked.";
    $taskId=safe_insert('local_ai_tasks',[
      'task_uid'=>uid('lat'),
      'commission_id'=>null,
      'agent'=>'Scout',
      'task_type'=>'scout_contact_enrichment',
      'model'=>'goliath-local-worker',
      'prompt'=>$prompt,
      'status'=>'queued',
      'priority'=>400,
      'progress'=>0,
      'metadata'=>js(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'town'=>$town,'version'=>'V93.2.3']),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
    ]);
    safe_update('scout_intel_dossiers',(int)$r['id'],['research_status'=>'researching','next_action'=>'Scout contact enrichment queued.','updated_at'=>gdb_now()]);
    $created[]=['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'task_id'=>$taskId];
  }

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.3 Scout Contact Enrichment Queue',
    'requested_limit'=>$limit,
    'created_count'=>count($created),
    'created'=>$created,
    'next'=>'Run your local worker. It should pull Scout contact_enrichment tasks and return JSON. Next patch will apply those results into CRM/dossiers.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>