<?php
/**
 * Goliath V70 — Executive Council + Morning Brief
 * Converts runtime, collaboration, heartbeat and Knowledge Vault data into actionable executive recommendations.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function gec_uid($prefix){ return function_exists('gdb_uid') ? gdb_uid($prefix) : $prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(6)),0,12); }
function gec_table_ok($table){
  if(!gdb_enabled()) return false;
  try{ $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]); return ((int)($r['c']??0))>0; }
  catch(Throwable $e){ return false; }
}
function gec_required_tables(){
  return ['goliath_executive_council_reports','goliath_morning_briefs','goliath_council_action_items','executive_commissions','executive_heartbeats','goliath_review_queue','goliath_notifications'];
}
function gec_health(){
  $tables=[]; foreach(gec_required_tables() as $t){ $tables[$t]=gec_table_ok($t); }
  return ['ok'=>gdb_enabled() && !in_array(false,$tables,true),'configured'=>gdb_enabled(),'tables'=>$tables,'time'=>date('c')];
}
function gec_count($sql,$params=[]){ try{$r=gdb_one($sql,$params); return (int)($r['c']??0);}catch(Throwable $e){return 0;} }
function gec_rows($sql,$params=[]){ try{return gdb_all($sql,$params);}catch(Throwable $e){return [];} }
function gec_json($v){ return gdb_json($v ?: []); }

function gec_exec_rollup(){
  $execs=['Goliath','Jessica','Scout','Scorsese','Shakespeare','Einstein','Columbo','Mozart','Prospector','Rockefeller','Pandora'];
  $out=[];
  foreach($execs as $display){
    $key=strtolower($display);
    $hb=gec_rows("SELECT * FROM executive_heartbeats WHERE executive_key=? ORDER BY updated_at DESC LIMIT 1",[$key]);
    $hb=$hb[0]??[];
    $out[]=[
      'executive'=>$display,
      'status'=>$hb['status']??'unknown',
      'phase'=>$hb['phase']??($hb['current_step']??''),
      'current_task'=>$hb['current_task']??'',
      'progress'=>(int)($hb['progress']??0),
      'plugin'=>$hb['plugin_in_use']??($hb['active_plugin']??''),
      'collaboration_count'=>(int)($hb['collaboration_count']??0),
      'teamwork_score'=>(int)($hb['teamwork_score']??0),
      'ready_for_review'=>gec_count("SELECT COUNT(*) c FROM goliath_review_queue WHERE executive=? AND review_status IN ('ready','open','pending')",[$display]),
      'completed_today'=>gec_count("SELECT COUNT(*) c FROM executive_commissions WHERE executive_key=? AND status IN ('complete','completed') AND DATE(updated_at)=CURRENT_DATE",[$key]),
      'updated_at'=>$hb['updated_at']??null
    ];
  }
  return $out;
}

function gec_make_summary($rollup,$totals){
  $active=[]; $review=[]; $complete=[];
  foreach($rollup as $r){
    if(($r['current_task']??'')) $active[]=$r['executive'].': '.$r['current_task'].' ('.($r['progress']??0).'%)';
    if(($r['ready_for_review']??0)>0) $review[]=$r['executive'].' has '.$r['ready_for_review'].' ready for review';
    if(($r['completed_today']??0)>0) $complete[]=$r['executive'].' completed '.$r['completed_today'].' today';
  }
  $txt="Executive Council completed its review. ";
  $txt.="Today there are {$totals['ready']} items ready for review, {$totals['completed']} completed commissions, {$totals['collabs']} active collaboration requests, and {$totals['assets']} Knowledge Vault assets. ";
  if($complete) $txt.="Completed work: ".implode('; ',array_slice($complete,0,6)).'. ';
  if($review) $txt.="Review priorities: ".implode('; ',array_slice($review,0,6)).'. ';
  if($active) $txt.="Active work: ".implode('; ',array_slice($active,0,6)).'.';
  return $txt;
}

function gec_top_priority($reviewItems,$compounding,$rollup){
  if($reviewItems){ return 'Review: '.$reviewItems[0]['title'].' — '.$reviewItems[0]['executive'].' has work waiting for approval.'; }
  if($compounding){ return 'Einstein: begin compounding '.$compounding[0]['title'].' to create SEO, AEO, backlinks and future distribution value.'; }
  foreach($rollup as $r){ if(($r['progress']??0)>=85 && ($r['current_task']??'')) return $r['executive'].': '.$r['current_task'].' is near completion and should be watched for review.'; }
  return 'Let the runtime continue. Highest-value priority will be selected once review items or compounding opportunities appear.';
}

function gec_action_item($reportId,$executive,$title,$recommendation,$priority='normal',$type='recommendation',$metadata=[]){
  if(!gdb_enabled() || !gec_table_ok('goliath_council_action_items')) return 0;
  try{return gdb_insert('goliath_council_action_items',[
    'action_uid'=>gec_uid('council_action'),
    'report_id'=>$reportId ?: null,
    'executive'=>$executive,
    'action_type'=>$type,
    'priority'=>$priority,
    'title'=>$title,
    'recommendation'=>$recommendation,
    'status'=>'open',
    'metadata'=>gec_json($metadata)
  ]);}catch(Throwable $e){ error_log('GEC action item failed: '.$e->getMessage()); return 0; }
}

function gec_run($type='morning_brief'){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured','time'=>date('c')];
  $health=gec_health(); if(!$health['ok']) return ['ok'=>false,'error'=>'V70 tables missing','health'=>$health,'time'=>date('c')];

  $total=gec_count("SELECT COUNT(*) c FROM executive_commissions");
  $completed=gec_count("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('complete','completed') AND DATE(updated_at)=CURRENT_DATE");
  $ready=gec_count("SELECT COUNT(*) c FROM goliath_review_queue WHERE review_status IN ('ready','open','pending')");
  $collabs=gec_count("SELECT COUNT(*) c FROM goliath_collaboration_requests WHERE status IN ('accepted','working','open','queued')");
  $assets=gec_count("SELECT COUNT(*) c FROM goliath_knowledge_assets");
  $compounds=gec_count("SELECT COUNT(*) c FROM goliath_asset_compounding_queue WHERE status IN ('queued','working','open')");
  $rollup=gec_exec_rollup();
  $reviewItems=gec_rows("SELECT id, executive, title, summary, review_url, viral_potential_score, business_value_score, emotional_impact_score, created_at FROM goliath_review_queue WHERE review_status IN ('ready','open','pending') ORDER BY business_value_score DESC, viral_potential_score DESC, created_at DESC LIMIT 10");
  $compounding=gec_rows("SELECT id, title, recommended_action, expected_value, priority, created_at FROM goliath_asset_compounding_queue WHERE status IN ('queued','working','open') ORDER BY FIELD(priority,'urgent','high','normal','low'), created_at DESC LIMIT 10");
  $opps=gec_rows("SELECT id, executive, title, message, action_url, priority, created_at FROM goliath_notifications WHERE status IN ('new','open','queued') ORDER BY FIELD(priority,'urgent','high','normal','low'), created_at DESC LIMIT 10");
  $totals=['total'=>$total,'completed'=>$completed,'ready'=>$ready,'collabs'=>$collabs,'assets'=>$assets,'compounds'=>$compounds];
  $summary=gec_make_summary($rollup,$totals);
  $priority=gec_top_priority($reviewItems,$compounding,$rollup);
  $ceo="Good Morning Mark. If you only have one hour today, start with: ".$priority." Then review the highest-value ready-for-review items and allow Einstein to compound anything approved.";

  $reportId=0; $uid=gec_uid('council');
  try{
    gdb_exec("INSERT INTO goliath_executive_council_reports
      (report_uid, report_date, report_type, status, total_commissions, completed_commissions, ready_for_review, active_collaborations, knowledge_assets, compounding_items, highest_priority, council_summary, ceo_recommendation, executive_rollup, top_opportunities, review_items, metadata)
      VALUES (:uid, CURRENT_DATE, :type, 'created', :total, :completed, :ready, :collabs, :assets, :compounds, :priority, :summary, :ceo, :rollup, :opps, :reviews, :meta)
      ON DUPLICATE KEY UPDATE status='updated', total_commissions=VALUES(total_commissions), completed_commissions=VALUES(completed_commissions), ready_for_review=VALUES(ready_for_review), active_collaborations=VALUES(active_collaborations), knowledge_assets=VALUES(knowledge_assets), compounding_items=VALUES(compounding_items), highest_priority=VALUES(highest_priority), council_summary=VALUES(council_summary), ceo_recommendation=VALUES(ceo_recommendation), executive_rollup=VALUES(executive_rollup), top_opportunities=VALUES(top_opportunities), review_items=VALUES(review_items), metadata=VALUES(metadata)",[
      'uid'=>$uid,'type'=>$type,'total'=>$total,'completed'=>$completed,'ready'=>$ready,'collabs'=>$collabs,'assets'=>$assets,'compounds'=>$compounds,'priority'=>$priority,'summary'=>$summary,'ceo'=>$ceo,'rollup'=>gec_json($rollup),'opps'=>gec_json($opps),'reviews'=>gec_json($reviewItems),'meta'=>gec_json(['runtime'=>'V70','compounding'=>$compounding,'time'=>date('c')])
    ]);
    $row=gdb_one("SELECT id FROM goliath_executive_council_reports WHERE report_date=CURRENT_DATE AND report_type=? LIMIT 1",[$type]);
    $reportId=(int)($row['id']??0);
  }catch(Throwable $e){ error_log('GEC report failed: '.$e->getMessage()); }

  $briefText=$ceo."\n\n".$summary."\n\nReady for review: ".$ready.". Completed today: ".$completed.". Active collaborations: ".$collabs.". Einstein compounding queue: ".$compounds.".";
  try{
    gdb_exec("INSERT INTO goliath_morning_briefs
      (brief_uid, brief_date, status, headline, executive_summary, top_priority, mark_actions, completed_work, review_queue, opportunity_queue, collaboration_highlights, einstein_compounding, brief_text, metadata)
      VALUES (:uid, CURRENT_DATE, 'ready', 'Good Morning Mark', :summary, :priority, :actions, :completed, :reviews, :opps, :collab, :einstein, :brief, :meta)
      ON DUPLICATE KEY UPDATE status='ready', executive_summary=VALUES(executive_summary), top_priority=VALUES(top_priority), mark_actions=VALUES(mark_actions), completed_work=VALUES(completed_work), review_queue=VALUES(review_queue), opportunity_queue=VALUES(opportunity_queue), collaboration_highlights=VALUES(collaboration_highlights), einstein_compounding=VALUES(einstein_compounding), brief_text=VALUES(brief_text), metadata=VALUES(metadata)",[
      'uid'=>gec_uid('brief'),'summary'=>$summary,'priority'=>$priority,'actions'=>gec_json([['title'=>'Start here','recommendation'=>$priority]]),'completed'=>gec_json($rollup),'reviews'=>gec_json($reviewItems),'opps'=>gec_json($opps),'collab'=>gec_json(['active_collaborations'=>$collabs]),'einstein'=>gec_json($compounding),'brief'=>$briefText,'meta'=>gec_json(['runtime'=>'V70','report_id'=>$reportId])
    ]);
  }catch(Throwable $e){ error_log('GEC brief failed: '.$e->getMessage()); }

  $createdActions=0;
  if($priority) $createdActions += gec_action_item($reportId,'Goliath','CEO Priority',$priority,'high','ceo_priority') ? 1 : 0;
  foreach(array_slice($reviewItems,0,3) as $ri){ $createdActions += gec_action_item($reportId,$ri['executive']?:'Goliath','Review ready: '.$ri['title'],$ri['summary'] ?: 'Review this ready item.','high','review_item',['review_id'=>$ri['id']]) ? 1 : 0; }
  foreach(array_slice($compounding,0,3) as $ci){ $createdActions += gec_action_item($reportId,'Einstein','Compound asset: '.$ci['title'],$ci['recommended_action'] ?: 'Begin compounding lifecycle.','normal','asset_compounding',['compounding_id'=>$ci['id']]) ? 1 : 0; }

  if(function_exists('gal_notify')) gal_notify('Goliath','Executive Council complete',$ceo,'high','/dashboard/goliath-morning-brief.php',['runtime'=>'V70','report_id'=>$reportId]);
  if(function_exists('gal_action')) gal_action('Goliath','executive_council_report','Executive Council Report Created',$summary,'complete',100,['source'=>'v70_executive_council','metadata'=>['report_id'=>$reportId,'type'=>$type]]);

  return ['ok'=>true,'report_id'=>$reportId,'morning_brief_ready'=>true,'action_items_created'=>$createdActions,'ready_for_review'=>$ready,'completed_today'=>$completed,'active_collaborations'=>$collabs,'knowledge_assets'=>$assets,'compounding_items'=>$compounds,'time'=>date('c')];
}
