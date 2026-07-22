<?php
/**
 * Goliath V69 — Knowledge Vault Expansion
 * Every commission, heartbeat, deliverable and relationship touch becomes durable memory.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function gkv_uid($prefix){ return function_exists('gdb_uid') ? gdb_uid($prefix) : $prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(6)),0,12); }
function gkv_table_ok($table){
  if(!gdb_enabled()) return false;
  try{
    $row=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
    return ((int)($row['c']??0))>0;
  }catch(Throwable $e){ return false; }
}
function gkv_required_tables(){
  return ['goliath_knowledge_assets','goliath_relationship_timeline','goliath_asset_compounding_queue','goliath_knowledge_vault_daily_digest','executive_commissions','executive_heartbeats'];
}
function gkv_health(){
  $tables=[]; foreach(gkv_required_tables() as $t){ $tables[$t]=gkv_table_ok($t); }
  return ['ok'=>gdb_enabled() && !in_array(false,$tables,true),'configured'=>gdb_enabled(),'tables'=>$tables,'time'=>date('c')];
}

function gkv_record_asset($row){
  if(!gdb_enabled() || !gkv_table_ok('goliath_knowledge_assets')) return 0;
  $payload=[
    'asset_uid'=>$row['asset_uid']??gkv_uid('asset'),
    'source_type'=>$row['source_type']??'runtime',
    'source_id'=>$row['source_id']??null,
    'executive'=>$row['executive']??null,
    'asset_type'=>$row['asset_type']??'note',
    'asset_status'=>$row['asset_status']??'active',
    'title'=>$row['title']??'Goliath Knowledge Asset',
    'summary'=>$row['summary']??null,
    'content'=>$row['content']??null,
    'value_created'=>$row['value_created']??null,
    'client_context'=>$row['client_context']??null,
    'review_url'=>$row['review_url']??null,
    'public_url'=>$row['public_url']??null,
    'priority'=>$row['priority']??'normal',
    'viral_potential_score'=>(int)($row['viral_potential_score']??0),
    'business_value_score'=>(int)($row['business_value_score']??0),
    'emotional_impact_score'=>(int)($row['emotional_impact_score']??0),
    'compounding_status'=>$row['compounding_status']??'not_started',
    'metadata'=>gdb_json($row['metadata']??[]),
    'completed_at'=>$row['completed_at']??null
  ];
  try{return gdb_insert('goliath_knowledge_assets',$payload);}catch(Throwable $e){error_log('GKV asset failed: '.$e->getMessage()); return 0;}
}

function gkv_timeline($row){
  if(!gdb_enabled() || !gkv_table_ok('goliath_relationship_timeline')) return 0;
  $payload=[
    'timeline_uid'=>$row['timeline_uid']??gkv_uid('tl'),
    'related_contact_id'=>$row['contact_id']??null,
    'related_lead_id'=>$row['lead_id']??null,
    'related_asset_id'=>$row['asset_id']??null,
    'related_commission_id'=>$row['commission_id']??null,
    'executive'=>$row['executive']??null,
    'event_type'=>$row['event_type']??'activity',
    'event_title'=>$row['event_title']??'Goliath activity',
    'event_summary'=>$row['event_summary']??null,
    'human_touch_note'=>$row['human_touch_note']??null,
    'recommended_next_action'=>$row['recommended_next_action']??null,
    'event_status'=>$row['event_status']??'recorded',
    'metadata'=>gdb_json($row['metadata']??[])
  ];
  try{return gdb_insert('goliath_relationship_timeline',$payload);}catch(Throwable $e){error_log('GKV timeline failed: '.$e->getMessage()); return 0;}
}

function gkv_queue_compounding($assetId,$commissionId,$title,$type='asset_aftercare',$priority='normal',$metadata=[]){
  if(!gdb_enabled() || !gkv_table_ok('goliath_asset_compounding_queue')) return 0;
  try{return gdb_insert('goliath_asset_compounding_queue',[
    'compounding_uid'=>gkv_uid('compound'),
    'asset_id'=>$assetId ?: null,
    'source_commission_id'=>$commissionId ?: null,
    'executive'=>'Einstein',
    'compounding_type'=>$type,
    'status'=>'queued',
    'priority'=>$priority,
    'title'=>$title,
    'recommended_action'=>'Begin SEO, AEO, internal linking, backlink research, repurposing and refresh lifecycle. Publication is the beginning of the asset lifecycle.',
    'target_channel'=>'Knowledge Vault',
    'expected_value'=>'Long-term discoverability, authority, relationship nurture and compounding business value.',
    'progress_percent'=>0,
    'metadata'=>gdb_json($metadata)
  ]);}catch(Throwable $e){error_log('GKV compounding failed: '.$e->getMessage()); return 0;}
}

function gkv_import_runtime($limit=50){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured','time'=>date('c')];
  $health=gkv_health(); if(!$health['ok']) return ['ok'=>false,'error'=>'V69 tables missing','health'=>$health,'time'=>date('c')];
  $assets=0; $timeline=0; $compound=0;

  $commissions=gdb_all("SELECT * FROM executive_commissions WHERE status IN ('review','complete','completed') ORDER BY updated_at DESC LIMIT ".(int)$limit);
  foreach($commissions as $c){
    $cid=(int)($c['id']??0); if(!$cid) continue;
    $dupe=gdb_one("SELECT id FROM goliath_knowledge_assets WHERE source_type='commission' AND source_id=? LIMIT 1",[$cid]);
    if($dupe) continue;
    $exec=ucfirst(strtolower($c['executive_key']??$c['executive']??'Goliath'));
    $title=$c['title']??$c['current_task']??('Commission #'.$cid);
    $summary=$c['summary']??$c['teamwork_notes']??('Runtime commission moved into the Knowledge Vault.');
    $assetId=gkv_record_asset([
      'source_type'=>'commission','source_id'=>$cid,'executive'=>$exec,'asset_type'=>'commission_output',
      'asset_status'=>($c['status']==='complete'||$c['status']==='completed')?'completed':'ready_for_review',
      'title'=>$title,'summary'=>$summary,
      'value_created'=>$c['value_created']??'Executive work preserved for review, reuse and compounding.',
      'priority'=>$c['priority']??'normal','business_value_score'=>(int)($c['business_value_score']??0),
      'metadata'=>['commission'=>$c]
    ]);
    if($assetId){
      $assets++;
      $timeline += gkv_timeline(['asset_id'=>$assetId,'commission_id'=>$cid,'executive'=>$exec,'event_type'=>'commission_preserved','event_title'=>$title,'event_summary'=>$summary,'recommended_next_action'=>'Review asset and allow Einstein to compound it.','metadata'=>['runtime'=>'V69']]) ? 1 : 0;
      $compound += gkv_queue_compounding($assetId,$cid,'Compound asset: '.$title,'post_delivery_curation',$c['priority']??'normal',['executive'=>$exec]) ? 1 : 0;
      if(function_exists('gal_action')) gal_action('Einstein','asset_compounding_queued','Einstein compounding queued','Asset entered the Knowledge Vault and was queued for post-delivery curation.','queued',0,['commission_id'=>$cid,'asset_id'=>$assetId,'source'=>'v69_knowledge_vault']);
    }
  }

  $ready=0; try{$r=gdb_one("SELECT COUNT(*) c FROM goliath_knowledge_assets WHERE asset_status='ready_for_review' AND DATE(created_at)=CURRENT_DATE"); $ready=(int)($r['c']??0);}catch(Throwable $e){}
  $completed=0; try{$r=gdb_one("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('complete','completed') AND DATE(updated_at)=CURRENT_DATE"); $completed=(int)($r['c']??0);}catch(Throwable $e){}
  try{
    gdb_exec("INSERT INTO goliath_knowledge_vault_daily_digest (digest_date, assets_created, timeline_events, compounding_items, ready_for_review, completed_commissions, digest_text, metadata)
      VALUES (CURRENT_DATE, :a, :t, :c, :r, :done, :txt, :meta)
      ON DUPLICATE KEY UPDATE assets_created=assets_created+VALUES(assets_created), timeline_events=timeline_events+VALUES(timeline_events), compounding_items=compounding_items+VALUES(compounding_items), ready_for_review=VALUES(ready_for_review), completed_commissions=VALUES(completed_commissions), digest_text=VALUES(digest_text), metadata=VALUES(metadata)",[
      'a'=>$assets,'t'=>$timeline,'c'=>$compound,'r'=>$ready,'done'=>$completed,
      'txt'=>'V69 Knowledge Vault updated. Assets preserved, relationship timeline expanded, Einstein compounding queue refreshed.',
      'meta'=>gdb_json(['runtime'=>'V69','time'=>date('c')])
    ]);
  }catch(Throwable $e){}
  return ['ok'=>true,'assets_created'=>$assets,'timeline_events_created'=>$timeline,'compounding_items_created'=>$compound,'ready_for_review'=>$ready,'completed_commissions_today'=>$completed,'time'=>date('c')];
}
