<?php
/**
 * Goliath V68 — Runtime Synchronization Engine
 * One runtime object for Mission Control, executive offices, worker output, CRM, and briefings.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function grs_uid($prefix){ return function_exists('gdb_uid') ? gdb_uid($prefix) : $prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(6)),0,12); }
function grs_execs(){ return ['goliath'=>'Goliath','jessica'=>'Jessica','scout'=>'Scout','scorsese'=>'Scorsese','shakespeare'=>'Shakespeare','einstein'=>'Einstein','columbo'=>'Columbo','mozart'=>'Mozart','prospector'=>'Prospector','rockefeller'=>'Rockefeller','pandora'=>'Pandora']; }
function grs_table_ok($table){
  if(!gdb_enabled()) return false;
  try{
    $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
    return ((int)($r['c']??0))>0;
  }catch(Throwable $e){ return false; }
}
function grs_required_tables(){ return ['executive_commissions','executive_heartbeats','goliath_runtime_snapshots','goliath_runtime_events']; }
function grs_health(){
  $tables=[]; foreach(grs_required_tables() as $t){ $tables[$t]=grs_table_ok($t); }
  return ['ok'=>gdb_enabled() && !in_array(false,$tables,true),'configured'=>gdb_enabled(),'tables'=>$tables,'time'=>date('c')];
}
function grs_counts_for($execKey){
  $counts=['queued'=>0,'working'=>0,'review'=>0,'complete'=>0];
  if(grs_table_ok('executive_commissions')){
    $row=gdb_one("SELECT
      SUM(status IN ('queued','pending','new')) queued,
      SUM(status IN ('claimed','working','running','in_progress','processing')) working,
      SUM(status IN ('review','ready_for_review','ready')) review,
      SUM(status IN ('complete','completed','done','archived') AND DATE(updated_at)=CURRENT_DATE) complete
      FROM executive_commissions WHERE executive_key=?",[$execKey]) ?: [];
    foreach($counts as $k=>$v){ $counts[$k]=(int)($row[$k]??0); }
  }
  return $counts;
}
function grs_latest_heartbeat($execKey){
  if(!grs_table_ok('executive_heartbeats')) return null;
  return gdb_one("SELECT * FROM executive_heartbeats WHERE executive_key=? ORDER BY updated_at DESC LIMIT 1",[$execKey]);
}
function grs_ready_count($execKey){
  if(!grs_table_ok('goliath_review_queue')) return 0;
  $display=ucfirst($execKey);
  $r=gdb_one("SELECT COUNT(*) c FROM goliath_review_queue WHERE executive=? AND review_status IN ('ready','open','pending')",[$display]);
  return (int)($r['c']??0);
}
function grs_collab_count($execKey){
  if(!grs_table_ok('goliath_collaboration_requests')) return 0;
  $display=ucfirst($execKey);
  $r=gdb_one("SELECT COUNT(*) c FROM goliath_collaboration_requests WHERE (source_executive=? OR target_executive=?) AND DATE(created_at)=CURRENT_DATE",[$display,$display]);
  return (int)($r['c']??0);
}
function grs_sync_exec($execKey,$execName){
  $hb=grs_latest_heartbeat($execKey) ?: [];
  $counts=grs_counts_for($execKey);
  $ready=grs_ready_count($execKey);
  $collabs=grs_collab_count($execKey);
  $progress=(int)($hb['progress'] ?? $hb['progress_percent'] ?? 0);
  if($progress<=0){
    if($counts['working']>0) $progress=18; elseif($counts['review']>0) $progress=92; elseif($counts['complete']>0) $progress=100; else $progress=0;
  }
  $status=$hb['status'] ?? ($counts['review']>0?'review':($counts['working']>0?'working':($counts['queued']>0?'queued':'idle')));
  $phase=$hb['phase'] ?? $hb['current_step'] ?? ($status==='review'?'Ready for review':ucfirst($status));
  $task=$hb['current_task'] ?? $hb['task_title'] ?? $hb['commission_title'] ?? ($counts['queued']>0?'Queued executive work':'Standing by');
  $plugin=$hb['plugin_in_use'] ?? $hb['plugin'] ?? null;
  $eta=isset($hb['eta_minutes'])?(int)$hb['eta_minutes']:null;
  $last=$hb['updated_at'] ?? $hb['last_heartbeat_at'] ?? null;
  $meta=['heartbeat'=>$hb,'counts'=>$counts];
  $row=[
    'snapshot_uid'=>grs_uid('snap'),
    'executive_key'=>$execKey,'executive_name'=>$execName,'status'=>$status,'phase'=>$phase,
    'current_task'=>$task,'current_step'=>$hb['current_step'] ?? $phase,'progress_percent'=>max(0,min(100,$progress)),
    'plugin_in_use'=>$plugin,'eta_minutes'=>$eta,'queued_count'=>$counts['queued'],'working_count'=>$counts['working'],
    'review_count'=>$counts['review'],'completed_today'=>$counts['complete'],'ready_for_review'=>$ready,
    'collaboration_count'=>$collabs,'last_heartbeat_at'=>$last,'runtime_owner'=>'goliath-hostinger-v68',
    'source_tables'=>gdb_json(['commissions'=>'executive_commissions','heartbeats'=>'executive_heartbeats','review'=>'goliath_review_queue']),
    'metadata'=>gdb_json($meta)
  ];
  $exists=gdb_one('SELECT id, progress_percent, status FROM goliath_runtime_snapshots WHERE executive_key=? LIMIT 1',[$execKey]);
  if($exists){
    unset($row['snapshot_uid']);
    gdb_update('goliath_runtime_snapshots',$row,'executive_key=:exec',['exec'=>$execKey]);
  } else { gdb_insert('goliath_runtime_snapshots',$row); }
  if(!$exists || (int)($exists['progress_percent']??-1)!==$row['progress_percent'] || ($exists['status']??'')!==$row['status']){
    gdb_insert('goliath_runtime_events',[
      'event_uid'=>grs_uid('rte'),'executive_key'=>$execKey,'event_type'=>'runtime_sync',
      'title'=>$execName.' runtime updated','summary'=>$phase.' — '.$task,
      'progress_percent'=>$row['progress_percent'],'metadata'=>gdb_json(['status'=>$status,'ready_for_review'=>$ready,'completed_today'=>$counts['complete']])
    ]);
  }
  return $row;
}
function grs_run(){
  $h=grs_health(); if(!$h['ok']) return ['ok'=>false,'error'=>'V68 tables missing','health'=>$h,'time'=>date('c')];
  $updated=0; $snapshots=[];
  foreach(grs_execs() as $key=>$name){ $snapshots[$key]=grs_sync_exec($key,$name); $updated++; }
  if(function_exists('gal_refresh_all_tallies')) gal_refresh_all_tallies();
  return ['ok'=>true,'runtime_snapshots_updated'=>$updated,'time'=>date('c')];
}
function grs_get_runtime($execKey=null){
  if(!gdb_enabled() || !grs_table_ok('goliath_runtime_snapshots')) return [];
  if($execKey) return gdb_one('SELECT * FROM goliath_runtime_snapshots WHERE executive_key=? LIMIT 1',[strtolower($execKey)]) ?: [];
  return gdb_all('SELECT * FROM goliath_runtime_snapshots ORDER BY executive_name ASC');
}
