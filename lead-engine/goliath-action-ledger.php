<?php
/**
 * Goliath V65 — CRM Action Ledger Bridge
 * Every executive action should flow through the Hostinger Knowledge Vault.
 */
require_once __DIR__ . '/goliath-db.php';

function gal_has_table($table){
  static $cache=[];
  if(isset($cache[$table])) return $cache[$table];
  if(!gdb_enabled()) return $cache[$table]=false;
  try{
    $r=gdb_one("SHOW TABLES LIKE ?",[$table]);
    return $cache[$table]=(bool)$r;
  }catch(Throwable $e){ return $cache[$table]=false; }
}

function gal_action($executive,$type,$title,$summary='',$status='open',$progress=0,$opts=[]){
  if(!gdb_enabled() || !gal_has_table('goliath_crm_action_ledger')) return 0;
  $row=[
    'action_uid'=>$opts['action_uid']??gdb_uid('act'),
    'related_contact_id'=>$opts['contact_id']??null,
    'related_lead_id'=>$opts['lead_id']??null,
    'related_commission_id'=>$opts['commission_id']??null,
    'related_asset_id'=>$opts['asset_id']??null,
    'executive'=>ucfirst((string)$executive),
    'action_type'=>$type,
    'action_status'=>$status,
    'priority'=>$opts['priority']??'normal',
    'title'=>$title,
    'summary'=>$summary,
    'details'=>$opts['details']??null,
    'source'=>$opts['source']??'executive_nervous_system',
    'crm_stage'=>$opts['crm_stage']??null,
    'progress_percent'=>max(0,min(100,(int)$progress)),
    'ready_for_review'=>!empty($opts['ready_for_review'])?1:0,
    'review_url'=>$opts['review_url']??null,
    'due_at'=>$opts['due_at']??null,
    'completed_at'=>$opts['completed_at']??null,
    'metadata'=>gdb_json($opts['metadata']??[])
  ];
  try{return gdb_insert('goliath_crm_action_ledger',$row);}catch(Throwable $e){error_log('Goliath ledger write failed: '.$e->getMessage()); return 0;}
}

function gal_notify($executive,$title,$message='',$priority='normal',$url=null,$metadata=[]){
  if(!gdb_enabled()) return 0;
  if(gal_has_table('goliath_notifications')){
    try{
      return gdb_insert('goliath_notifications',[
        'notification_uid'=>gdb_uid('n'),
        'executive'=>ucfirst((string)$executive),
        'notification_type'=>'activity',
        'priority'=>$priority,
        'title'=>$title,
        'message'=>$message,
        'action_url'=>$url,
        'metadata'=>gdb_json($metadata)
      ]);
    }catch(Throwable $e){ error_log('Goliath notification write failed: '.$e->getMessage()); }
  }
  return 0;
}

function gal_review_item($executive,$sourceType,$sourceId,$title,$summary='',$url=null,$scores=[],$metadata=[]){
  if(!gdb_enabled() || !gal_has_table('goliath_review_queue')) return 0;
  try{
    return gdb_insert('goliath_review_queue',[
      'review_uid'=>gdb_uid('review'),
      'executive'=>ucfirst((string)$executive),
      'source_type'=>$sourceType,
      'source_id'=>$sourceId,
      'title'=>$title,
      'summary'=>$summary,
      'review_status'=>'ready',
      'viral_potential_score'=>(int)($scores['viral']??0),
      'business_value_score'=>(int)($scores['business']??0),
      'emotional_impact_score'=>(int)($scores['emotional']??0),
      'recommended_action'=>$scores['action']??'Review and approve next step',
      'review_url'=>$url,
      'metadata'=>gdb_json($metadata)
    ]);
  }catch(Throwable $e){ error_log('Goliath review queue write failed: '.$e->getMessage()); return 0; }
}

function gal_refresh_daily_tally($executive){
  if(!gdb_enabled() || !gal_has_table('goliath_executive_daily_tallies')) return false;
  $execKey=strtolower((string)$executive);
  $display=ucfirst($execKey);
  $counts=gdb_one("SELECT
    SUM(status='queued') queued_count,
    SUM(status='working') working_count,
    SUM(status='review') review_count,
    SUM(status='complete') completed_count,
    SUM(status IN ('blocked','failed')) blocked_count
    FROM executive_commissions WHERE executive_key=?",[$execKey]) ?: [];
  $active=gdb_one("SELECT current_task, progress FROM executive_commissions WHERE executive_key=? AND status IN ('working','review') ORDER BY updated_at DESC LIMIT 1",[$execKey]);
  $notes=gdb_one("SELECT COUNT(*) c FROM goliath_notifications WHERE executive=? AND DATE(created_at)=CURRENT_DATE",[$display]) ?: ['c'=>0];
  try{
    gdb_exec("INSERT INTO goliath_executive_daily_tallies
      (tally_date, executive, queued_count, working_count, review_count, completed_count, blocked_count, notifications_count, last_heartbeat_at, current_task, current_progress)
      VALUES (CURRENT_DATE, :e, :q, :w, :r, :c, :b, :n, NOW(), :task, :progress)
      ON DUPLICATE KEY UPDATE queued_count=VALUES(queued_count), working_count=VALUES(working_count), review_count=VALUES(review_count), completed_count=VALUES(completed_count), blocked_count=VALUES(blocked_count), notifications_count=VALUES(notifications_count), last_heartbeat_at=NOW(), current_task=VALUES(current_task), current_progress=VALUES(current_progress)",[
      'e'=>$display,
      'q'=>(int)($counts['queued_count']??0),'w'=>(int)($counts['working_count']??0),'r'=>(int)($counts['review_count']??0),'c'=>(int)($counts['completed_count']??0),'b'=>(int)($counts['blocked_count']??0),'n'=>(int)($notes['c']??0),
      'task'=>$active['current_task']??null,'progress'=>(int)($active['progress']??0)
    ]);
    return true;
  }catch(Throwable $e){ error_log('Goliath tally refresh failed: '.$e->getMessage()); return false; }
}

function gal_refresh_all_tallies(){
  foreach(['goliath','jessica','scout','scorsese','shakespeare','einstein','columbo','mozart','prospector','rockefeller','pandora'] as $e){ gal_refresh_daily_tally($e); }
}
