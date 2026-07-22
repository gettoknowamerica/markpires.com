<?php
/**
 * V93.2.4 Scout Enrichment Queue — stricter prompt + search links.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
  function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function js($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
  function upd($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
  function qurl($q){return 'https://www.google.com/search?q='.rawurlencode($q);}
  $limit=max(1,min(50,(int)($_GET['limit']??20)));
  $rows=gdb_all("SELECT d.*, c.owner_name c_owner, c.property_address c_address, c.mailing_address c_mailing, c.town c_town, c.state c_state, c.zip c_zip, c.evidence c_evidence, c.notes c_notes, c.raw_data c_raw
    FROM scout_intel_dossiers d
    LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
    WHERE d.handoff_status<>'ready_for_mark'
      AND d.research_status IN ('needs_contact_research','needs_research','not_ready','queued','researching')
      AND NOT EXISTS (
        SELECT 1 FROM local_ai_tasks t
        WHERE LOWER(t.agent)='scout'
          AND t.task_type='scout_contact_enrichment_v2'
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
    $queries=[
      qurl('"'.$owner.'" "'.$address.'" phone'),
      qurl('"'.$owner.'" "'.$town.'" CT email'),
      qurl('"'.$address.'" "'.$town.'" owner phone'),
      qurl('"'.$owner.'" "'.$town.'" "LinkedIn"'),
      qurl('"'.$owner.'" "'.$town.'" "Facebook"'),
    ];
    $prompt="SCOUT CONTACT ENRICHMENT V2\n\nDossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\nState: {$state}\nZip: ".($r['zip'] ?: ($r['c_zip']??''))."\n\nIMPORTANT:\nIf your local runtime does not have web/search/API access, DO NOT pretend contact research was completed. Return NEEDS_EXTERNAL_SEARCH with the search URLs below.\n\nSearch URLs:\n- ".implode("\n- ",$queries)."\n\nMISSION:\nFind legitimate public/licensed contact info only. No sensitive personal detail. No guessing.\n\nRETURN ONLY JSON:\n{\n  \"dossier_id\": {$r['id']},\n  \"status\": \"found|not_found|needs_external_search\",\n  \"phone_1\": \"\",\n  \"phone_2\": \"\",\n  \"email_1\": \"\",\n  \"email_2\": \"\",\n  \"phone_confidence\": 0,\n  \"email_confidence\": 0,\n  \"source_evidence\": \"\",\n  \"research_notes\": \"\",\n  \"search_urls\": ".json_encode($queries,JSON_UNESCAPED_SLASHES)."\n}";
    $taskId=ins('local_ai_tasks',[
      'task_uid'=>uid('lat'),'commission_id'=>null,'agent'=>'Scout','task_type'=>'scout_contact_enrichment_v2','model'=>'goliath-local-worker',
      'prompt'=>$prompt,'status'=>'queued','priority'=>450,'progress'=>0,
      'metadata'=>js(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'town'=>$town,'search_urls'=>$queries,'version'=>'V93.2.4']),
      'created_at'=>gdb_now(),'updated_at'=>gdb_now()
    ]);
    upd('scout_intel_dossiers',(int)$r['id'],['research_status'=>'researching','next_action'=>'Scout enrichment V2 queued with search URLs.','updated_at'=>gdb_now()]);
    $created[]=['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'task_id'=>$taskId,'search_urls'=>$queries];
  }
  echo json_encode(['ok'=>true,'version'=>'V93.2.4 Scout Enrichment Queue','created_count'=>count($created),'created'=>$created,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>