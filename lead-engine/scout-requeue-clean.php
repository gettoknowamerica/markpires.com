<?php
/**
 * V93.2.8 Scout Requeue Clean
 * Clears/archives bad blank enrichment attempts and creates fresh V93.2.7-compatible tasks.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function col928($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function uid928($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function js928($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function ins928($t,$row){$safe=[];foreach($row as $k=>$v){if(col928($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
  function upd928($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col928($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
  function qurl928($q){return 'https://www.google.com/search?q='.rawurlencode($q);}

  $limit=max(1,min(50,(int)($_GET['limit']??20)));

  // Mark old blank completed enrichment tasks as reviewed so apply does not keep reporting them.
  $archived=0;
  $old=gdb_all("SELECT id,result FROM local_ai_tasks WHERE LOWER(agent)='scout' AND task_type IN ('scout_contact_enrichment','scout_contact_enrichment_v2') AND status='completed' ORDER BY id ASC LIMIT 500")?:[];
  foreach($old as $t){
    $r=(string)($t['result']??'');
    $hasReal=(preg_match('/"phone_1"\s*:\s*"[^"]{7,}"/',$r) || preg_match('/"email_1"\s*:\s*"[^"]+@[^"]+"/',$r));
    if(!$hasReal){
      upd928('local_ai_tasks',(int)$t['id'],['status'=>'reviewed_blank','updated_at'=>gdb_now()]);
      $archived++;
    }
  }

  $rows=gdb_all("SELECT d.*, c.owner_name c_owner, c.property_address c_address, c.town c_town, c.state c_state, c.zip c_zip
    FROM scout_intel_dossiers d
    LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
    WHERE d.handoff_status<>'ready_for_mark'
      AND NOT EXISTS (
        SELECT 1 FROM local_ai_tasks t
        WHERE LOWER(t.agent)='scout'
          AND t.task_type='scout_contact_enrichment_v927'
          AND t.status IN ('queued','working','assigned','completed')
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
    $urls=[
      qurl928('"'.$owner.'" "'.$address.'" phone'),
      qurl928('"'.$owner.'" "'.$town.'" CT email'),
      qurl928('"'.$address.'" "'.$town.'" owner phone'),
      qurl928('"'.$owner.'" "'.$town.'" LinkedIn'),
      qurl928('"'.$owner.'" "'.$town.'" Facebook')
    ];
    $prompt="SCOUT CONTACT ENRICHMENT V927\n\nDossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\nState: {$state}\nZip: {$zip}\n\nUse local search mode. Return ONLY JSON. Do not return charter text. Do not invent phone/email.\n\nSearch URLs:\n- ".implode("\n- ",$urls)."\n\nRETURN JSON:\n{\n  \"dossier_id\": {$r['id']},\n  \"contact_id\": {$r['contact_id']},\n  \"status\": \"found|needs_external_search\",\n  \"phone_1\": \"\",\n  \"phone_2\": \"\",\n  \"email_1\": \"\",\n  \"email_2\": \"\",\n  \"phone_confidence\": 0,\n  \"email_confidence\": 0,\n  \"source_evidence\": \"\",\n  \"research_notes\": \"\",\n  \"ready_for_mark\": false,\n  \"search_urls\": ".json_encode($urls,JSON_UNESCAPED_SLASHES)."\n}";
    $taskId=ins928('local_ai_tasks',[
      'task_uid'=>uid928('lat'),
      'commission_id'=>null,
      'agent'=>'Scout',
      'task_type'=>'scout_contact_enrichment_v927',
      'model'=>'goliath-local-worker',
      'prompt'=>$prompt,
      'status'=>'queued',
      'priority'=>500,
      'progress'=>0,
      'metadata'=>js928(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'town'=>$town,'search_urls'=>$urls,'version'=>'V93.2.8']),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
    ]);
    upd928('scout_intel_dossiers',(int)$r['id'],['research_status'=>'queued_for_v927_worker','next_action'=>'Fresh V93.2.7 local worker task queued.','updated_at'=>gdb_now()]);
    $created[]=['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'task_id'=>$taskId,'owner'=>$owner,'address'=>$address];
  }

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.8 Scout Requeue Clean',
    'archived_blank_completed_tasks'=>$archived,
    'created_count'=>count($created),
    'created'=>$created,
    'next'=>'Now run the V93.2.7 PowerShell worker, wait for these new task IDs to complete, then run scout-apply-enrichment.php again.',
    'worker'=>'powershell -ExecutionPolicy Bypass -File "F:\\GOliathOmni\\goliath-universal-executive-runtime-v93-2-7.ps1"',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V93.2.8 Scout Requeue Clean','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>