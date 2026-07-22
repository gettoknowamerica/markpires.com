<?php
/**
 * Goliath V93.2.2 Scout Run Cycle
 * Real CRM mapping fix: prioritizes priority_score and reads phone_1/phone_2/email_1/email_2.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  require_once __DIR__.'/scout-intelligence-helpers.php';

  $key=$_GET['key']??$_POST['key']??'';
  if(!$key && PHP_SAPI==='cli'){
    foreach($argv??[] as $arg){
      if(strpos($arg,'key=')===0) $key=substr($arg,4);
      if(strpos($arg,'limit=')===0) $_GET['limit']=substr($arg,6);
    }
  }
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function sr_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function sr_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function sr_exec($sql){if(function_exists('gdb_exec')) return gdb_exec($sql); $pdo=gdb(); return $pdo->exec($sql);}
  function sr_uid($p='sr'){if(function_exists('gdb_uid')) return gdb_uid($p); return $p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function sr_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function sr_install(){
    sr_exec("CREATE TABLE IF NOT EXISTS scout_intel_dossiers (
      id INT AUTO_INCREMENT PRIMARY KEY,
      dossier_uid VARCHAR(80) UNIQUE,
      mission_id INT NULL,
      contact_id INT NULL,
      lead_uid VARCHAR(80),
      owner_name VARCHAR(255),
      property_address VARCHAR(255),
      mailing_address VARCHAR(255),
      town VARCHAR(120),
      state VARCHAR(40),
      zip VARCHAR(40),
      phone VARCHAR(120),
      email VARCHAR(255),
      source_label VARCHAR(255),
      research_status VARCHAR(80) DEFAULT 'queued',
      handoff_status VARCHAR(80) DEFAULT 'not_ready',
      confidence_score INT DEFAULT 0,
      contact_confidence INT DEFAULT 0,
      property_confidence INT DEFAULT 0,
      market_confidence INT DEFAULT 0,
      listing_history MEDIUMTEXT,
      nearby_sales MEDIUMTEXT,
      public_notes MEDIUMTEXT,
      call_strategy MEDIUMTEXT,
      email_strategy MEDIUMTEXT,
      recommended_blog VARCHAR(255),
      next_action VARCHAR(255),
      evidence_log MEDIUMTEXT,
      raw_json JSON NULL,
      completed_at DATETIME NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX(mission_id), INDEX(contact_id), INDEX(research_status), INDEX(handoff_status), INDEX(completed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    sr_exec("CREATE TABLE IF NOT EXISTS scout_intel_events (
      id INT AUTO_INCREMENT PRIMARY KEY,
      event_uid VARCHAR(80) UNIQUE,
      mission_id INT NULL,
      dossier_id INT NULL,
      contact_id INT NULL,
      event_type VARCHAR(120),
      title VARCHAR(255),
      details MEDIUMTEXT,
      metadata JSON NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX(mission_id), INDEX(dossier_id), INDEX(event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  sr_install();

  if(!sr_table('internal_crm_contacts')){
    echo json_encode(['ok'=>false,'version'=>'V93.2.2 Scout CRM Mapping Fix','error'=>'missing_internal_crm_contacts_table','time'=>date('c')],JSON_PRETTY_PRINT);
    exit;
  }

  $limit=max(1,min(100,(int)($_GET['limit']??20)));
  $missionId=(int)($_GET['mission_id']??0);
  $todayStart=date('Y-m-d 00:00:00');
  $completedToday=(int)(gdb_one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE completed_at>=?",[$todayStart])['c']??0);

  $hasResearch=sr_col('internal_crm_contacts','research_status');
  $hasUpdated=sr_col('internal_crm_contacts','updated_at');
  $hasPriority=sr_col('internal_crm_contacts','priority_score');
  $hasPhone1=sr_col('internal_crm_contacts','phone_1');
  $hasPhone2=sr_col('internal_crm_contacts','phone_2');
  $hasEmail1=sr_col('internal_crm_contacts','email_1');
  $hasEmail2=sr_col('internal_crm_contacts','email_2');

  $alreadySql="SELECT COALESCE(contact_id,0) FROM scout_intel_dossiers WHERE contact_id IS NOT NULL";
  $where=["id NOT IN ($alreadySql)"];
  if($hasResearch) $where[]="COALESCE(research_status,'') IN ('','queued','needs_review','needs_research','needs_contact_research')";

  $contactBoost=[];
  if($hasPhone1) $contactBoost[]="COALESCE(phone_1,'')<>''";
  if($hasPhone2) $contactBoost[]="COALESCE(phone_2,'')<>''";
  if($hasEmail1) $contactBoost[]="COALESCE(email_1,'')<>''";
  if($hasEmail2) $contactBoost[]="COALESCE(email_2,'')<>''";
  $contactCase=$contactBoost ? "CASE WHEN (".implode(" OR ",$contactBoost).") THEN 1 ELSE 0 END DESC," : "";

  $order=$contactCase.($hasPriority ? "COALESCE(priority_score,0) DESC, id ASC" : "id ASC");
  $sql="SELECT * FROM internal_crm_contacts WHERE ".implode(" AND ",$where)." ORDER BY {$order} LIMIT {$limit}";
  $contacts=gdb_all($sql)?:[];

  $built=[];
  foreach($contacts as $c){
    $mid=$missionId;
    $result=scout_make_dossier_from_contact($c,$mid,'internal_crm');
    $built[]=$result;

    if(!empty($c['id']) && ($hasResearch || $hasUpdated)){
      $newStatus=($result['handoff_status']??'')==='ready_for_mark'?'ready_for_mark':'needs_research';
      $update=[];
      if($hasResearch) $update['research_status']=$newStatus;
      if($hasUpdated) $update['updated_at']=gdb_now();
      if($update) scout_update('internal_crm_contacts',(int)$c['id'],$update);
    }
  }

  $ready=0;$needs=0;
  foreach($built as $b){ if(($b['handoff_status']??'')==='ready_for_mark')$ready++; else $needs++; }

  $taskId=null;
  if(sr_table('local_ai_tasks')){
    $prompt="SCOUT CRM MAPPING CYCLE\n\nBuilt ".count($built)." dossiers using real CRM mapping. Ready for Mark: {$ready}. Needs more research: {$needs}. Phone fields: phone_1/phone_2. Email fields: email_1/email_2. Continue prioritizing high priority_score and records with contact info.";
    $taskId=scout_insert('local_ai_tasks',[
      'task_uid'=>sr_uid('lat'),'agent'=>'Scout','task_type'=>'scout_crm_mapping_cycle','model'=>'goliath-local-worker','prompt'=>$prompt,'status'=>'queued','priority'=>350,'progress'=>0,
      'metadata'=>sr_json(['built'=>count($built),'ready'=>$ready,'needs'=>$needs,'date'=>date('Y-m-d'),'version'=>'V93.2.2']),
      'created_at'=>gdb_now(),'updated_at'=>gdb_now()
    ]);
  }

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.2 Scout CRM Mapping Fix',
    'limit'=>$limit,
    'sql_used'=>$sql,
    'contacts_found'=>count($contacts),
    'built_count'=>count($built),
    'ready_for_mark'=>$ready,
    'needs_more_research'=>$needs,
    'completed_today_before'=>$completedToday,
    'completed_today_after'=>$completedToday+$ready,
    'daily_goal'=>20,
    'cycle_review_task_id'=>$taskId,
    'built'=>$built,
    'next'=>'Open /dashboard/scout-intelligence-center.php. Ready-for-Mark dossiers should now appear if phone_1/phone_2/email_1/email_2 exist.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

} catch(Throwable $e) {
  http_response_code(200);
  echo json_encode([
    'ok'=>false,
    'version'=>'V93.2.2 Scout CRM Mapping Fix',
    'error_type'=>get_class($e),
    'error'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine(),
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>