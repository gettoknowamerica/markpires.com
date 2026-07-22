<?php
/**
 * Goliath V76.0 — Executive Operating System Core
 * Deliverable Registry + Evidence Engine + Executive Memory + Pipeline Events + Handoffs
 */

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function gv76_table($t){
  try{
    $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);
    return ((int)($r['c']??0))>0;
  }catch(Throwable $e){return false;}
}
function gv76_col($t,$c){
  try{
    $r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);
    return ((int)($r['c']??0))>0;
  }catch(Throwable $e){return false;}
}
function gv76_json($v){ return json_encode(is_array($v)?$v:[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function gv76_now(){ return date('Y-m-d H:i:s'); }
function gv76_uid($prefix='del'){ return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)); }

function gv76_install(){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'db_not_enabled'];

  // Create base tables if missing.
  gdb_exec("CREATE TABLE IF NOT EXISTS goliath_deliverables (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    deliverable_uid VARCHAR(90) NOT NULL UNIQUE,
    executive_key VARCHAR(80) NOT NULL,
    deliverable_type VARCHAR(80) NOT NULL DEFAULT 'executive_plan',
    title VARCHAR(255) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    evidence_status VARCHAR(40) NOT NULL DEFAULT 'needs_review',
    evidence LONGTEXT NULL,
    output_summary LONGTEXT NULL,
    output_url TEXT NULL,
    output_path TEXT NULL,
    source_urls LONGTEXT NULL,
    source_record_ids LONGTEXT NULL,
    related_commission_id BIGINT NULL,
    related_task_id BIGINT NULL,
    related_completion_id BIGINT NULL,
    next_executive VARCHAR(80) NULL,
    next_action LONGTEXT NULL,
    review_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    roi_score INT NOT NULL DEFAULT 0,
    priority INT NOT NULL DEFAULT 80,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_exec (executive_key),
    KEY idx_status (status),
    KEY idx_type (deliverable_type),
    KEY idx_review (review_status),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // If an older/partial goliath_deliverables table already existed, add the V76 columns safely.
  $deliverableCols = [
    'deliverable_uid'=>"VARCHAR(90) NULL",
    'executive_key'=>"VARCHAR(80) NOT NULL DEFAULT 'goliath'",
    'deliverable_type'=>"VARCHAR(80) NOT NULL DEFAULT 'executive_plan'",
    'title'=>"VARCHAR(255) NOT NULL DEFAULT 'Goliath Deliverable'",
    'status'=>"VARCHAR(40) NOT NULL DEFAULT 'draft'",
    'evidence_status'=>"VARCHAR(40) NOT NULL DEFAULT 'needs_review'",
    'evidence'=>"LONGTEXT NULL",
    'output_summary'=>"LONGTEXT NULL",
    'output_url'=>"TEXT NULL",
    'output_path'=>"TEXT NULL",
    'source_urls'=>"LONGTEXT NULL",
    'source_record_ids'=>"LONGTEXT NULL",
    'related_commission_id'=>"BIGINT NULL",
    'related_task_id'=>"BIGINT NULL",
    'related_completion_id'=>"BIGINT NULL",
    'next_executive'=>"VARCHAR(80) NULL",
    'next_action'=>"LONGTEXT NULL",
    'review_status'=>"VARCHAR(40) NOT NULL DEFAULT 'pending'",
    'roi_score'=>"INT NOT NULL DEFAULT 0",
    'priority'=>"INT NOT NULL DEFAULT 80",
    'metadata'=>"LONGTEXT NULL",
    'created_at'=>"DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'updated_at'=>"DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
  ];
  foreach($deliverableCols as $col=>$def){
    if(!gv76_col('goliath_deliverables',$col)){
      try{ gdb_exec("ALTER TABLE goliath_deliverables ADD COLUMN {$col} {$def}"); }catch(Throwable $e){}
    }
  }
  // Backfill deliverable_uid if needed before unique index.
  try{ gdb_exec("UPDATE goliath_deliverables SET deliverable_uid=CONCAT('del_',id,'_',UNIX_TIMESTAMP()) WHERE deliverable_uid IS NULL OR deliverable_uid=''"); }catch(Throwable $e){}
  try{ gdb_exec("ALTER TABLE goliath_deliverables ADD UNIQUE KEY uniq_deliverable_uid (deliverable_uid)"); }catch(Throwable $e){}
  foreach([
    'idx_gd_exec'=>'executive_key',
    'idx_gd_status'=>'status',
    'idx_gd_evidence'=>'evidence_status',
    'idx_gd_type'=>'deliverable_type',
    'idx_gd_review'=>'review_status',
    'idx_gd_created'=>'created_at'
  ] as $idx=>$col){
    try{ gdb_exec("ALTER TABLE goliath_deliverables ADD KEY {$idx} ({$col})"); }catch(Throwable $e){}
  }

  gdb_exec("CREATE TABLE IF NOT EXISTS executive_memory (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    executive_key VARCHAR(80) NOT NULL,
    memory_type VARCHAR(80) NOT NULL DEFAULT 'work_memory',
    memory_key VARCHAR(160) NOT NULL,
    memory_value LONGTEXT NULL,
    source_type VARCHAR(80) NULL,
    source_id VARCHAR(120) NULL,
    confidence INT NOT NULL DEFAULT 80,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_memory (executive_key, memory_type, memory_key),
    KEY idx_exec (executive_key),
    KEY idx_active (active)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS executive_pipeline_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_uid VARCHAR(90) NOT NULL UNIQUE,
    executive_key VARCHAR(80) NOT NULL,
    pipeline_name VARCHAR(120) NOT NULL DEFAULT 'default',
    stage VARCHAR(80) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    progress INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    detail LONGTEXT NULL,
    related_commission_id BIGINT NULL,
    related_task_id BIGINT NULL,
    related_deliverable_id BIGINT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_exec (executive_key),
    KEY idx_status (status),
    KEY idx_stage (stage),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS executive_handoffs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    handoff_uid VARCHAR(90) NOT NULL UNIQUE,
    from_executive VARCHAR(80) NOT NULL,
    to_executive VARCHAR(80) NOT NULL,
    title VARCHAR(255) NOT NULL,
    reason LONGTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'queued',
    related_deliverable_id BIGINT NULL,
    related_commission_id BIGINT NULL,
    related_task_id BIGINT NULL,
    expected_output LONGTEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_to (to_executive),
    KEY idx_status (status),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  return ['ok'=>true,'installed'=>true,'tables'=>[
    'goliath_deliverables'=>gv76_table('goliath_deliverables'),
    'executive_memory'=>gv76_table('executive_memory'),
    'executive_pipeline_events'=>gv76_table('executive_pipeline_events'),
    'executive_handoffs'=>gv76_table('executive_handoffs')
  ]];
}
function gv76_parse_contract($text){
  $text=(string)$text;
  $keys=['DELIVERABLE_TYPE','EXECUTIVE','ACTIONABLE_OUTPUT','EVIDENCE','CLICKABLE_OUTPUTS','HANDOFFS','NEXT_ACTION'];
  $out=['deliverable_type'=>'executive_plan','executive'=>'','actionable_output'=>'','evidence'=>'','clickable_outputs'=>'','handoffs'=>'','next_action'=>''];
  foreach($keys as $i=>$k){
    $next=array_slice($keys,$i+1);
    $pattern='/'.$k.':\s*(.*?)(?=\n(?:'.implode('|',$next).'):\s*|\z)/is';
    if(preg_match($pattern,$text,$m)) $out[strtolower($k)]=trim($m[1]);
  }
  return $out;
}
function gv76_extract_urls($text){
  $urls=[];
  if(preg_match_all('/https?:\/\/[^\s<>"\']+/i',(string)$text,$m)){foreach($m[0] as $u)$urls[$u]=$u;}
  if(preg_match_all('/\/(?:dashboard|lead-engine|media-assets|uploads|reports|exports)\/[^\s<>"\']+/i',(string)$text,$m)){foreach($m[0] as $u)$urls[$u]=$u;}
  return array_values($urls);
}
function gv76_extract_record_ids($text){
  $ids=[];
  if(preg_match_all('/(?:record|crm|lead|task|commission|completion|job|review)[ _-]?#?\s*[:=]?\s*([A-Za-z0-9_\-]{2,})/i',(string)$text,$m)){
    foreach($m[0] as $x)$ids[$x]=$x;
  }
  return array_values($ids);
}
function gv76_evidence_status($contract,$text){
  $e=trim((string)($contract['evidence']??''));
  $click=trim((string)($contract['clickable_outputs']??''));
  $urls=gv76_extract_urls($text."\n".$e."\n".$click);
  $ids=gv76_extract_record_ids($text."\n".$e."\n".$click);
  if(stripos($e,'NEEDS_VERIFIED_RESEARCH')!==false || stripos($text,'NEEDS_VERIFIED_RESEARCH')!==false) return 'needs_verified_research';
  if(stripos($click,'NO_CLICKABLE_OUTPUT_CREATED')!==false) return 'missing_clickable_output';
  if(count($urls)>0 || count($ids)>0 || preg_match('/\.(csv|json|html|mp4|mov|webm|png|jpg|jpeg|pdf|xlsx)\b/i',$text)) return 'verified';
  return 'needs_evidence';
}
function gv76_create_deliverable($args){
  if(!gv76_table('goliath_deliverables')) gv76_install();
  $exec=strtolower((string)($args['executive_key']??'goliath'));
  $output=(string)($args['output']??'');
  $contract=gv76_parse_contract($output);
  $urls=gv76_extract_urls($output."\n".($contract['evidence']??'')."\n".($contract['clickable_outputs']??''));
  $ids=gv76_extract_record_ids($output);
  $evidenceStatus=gv76_evidence_status($contract,$output);
  $type=trim((string)($contract['deliverable_type']??'')) ?: ($args['deliverable_type']??'executive_plan');
  $title=trim((string)($args['title']??'')) ?: ucfirst($exec).' deliverable';

  $outputUrl=$args['output_url']??null;
  if(!$outputUrl && count($urls)) $outputUrl=$urls[0];

  $id=gdb_insert('goliath_deliverables',[
    'deliverable_uid'=>gv76_uid('del'),
    'executive_key'=>$exec,
    'deliverable_type'=>strtolower(preg_replace('/[^a-zA-Z0-9_\-]+/','_', $type)),
    'title'=>$title,
    'status'=>in_array($evidenceStatus,['verified'])?'complete':'needs_review',
    'evidence_status'=>$evidenceStatus,
    'evidence'=>$contract['evidence']??'',
    'output_summary'=>$contract['actionable_output'] ?: mb_substr(strip_tags($output),0,1200),
    'output_url'=>$outputUrl,
    'output_path'=>$args['output_path']??null,
    'source_urls'=>gv76_json($urls),
    'source_record_ids'=>gv76_json($ids),
    'related_commission_id'=>$args['commission_id']??null,
    'related_task_id'=>$args['task_id']??null,
    'related_completion_id'=>$args['completion_id']??null,
    'next_executive'=>gv76_guess_next_executive($exec,$contract),
    'next_action'=>$contract['next_action']??'',
    'review_status'=>$evidenceStatus==='verified'?'ready':'needs_evidence',
    'roi_score'=>gv76_roi_score($exec,$type,$evidenceStatus),
    'priority'=>$args['priority']??80,
    'metadata'=>gv76_json(['contract'=>$contract,'urls'=>$urls,'record_ids'=>$ids,'source'=>'v76'])
  ]);

  gv76_memory_update($exec,'last_deliverable','deliverable_'.$id,$title,'deliverable',$id);
  gv76_pipeline_event($exec,'deliverable_registry','complete',$evidenceStatus==='verified'?100:70,$title,'Deliverable registered with evidence status: '.$evidenceStatus,$args['commission_id']??null,$args['task_id']??null,$id);
  gv76_create_handoff_from_contract($exec,$id,$args['commission_id']??null,$args['task_id']??null,$contract);

  return ['success'=>true,'deliverable_id'=>$id,'evidence_status'=>$evidenceStatus,'urls'=>$urls];
}
function gv76_guess_next_executive($exec,$contract){
  $h=strtolower((string)($contract['handoffs']??''));
  foreach(['jessica','einstein','shakespeare','scorsese','rockefeller','prospector','pandora','scout','columbo','mozart','goliath'] as $x){
    if(strpos($h,$x)!==false && $x!==strtolower($exec)) return $x;
  }
  $type=strtolower((string)($contract['deliverable_type']??''));
  if(strpos($type,'lead')!==false) return 'jessica';
  if(strpos($type,'seo')!==false) return 'shakespeare';
  if(strpos($type,'opportunity')!==false) return 'jessica';
  if(strpos($type,'media')!==false || strpos($type,'video')!==false) return 'goliath';
  return null;
}
function gv76_roi_score($exec,$type,$evidenceStatus){
  $score=30;
  if($evidenceStatus==='verified') $score+=30;
  if(in_array(strtolower($exec),['scout','jessica','rockefeller','prospector','pandora'])) $score+=15;
  if(preg_match('/lead|opportunity|revenue|crm|follow|media|video|seo/i',(string)$type)) $score+=15;
  return min(100,$score);
}
function gv76_memory_update($exec,$type,$key,$value,$sourceType=null,$sourceId=null){
  if(!gv76_table('executive_memory')) gv76_install();
  try{
    gdb_exec("INSERT INTO executive_memory (executive_key,memory_type,memory_key,memory_value,source_type,source_id,confidence,active,created_at,updated_at)
      VALUES (:e,:t,:k,:v,:st,:sid,85,1,NOW(),NOW())
      ON DUPLICATE KEY UPDATE memory_value=VALUES(memory_value), source_type=VALUES(source_type), source_id=VALUES(source_id), updated_at=NOW(), active=1",[
      'e'=>strtolower($exec),'t'=>$type,'k'=>$key,'v'=>$value,'st'=>$sourceType,'sid'=>(string)$sourceId
    ]);
  }catch(Throwable $e){}
}
function gv76_pipeline_event($exec,$pipeline,$stage,$progress,$title,$detail,$commissionId=null,$taskId=null,$deliverableId=null,$status='complete'){
  if(!gv76_table('executive_pipeline_events')) gv76_install();
  try{
    return gdb_insert('executive_pipeline_events',[
      'event_uid'=>gv76_uid('pipe'),
      'executive_key'=>strtolower($exec),
      'pipeline_name'=>$pipeline,
      'stage'=>$stage,
      'status'=>$status,
      'progress'=>(int)$progress,
      'title'=>$title,
      'detail'=>$detail,
      'related_commission_id'=>$commissionId,
      'related_task_id'=>$taskId,
      'related_deliverable_id'=>$deliverableId,
      'metadata'=>gv76_json(['source'=>'v76'])
    ]);
  }catch(Throwable $e){return null;}
}
function gv76_create_handoff_from_contract($exec,$deliverableId,$commissionId,$taskId,$contract){
  $next=gv76_guess_next_executive($exec,$contract);
  if(!$next || $next===strtolower($exec)) return null;
  if(!gv76_table('executive_handoffs')) gv76_install();
  try{
    return gdb_insert('executive_handoffs',[
      'handoff_uid'=>gv76_uid('handoff'),
      'from_executive'=>strtolower($exec),
      'to_executive'=>strtolower($next),
      'title'=>'Handoff: '.($contract['actionable_output'] ? mb_substr($contract['actionable_output'],0,120) : 'Review deliverable'),
      'reason'=>$contract['handoffs']??'Auto-handoff from V76 deliverable contract.',
      'status'=>'queued',
      'related_deliverable_id'=>$deliverableId,
      'related_commission_id'=>$commissionId,
      'related_task_id'=>$taskId,
      'expected_output'=>$contract['next_action']??'Review and improve this deliverable.',
      'metadata'=>gv76_json(['source'=>'v76_auto_handoff','contract'=>$contract])
    ]);
  }catch(Throwable $e){return null;}
}
function gv76_counts(){
  gv76_install();
  return [
    'deliverables'=>(int)((gdb_one("SELECT COUNT(*) c FROM goliath_deliverables")?:['c'=>0])['c']),
    'verified'=>(int)((gdb_one("SELECT COUNT(*) c FROM goliath_deliverables WHERE evidence_status='verified'")?:['c'=>0])['c']),
    'needs_evidence'=>(int)((gdb_one("SELECT COUNT(*) c FROM goliath_deliverables WHERE evidence_status<>'verified'")?:['c'=>0])['c']),
    'handoffs_queued'=>(int)((gdb_one("SELECT COUNT(*) c FROM executive_handoffs WHERE status='queued'")?:['c'=>0])['c']),
    'pipeline_events'=>(int)((gdb_one("SELECT COUNT(*) c FROM executive_pipeline_events")?:['c'=>0])['c'])
  ];
}
?>