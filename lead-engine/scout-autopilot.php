<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??($argv[1]??''); if(strpos($key,'key=')===0)$key=substr($key,4);
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function s953_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function s953_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function s953_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
 function s953_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(s953_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function s953_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(s953_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function s953_qurl($q){return 'https://www.google.com/search?q='.rawurlencode($q);}
 $target=max(1,min(100,(int)($_GET['target_queue']??25)));
 $batch=max(1,min(100,(int)($_GET['batch']??25)));
 $queued=(int)(gdb_one("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status IN ('queued','working')")['c']??0);
 $needs=(int)(gdb_one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status<>'ready_for_mark'")['c']??0);
 $createdDossiers=0;
 if($needs<$target){
  $rows=gdb_all("SELECT c.* FROM internal_crm_contacts c WHERE NOT EXISTS (SELECT 1 FROM scout_intel_dossiers d WHERE d.contact_id=c.id) AND COALESCE(c.research_status,'') NOT IN ('do_not_contact','bad_record') ORDER BY COALESCE(c.priority_score,0) DESC,c.id ASC LIMIT ".min($batch,$target-$needs))?:[];
  foreach($rows as $c){
   $owner=$c['owner_name']??''; $address=$c['property_address']??''; $town=$c['town']??''; $state=$c['state']??'CT';
   if(!$owner && !empty($c['raw_data'])){$raw=json_decode($c['raw_data'],true); if(is_array($raw))$owner=$raw['full_name']??$raw['FULL_NAME']??'';}
   $id=s953_insert('scout_intel_dossiers',['dossier_uid'=>s953_uid('dossier'),'contact_id'=>(int)$c['id'],'owner_name'=>$owner,'property_address'=>$address,'mailing_address'=>$c['mailing_address']??'','town'=>$town,'state'=>$state,'zip'=>$c['zip']??'','source_label'=>'autopilot_crm','research_status'=>'queued_for_browser_intelligence','handoff_status'=>'not_ready','confidence_score'=>40,'contact_confidence'=>0,'next_action'=>'Scout Autopilot created dossier; queued for OpenClaw browser research.','raw_json'=>s953_json(['crm_contact'=>$c,'autopilot'=>'V95.3']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   if($id)$createdDossiers++;
  }
 }
 $queued=(int)(gdb_one("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status IN ('queued','working')")['c']??0);
 $toCreate=max(0,$target-$queued); $created=[];
 if($toCreate>0){
  $rows=gdb_all("SELECT d.*,c.owner_name c_owner,c.property_address c_address,c.town c_town,c.state c_state,c.zip c_zip FROM scout_intel_dossiers d LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id WHERE d.handoff_status<>'ready_for_mark' AND COALESCE(d.research_status,'') NOT IN ('do_not_contact','ready_for_mark') AND NOT EXISTS (SELECT 1 FROM goliath_browser_jobs b WHERE b.executive_key='scout' AND b.job_type='contact_enrichment' AND b.status IN ('queued','working','complete') AND b.prompt LIKE CONCAT('%Dossier ID: ',d.id,'%')) ORDER BY d.confidence_score DESC,d.id ASC LIMIT ".min($batch,$toCreate))?:[];
  foreach($rows as $r){
   $owner=$r['owner_name']?:($r['c_owner']??''); $address=$r['property_address']?:($r['c_address']??''); $town=$r['town']?:($r['c_town']??''); $state=$r['state']?:($r['c_state']??'CT'); $zip=$r['zip']?:($r['c_zip']??'');
   $urls=[s953_qurl('"'.$owner.'" "'.$address.'" phone'),s953_qurl('"'.$owner.'" "'.$town.'" CT phone email'),s953_qurl('"'.$address.'" "'.$town.'" owner phone'),s953_qurl('"'.$owner.'" "'.$town.'" LinkedIn')];
   $prompt="Dossier ID: {$r['id']}\nContact ID: {$r['contact_id']}\nOwner: {$owner}\nProperty Address: {$address}\nTown: {$town}\nState: {$state}\nZip: {$zip}\n\nScout Autopilot Mission: Open real browser through OpenClaw, search, open result pages, extract only visible candidate phone/email with evidence URL. Do not invent.";
   $jobId=s953_insert('goliath_browser_jobs',['job_uid'=>s953_uid('gbj'),'executive_key'=>'scout','job_type'=>'contact_enrichment','target_name'=>$owner,'target_address'=>$address,'target_town'=>$town,'prompt'=>$prompt,'search_urls'=>s953_json($urls),'status'=>'queued','progress'=>0,'current_step'=>'Queued by Scout Autopilot','priority'=>650,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   if($jobId){s953_update('scout_intel_dossiers',(int)$r['id'],['research_status'=>'queued_for_browser_intelligence','next_action'=>'Queued by Scout Autopilot as GBI job #'.$jobId,'updated_at'=>gdb_now()]);$created[]=['browser_job_id'=>$jobId,'dossier_id'=>(int)$r['id'],'owner'=>$owner,'address'=>$address];}
  }
 }
 $after=['queued_or_working'=>(int)(gdb_one("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status IN ('queued','working')")['c']??0),'ready_dossiers'=>(int)(gdb_one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),'needs_dossiers'=>(int)(gdb_one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status<>'ready_for_mark'")['c']??0)];
 echo json_encode(['ok'=>true,'version'=>'V95.3 Scout Autopilot','target_queue'=>$target,'created_dossiers'=>$createdDossiers,'created_jobs_count'=>count($created),'created_jobs'=>$created,'counts_after'=>$after,'next'=>'Keep V94.1 OpenClaw bridge running. Autopilot keeps feeding jobs.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V95.3 Scout Autopilot','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>