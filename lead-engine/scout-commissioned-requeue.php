<?php
/**
 * V93.2.12 Scout Commissioned Requeue
 * Creates fresh Scout contact-enrichment tasks WITH commissions already attached.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function col9312($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function uid9312($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function js9312($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
 function ins9312($t,$row){$safe=[];foreach($row as $k=>$v){if(col9312($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function upd9312($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col9312($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function qurl9312($q){return 'https://www.google.com/search?q='.rawurlencode($q);}
 $limit=max(1,min(100,(int)($_GET['limit']??20)));
 $rows=gdb_all("SELECT d.*, c.owner_name c_owner,c.property_address c_address,c.town c_town,c.state c_state,c.zip c_zip
  FROM scout_intel_dossiers d
  LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
  WHERE d.handoff_status<>'ready_for_mark'
    AND NOT EXISTS (SELECT 1 FROM local_ai_tasks t WHERE t.task_type='scout_contact_enrichment_v9312' AND t.status IN ('queued','working','assigned','completed') AND t.prompt LIKE CONCAT('%Dossier ID: ',d.id,'%'))
  ORDER BY d.confidence_score DESC,d.id ASC LIMIT {$limit}")?:[];
 $created=[];
 foreach($rows as $r){
  $owner=$r['owner_name']?:($r['c_owner']??''); $address=$r['property_address']?:($r['c_address']??''); $town=$r['town']?:($r['c_town']??''); $state=$r['state']?:($r['c_state']??'CT'); $zip=$r['zip']?:($r['c_zip']??'');
  $urls=[qurl9312('"'.$owner.'" "'.$address.'" phone'),qurl9312('"'.$owner.'" "'.$town.'" CT phone email'),qurl9312('"'.$address.'" "'.$town.'" owner phone'),qurl9312('"'.$owner.'" "'.$town.'" LinkedIn')];
  $prompt="SCOUT CONTACT ENRICHMENT V9312\n\nDossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\nState: {$state}\nZip: {$zip}\n\nReturn ONLY JSON. Open/search result pages where possible. Do not invent phone/email.\n\nSearch URLs:\n- ".implode("\n- ",$urls);
  $commissionId=ins9312('executive_commissions',['commission_uid'=>uid9312('commission'),'executive_key'=>'scout','executive'=>'Scout','title'=>'Scout Deep Contact Enrichment: '.$owner,'description'=>$prompt,'prompt'=>$prompt,'status'=>'accepted','priority'=>550,'progress'=>10,'current_step'=>'Accepted automatically; queued for deep search worker.','metadata'=>js9312(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'version'=>'V93.2.12']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  $taskId=ins9312('local_ai_tasks',['task_uid'=>uid9312('lat'),'commission_id'=>$commissionId,'agent'=>'Scout','task_type'=>'scout_contact_enrichment_v9312','model'=>'goliath-local-worker','prompt'=>$prompt,'status'=>'queued','priority'=>550,'progress'=>0,'metadata'=>js9312(['dossier_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'owner'=>$owner,'address'=>$address,'town'=>$town,'search_urls'=>$urls,'version'=>'V93.2.12']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  upd9312('scout_intel_dossiers',(int)$r['id'],['research_status'=>'queued_for_deep_search','next_action'=>'Commission #'.$commissionId.' accepted and queued for deep search.','updated_at'=>gdb_now()]);
  $created[]=['dossier_id'=>(int)$r['id'],'commission_id'=>$commissionId,'task_id'=>$taskId,'owner'=>$owner,'address'=>$address];
 }
 echo json_encode(['ok'=>true,'version'=>'V93.2.12 Scout Commissioned Requeue','created_count'=>count($created),'created'=>$created,'next'=>'Run V93.2.12 PowerShell worker, then apply enrichment.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V93.2.12 Scout Commissioned Requeue','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>