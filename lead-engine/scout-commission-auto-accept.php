<?php
/**
 * V93.2.11 Scout Commission Auto-Accept — schema-safe version
 * Fixes missing executive column issue.
 * Also adds safe missing columns to executive_commissions when useful.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
  }

  function tbl9311($t){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function col9311($t,$c){
    try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}
  }
  function exec9311($sql){
    if(function_exists('gdb_exec')) return gdb_exec($sql);
    $pdo=gdb(); return $pdo->exec($sql);
  }
  function add9311($table,$col,$def,&$added,&$skipped){
    if(!tbl9311($table)){ $skipped[]="$table missing"; return; }
    if(col9311($table,$col)){ $skipped[]="$table.$col exists"; return; }
    exec9311("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    $added[]="$table.$col";
  }
  function uid9311($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
  function js9311($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
  function ins9311($t,$row){$safe=[];foreach($row as $k=>$v){if(col9311($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
  function upd9311($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(col9311($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}

  if(!tbl9311('local_ai_tasks') || !tbl9311('executive_commissions')){
    echo json_encode(['ok'=>false,'error'=>'missing local_ai_tasks or executive_commissions table']);
    exit;
  }

  $added=[]; $skipped=[];

  // Make executive_commissions friendly to both old and new code.
  add9311('executive_commissions','commission_uid',"VARCHAR(80) NULL",$added,$skipped);
  add9311('executive_commissions','executive_key',"VARCHAR(80) NULL",$added,$skipped);
  add9311('executive_commissions','executive',"VARCHAR(80) NULL",$added,$skipped);
  add9311('executive_commissions','title',"VARCHAR(255) NULL",$added,$skipped);
  add9311('executive_commissions','description',"MEDIUMTEXT NULL",$added,$skipped);
  add9311('executive_commissions','prompt',"MEDIUMTEXT NULL",$added,$skipped);
  add9311('executive_commissions','status',"VARCHAR(80) DEFAULT 'queued'",$added,$skipped);
  add9311('executive_commissions','priority',"INT DEFAULT 100",$added,$skipped);
  add9311('executive_commissions','progress',"INT DEFAULT 0",$added,$skipped);
  add9311('executive_commissions','current_step',"VARCHAR(255) NULL",$added,$skipped);
  add9311('executive_commissions','metadata',"JSON NULL",$added,$skipped);
  add9311('executive_commissions','created_at',"DATETIME DEFAULT CURRENT_TIMESTAMP",$added,$skipped);
  add9311('executive_commissions','updated_at',"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",$added,$skipped);
  add9311('executive_commissions','completed_at',"DATETIME NULL",$added,$skipped);

  $limit=max(1,min(500,(int)($_GET['limit']??200)));

  $tasks=gdb_all("SELECT * FROM local_ai_tasks
    WHERE LOWER(agent)='scout'
      AND task_type LIKE 'scout_contact_enrichment%'
      AND (commission_id IS NULL OR commission_id=0)
    ORDER BY id ASC
    LIMIT {$limit}")?:[];

  $linked=[];
  foreach($tasks as $t){
    $meta=json_decode($t['metadata']??'',true); if(!is_array($meta))$meta=[];
    $dossierId=(int)($meta['dossier_id']??0);
    $contactId=(int)($meta['contact_id']??0);
    $owner=$meta['owner']??'Scout Lead';
    $address=$meta['address']??'';
    $title="Scout Contact Enrichment: ".$owner.($address?' — '.$address:'');

    $taskStatus=(string)($t['status']??'queued');
    $commissionStatus=($taskStatus==='completed')?'complete':'accepted';
    $progress=($taskStatus==='completed')?100:10;

    $commissionId=ins9311('executive_commissions',[
      'commission_uid'=>uid9311('commission'),
      'executive_key'=>'scout',
      'executive'=>'Scout',
      'title'=>$title,
      'description'=>(string)($t['prompt']??''),
      'prompt'=>(string)($t['prompt']??''),
      'status'=>$commissionStatus,
      'priority'=>(int)($t['priority']??500),
      'progress'=>$progress,
      'current_step'=>($taskStatus==='completed'?'Worker completed; enrichment awaiting/apply review.':'Accepted automatically by Scout.'),
      'metadata'=>js9311(['dossier_id'=>$dossierId,'contact_id'=>$contactId,'task_id'=>(int)$t['id'],'auto_accepted'=>true,'version'=>'V93.2.11']),
      'created_at'=>$t['created_at']??date('Y-m-d H:i:s'),
      'updated_at'=>date('Y-m-d H:i:s'),
      'completed_at'=>($taskStatus==='completed'?($t['completed_at']??date('Y-m-d H:i:s')):null)
    ]);

    if($commissionId){
      upd9311('local_ai_tasks',(int)$t['id'],['commission_id'=>$commissionId,'updated_at'=>date('Y-m-d H:i:s')]);
      if($dossierId && tbl9311('scout_intel_dossiers')){
        upd9311('scout_intel_dossiers',$dossierId,[
          'next_action'=>'Scout accepted commission #'.$commissionId.'. Contact enrichment in progress/review.',
          'updated_at'=>date('Y-m-d H:i:s')
        ]);
      }
      $linked[]=['task_id'=>(int)$t['id'],'commission_id'=>$commissionId,'status'=>$commissionStatus,'owner'=>$owner,'address'=>$address];
    }
  }

  // Build schema-safe counts. Never reference a missing column in WHERE.
  $execWhere = col9311('executive_commissions','executive_key')
    ? "LOWER(COALESCE(executive_key,''))='scout'"
    : (col9311('executive_commissions','executive') ? "LOWER(COALESCE(executive,''))='scout'" : "1=1");

  $counts=[
    'scout_enrichment_without_commission'=>(int)(gdb_one("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND task_type LIKE 'scout_contact_enrichment%' AND (commission_id IS NULL OR commission_id=0)")['c']??0),
    'scout_enrichment_with_commission'=>(int)(gdb_one("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND task_type LIKE 'scout_contact_enrichment%' AND commission_id>0")['c']??0),
    'scout_commissions'=>(int)(gdb_one("SELECT COUNT(*) c FROM executive_commissions WHERE {$execWhere}")['c']??0)
  ];

  echo json_encode([
    'ok'=>true,
    'version'=>'V93.2.11 Scout Commission Auto-Accept Schema-Safe',
    'schema_added'=>$added,
    'schema_skipped'=>$skipped,
    'linked_count'=>count($linked),
    'linked'=>$linked,
    'counts'=>$counts,
    'next'=>'Commission 0 should now be linked. Re-run after creating fresh Scout tasks.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V93.2.11 Scout Commission Auto-Accept Schema-Safe','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>