<?php
/**
 * Goliath V67 — Executive Heartbeat Runtime
 * One heartbeat model for every executive. Mission Control and office pages should read this layer.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function ghr_required_tables(){
  return ['executive_commissions','executive_heartbeats','goliath_heartbeat_events','goliath_review_queue','goliath_notifications'];
}

function ghr_table_ok($table){
  if(!gdb_enabled()) return false;
  try{
    $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
    return ((int)($r['c']??0))>0;
  }catch(Throwable $e){ return false; }
}

function ghr_health(){
  $tables=[];
  foreach(ghr_required_tables() as $t){ $tables[$t]=ghr_table_ok($t); }
  return ['ok'=>gdb_enabled() && !in_array(false,$tables,true),'configured'=>gdb_enabled(),'tables'=>$tables,'time'=>date('c')];
}

function ghr_execs(){
  return ['goliath','jessica','scout','scorsese','shakespeare','einstein','columbo','mozart','prospector','rockefeller','pandora'];
}

function ghr_display($key){ return ucfirst(strtolower(trim((string)$key))); }

function ghr_phase_for($status,$progress,$commission=[]){
  $status=strtolower((string)$status);
  if(!empty($commission['phase'])) return $commission['phase'];
  if(!empty($commission['current_step'])) return $commission['current_step'];
  if($status==='queued') return 'Queued for executive review';
  if($status==='claimed') return 'Claimed and analyzing';
  if($status==='working'){
    if($progress < 20) return 'Analyzing commission';
    if($progress < 40) return 'Requesting plugins and collaborators';
    if($progress < 70) return 'Producing work';
    if($progress < 90) return 'Quality pass';
    return 'Preparing review package';
  }
  if($status==='review') return 'Ready for Mark review';
  if(in_array($status,['complete','completed','delivered'],true)) return 'Completed and logged';
  if(in_array($status,['blocked','failed','error'],true)) return 'Blocked';
  return 'Standing by';
}

function ghr_active_commission($exec){
  $exec=strtolower($exec);
  $row=gdb_one("SELECT * FROM executive_commissions WHERE executive_key=? AND status IN ('working','claimed','review','queued') ORDER BY FIELD(status,'working','review','claimed','queued'), updated_at DESC LIMIT 1",[$exec]);
  return $row ?: null;
}

function ghr_counts($exec){
  $exec=strtolower($exec);
  $today=gdb_one("SELECT
    SUM(status IN ('complete','completed','delivered') AND DATE(COALESCE(completed_at,updated_at))=CURRENT_DATE) completed_today,
    SUM(status IN ('review','ready_for_review') OR ready_for_review=1) ready_review,
    SUM(status='queued') queued,
    SUM(status IN ('working','claimed')) working
    FROM executive_commissions WHERE executive_key=?",[$exec]) ?: [];
  return [
    'completed_today'=>(int)($today['completed_today']??0),
    'ready_review'=>(int)($today['ready_review']??0),
    'queued'=>(int)($today['queued']??0),
    'working'=>(int)($today['working']??0)
  ];
}

function ghr_write_event($exec,$commission,$status,$phase,$progress,$plugin,$eta,$message,$metadata=[]){
  if(!ghr_table_ok('goliath_heartbeat_events')) return 0;
  try{
    return gdb_insert('goliath_heartbeat_events',[
      'event_uid'=>gdb_uid('hb'),
      'executive_key'=>strtolower($exec),
      'commission_id'=>$commission['id']??null,
      'status'=>$status,
      'phase'=>$phase,
      'current_task'=>$commission['current_task'] ?? $commission['title'] ?? 'Executive work',
      'current_step'=>$commission['current_step'] ?? $phase,
      'progress_percent'=>(int)$progress,
      'plugin_active'=>$plugin,
      'eta_minutes'=>$eta,
      'message'=>$message,
      'metadata'=>gdb_json($metadata)
    ]);
  }catch(Throwable $e){ error_log('Goliath heartbeat event failed: '.$e->getMessage()); return 0; }
}

function ghr_upsert_heartbeat($exec,$commission=null){
  $exec=strtolower($exec);
  $display=ghr_display($exec);
  $counts=ghr_counts($exec);
  if($commission){
    $status=strtolower($commission['status'] ?? 'working');
    $progress=(int)($commission['progress_percent'] ?? $commission['progress'] ?? 0);
    if($progress<=0){
      $progress = $status==='queued' ? 0 : ($status==='claimed' ? 12 : ($status==='review' ? 92 : 25));
    }
    $phase=ghr_phase_for($status,$progress,$commission);
    $task=$commission['current_task'] ?? $commission['title'] ?? 'Executive work';
    $step=$commission['current_step'] ?? $phase;
    $plugin=$commission['plugin_active'] ?? $commission['plugin_in_use'] ?? null;
    $eta=$commission['eta_minutes'] ?? max(1,(int)ceil((100-min(99,$progress))/10));
    $commissionId=(int)($commission['id']??0);
  }else{
    $status=$counts['queued']>0?'queued':'idle';
    $progress=$counts['queued']>0?0:100;
    $phase=$counts['queued']>0?'Waiting to claim next commission':'Standing by';
    $task=$counts['queued']>0?'Queued work waiting':'Standing by';
    $step=$phase; $plugin=null; $eta=null; $commissionId=null;
  }
  $progress=max(0,min(100,$progress));
  $collab=(int)(($commission['collaboration_count']??0));
  try{
    gdb_exec("INSERT INTO executive_heartbeats
      (executive_key, executive, heartbeat_uid, status, phase, current_task, current_step, progress_percent, progress, plugin_active, plugin_in_use, eta_minutes, collaboration_count, ready_for_review_count, completed_today_count, last_heartbeat_at, updated_at, metadata)
      VALUES (:k,:e,:uid,:status,:phase,:task,:step,:pp,:p,:plugin,:plugin2,:eta,:collab,:review,:complete,NOW(),NOW(),:meta)
      ON DUPLICATE KEY UPDATE executive=VALUES(executive), status=VALUES(status), phase=VALUES(phase), current_task=VALUES(current_task), current_step=VALUES(current_step), progress_percent=VALUES(progress_percent), progress=VALUES(progress), plugin_active=VALUES(plugin_active), plugin_in_use=VALUES(plugin_in_use), eta_minutes=VALUES(eta_minutes), collaboration_count=VALUES(collaboration_count), ready_for_review_count=VALUES(ready_for_review_count), completed_today_count=VALUES(completed_today_count), last_heartbeat_at=NOW(), updated_at=NOW(), metadata=VALUES(metadata)",[
      'k'=>$exec,'e'=>$display,'uid'=>gdb_uid('heartbeat'),'status'=>$status,'phase'=>$phase,'task'=>$task,'step'=>$step,'pp'=>$progress,'p'=>$progress,'plugin'=>$plugin,'plugin2'=>$plugin,'eta'=>$eta,'collab'=>$collab,'review'=>$counts['ready_review'],'complete'=>$counts['completed_today'],'meta'=>gdb_json(['runtime'=>'V67','commission_id'=>$commissionId])
    ]);
    ghr_write_event($exec,$commission?:[], $status,$phase,$progress,$plugin,$eta,$display.' heartbeat: '.$phase,['runtime'=>'V67']);
    return true;
  }catch(Throwable $e){ error_log('Goliath heartbeat upsert failed: '.$e->getMessage()); return false; }
}

function ghr_review_transition($commission){
  if(!$commission) return false;
  $id=(int)($commission['id']??0); if(!$id) return false;
  $exec=strtolower($commission['executive_key'] ?? 'goliath');
  $display=ghr_display($exec);
  $progress=(int)($commission['progress_percent'] ?? $commission['progress'] ?? 0);
  $status=strtolower($commission['status'] ?? '');
  if($progress>=90 && !in_array($status,['complete','completed','delivered'],true)){
    try{
      gdb_update('executive_commissions',[
        'status'=>'review',
        'ready_for_review'=>1,
        'review_created_at'=>date('Y-m-d H:i:s'),
        'phase'=>'Ready for Mark review',
        'current_step'=>'Review package prepared'
      ],'id=:id',['id'=>$id]);
      if(function_exists('gal_review_item')){
        gal_review_item($display,'commission',$id,$commission['title'] ?? 'Executive work ready for review',$commission['current_task'] ?? 'Ready for review',null,['viral'=>70,'business'=>80,'emotional'=>70,'action'=>'Review and approve next step'],['runtime'=>'V67']);
      }
      if(function_exists('gal_notify')) gal_notify($display,'Ready for review',$display.' has work ready for review.','high',null,['commission_id'=>$id]);
      return true;
    }catch(Throwable $e){ error_log('Goliath review transition failed: '.$e->getMessage()); }
  }
  return false;
}

function ghr_run($limit=50){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured','time'=>date('c')];
  $health=ghr_health(); if(!$health['ok']) return ['ok'=>false,'error'=>'V67 tables missing','health'=>$health,'time'=>date('c')];
  $updated=0; $reviewed=0;
  foreach(ghr_execs() as $exec){
    $c=ghr_active_commission($exec);
    if(ghr_upsert_heartbeat($exec,$c)) $updated++;
    if($c && ghr_review_transition($c)) $reviewed++;
  }
  if(function_exists('gal_refresh_all_tallies')) gal_refresh_all_tallies();
  return ['ok'=>true,'heartbeats_updated'=>$updated,'review_items_created'=>$reviewed,'time'=>date('c')];
}
