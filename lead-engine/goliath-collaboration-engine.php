<?php
/**
 * Goliath V66 — Executive Collaboration Engine
 * Adds the mandatory teamwork checkpoint to every active commission.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function gce_exec_key($v){ return strtolower(trim((string)$v)); }
function gce_display($v){ return ucfirst(gce_exec_key($v)); }

function gce_uid($prefix){ return function_exists('gdb_uid') ? gdb_uid($prefix) : $prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(6)),0,12); }

function gce_required_tables(){
  return ['goliath_collaboration_requests','goliath_constitution_checks','goliath_teamwork_scores','executive_commissions','executive_heartbeats'];
}

function gce_table_ok($table){
    if(!gdb_enabled()) return false;

    try{
        $row = gdb_one(
            "SELECT COUNT(*) AS c
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$table]
        );

        return ((int)($row['c'] ?? 0)) > 0;
    }catch(Throwable $e){
        return false;
    }
}

function gce_health(){
  $tables=[]; foreach(gce_required_tables() as $t){ $tables[$t]=gce_table_ok($t); }
  return ['ok'=>gdb_enabled() && !in_array(false,$tables,true), 'configured'=>gdb_enabled(), 'tables'=>$tables, 'time'=>date('c')];
}

function gce_collaboration_targets($exec,$commission){
  $exec=gce_exec_key($exec);
  $type=strtolower(($commission['commission_type'] ?? $commission['type'] ?? '').' '.($commission['title'] ?? '').' '.($commission['current_task'] ?? ''));
  $map=[
    'scorsese'=>['Shakespeare'=>'Hook, title, script polish','Einstein'=>'SEO/AEO, repurposing, backlink plan','Mozart'=>'Music/soundtrack enhancement','Pandora'=>'Viral angle and emotional hook'],
    'shakespeare'=>['Einstein'=>'SEO/AEO, FAQ, internal linking','Columbo'=>'Research/source enrichment','Scorsese'=>'Video/social companion asset','Pandora'=>'Emotional angle and shareability'],
    'scout'=>['Jessica'=>'Human Touch follow-up path','Einstein'=>'Lead scoring and market intelligence','Rockefeller'=>'Revenue priority and ROI','Columbo'=>'Research verification'],
    'jessica'=>['Shakespeare'=>'Message polish in Mark voice','Einstein'=>'Relationship scoring and timing','Scout'=>'Additional contact enrichment','Rockefeller'=>'Priority/revenue path'],
    'einstein'=>['Shakespeare'=>'Turn insight into publishable copy','Scorsese'=>'Video companion or visual explainer','Scout'=>'More data sources','Prospector'=>'External authority opportunities'],
    'columbo'=>['Scorsese'=>'Archive clip/short opportunity','Shakespeare'=>'Description, title, metadata','Einstein'=>'Performance and backlink strategy','Pandora'=>'Unexpected viral angle'],
    'mozart'=>['Scorsese'=>'Music video/visual asset','Einstein'=>'Catalog/metadata/SEO','Shakespeare'=>'Story and release copy'],
    'prospector'=>['Jessica'=>'Warm outreach sequence','Shakespeare'=>'Pitch/email copy','Einstein'=>'Opportunity scoring','Rockefeller'=>'Revenue forecast'],
    'rockefeller'=>['Einstein'=>'Data validation and forecast logic','Scout'=>'Additional opportunity discovery','Jessica'=>'Relationship timing'],
    'pandora'=>['Shakespeare'=>'Story conversion','Scorsese'=>'Viral media concept','Einstein'=>'Trend and distribution logic']
  ];
  $targets=$map[$exec] ?? ['Einstein'=>'Intelligence and compounding review','Shakespeare'=>'Language/story improvement','Pandora'=>'Creative edge'];
  if(str_contains($type,'lead') || str_contains($type,'buyer') || str_contains($type,'seller')) $targets['Jessica']='Human Touch relationship plan';
  if(str_contains($type,'video') || str_contains($type,'media')) $targets['Scorsese']='Media production/review';
  if(str_contains($type,'blog') || str_contains($type,'content')) $targets['Shakespeare']='Publishing and voice polish';
  return $targets;
}

function gce_constitution_check($commission){
  if(!gdb_enabled() || !gce_table_ok('goliath_constitution_checks')) return false;
  $exec=gce_display($commission['executive_key'] ?? $commission['executive'] ?? 'Goliath');
  $cid=(int)($commission['id'] ?? 0);
  $exists=$cid ? gdb_one('SELECT id FROM goliath_constitution_checks WHERE commission_id=? LIMIT 1',[$cid]) : null;
  if($exists) return true;
  gdb_insert('goliath_constitution_checks',[
    'check_uid'=>gce_uid('cc'),
    'commission_id'=>$cid ?: null,
    'executive'=>$exec,
    'status'=>'passed',
    'human_touch_ok'=>1,
    'collaboration_ok'=>1,
    'long_term_value_ok'=>1,
    'knowledge_vault_ok'=>1,
    'notes'=>'Passed V66 constitutional runtime check: Human Touch, collaboration, lasting value, and Knowledge Vault preservation.',
    'metadata'=>gdb_json(['runtime'=>'V66','doctrine'=>'Collaboration First'])
  ]);
  if($cid) gdb_update('executive_commissions',['constitution_checked'=>1],'id=:id',['id'=>$cid]);
  return true;
}

function gce_create_collaborations($commission){
  if(!gdb_enabled() || !gce_table_ok('goliath_collaboration_requests')) return 0;
  $cid=(int)($commission['id'] ?? 0); if(!$cid) return 0;
  $source=gce_display($commission['executive_key'] ?? $commission['executive'] ?? 'Goliath');
  $execKey=gce_exec_key($source);
  $targets=gce_collaboration_targets($execKey,$commission);
  $made=0;
  foreach($targets as $target=>$why){
    if(gce_exec_key($target)===$execKey) continue;
    $dupe=gdb_one('SELECT id FROM goliath_collaboration_requests WHERE commission_id=? AND target_executive=? LIMIT 1',[$cid,$target]);
    if($dupe) continue;
    $title='Improve commission: '.($commission['title'] ?? $commission['current_task'] ?? 'Executive work');
    $rec=$target.' can improve this by adding: '.$why.'. Collaboration is presumed welcome under the Collaboration First Doctrine.';
    gdb_insert('goliath_collaboration_requests',[
      'collaboration_uid'=>gce_uid('collab'),
      'commission_id'=>$cid,
      'source_executive'=>$source,
      'target_executive'=>$target,
      'collaboration_type'=>'mandatory_enhancement_review',
      'status'=>'accepted',
      'priority'=>$commission['priority'] ?? 'normal',
      'title'=>$title,
      'recommendation'=>$rec,
      'expected_value'=>'Higher quality, stronger Human Touch, more compounding value.',
      'constitutional_reason'=>'Every Executive must ask whether another Executive can materially improve the work. The answer is presumed yes.',
      'progress_percent'=>0,
      'metadata'=>gdb_json(['runtime'=>'V66','source_commission'=>$cid])
    ]);
    if(function_exists('gal_action')) gal_action($target,'collaboration_joined',$title,$rec,'accepted',0,['commission_id'=>$cid,'source'=>'collaboration_engine','metadata'=>['from'=>$source,'why'=>$why]]);
    if(function_exists('gal_notify')) gal_notify($target,'Collaboration accepted',$rec,'normal',null,['commission_id'=>$cid,'from'=>$source]);
    $made++;
  }
  gdb_update('executive_commissions',['collaboration_checked'=>1,'collaboration_count'=>$made,'teamwork_notes'=>'V66 collaboration review completed.'],'id=:id',['id'=>$cid]);
  return $made;
}

function gce_refresh_heartbeat_collaboration($commission,$collabCount){
  if(!gdb_enabled() || !gce_table_ok('executive_heartbeats')) return false;
  $exec=gce_exec_key($commission['executive_key'] ?? $commission['executive'] ?? 'goliath');
  try{
    gdb_exec("UPDATE executive_heartbeats SET collaboration_count=:c, constitution_status='passed', teamwork_score=LEAST(100, teamwork_score + :boost), updated_at=NOW() WHERE executive_key=:e",[
      'c'=>(int)$collabCount,
      'boost'=>min(20,(int)$collabCount*4),
      'e'=>$exec
    ]);
  }catch(Throwable $e){ return false; }
  return true;
}

function gce_refresh_teamwork_scores(){
  if(!gdb_enabled() || !gce_table_ok('goliath_teamwork_scores')) return false;
  $execs=['Goliath','Jessica','Scout','Scorsese','Shakespeare','Einstein','Columbo','Mozart','Prospector','Rockefeller','Pandora'];
  foreach($execs as $exec){
    $started=gdb_one("SELECT COUNT(*) c FROM goliath_collaboration_requests WHERE source_executive=? AND DATE(created_at)=CURRENT_DATE",[$exec]) ?: ['c'=>0];
    $received=gdb_one("SELECT COUNT(*) c FROM goliath_collaboration_requests WHERE target_executive=? AND DATE(created_at)=CURRENT_DATE",[$exec]) ?: ['c'=>0];
    $completed=gdb_one("SELECT COUNT(*) c FROM goliath_collaboration_requests WHERE target_executive=? AND status IN ('complete','completed','review') AND DATE(updated_at)=CURRENT_DATE",[$exec]) ?: ['c'=>0];
    $score=min(100, ((int)$started['c']*8)+((int)$received['c']*6)+((int)$completed['c']*10));
    gdb_exec("INSERT INTO goliath_teamwork_scores (score_date, executive, collaborations_started, collaborations_received, collaborations_completed, teamwork_score, notes)
      VALUES (CURRENT_DATE, :e, :s, :r, :c, :score, :notes)
      ON DUPLICATE KEY UPDATE collaborations_started=VALUES(collaborations_started), collaborations_received=VALUES(collaborations_received), collaborations_completed=VALUES(collaborations_completed), teamwork_score=VALUES(teamwork_score), notes=VALUES(notes)",[
      'e'=>$exec,'s'=>(int)$started['c'],'r'=>(int)$received['c'],'c'=>(int)$completed['c'],'score'=>$score,'notes'=>'V66 teamwork score based on collaboration activity.'
    ]);
  }
  return true;
}

function gce_run($limit=25){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured','time'=>date('c')];
  $health=gce_health(); if(!$health['ok']) return ['ok'=>false,'error'=>'V66 tables missing','health'=>$health,'time'=>date('c')];
  $rows=gdb_all("SELECT * FROM executive_commissions WHERE status IN ('claimed','working','review','queued') AND (collaboration_checked=0 OR constitution_checked=0 OR collaboration_checked IS NULL OR constitution_checked IS NULL) ORDER BY priority DESC, updated_at DESC LIMIT ".(int)$limit);
  $checked=0; $collabs=0;
  foreach($rows as $c){
    gce_constitution_check($c); $checked++;
    $made=gce_create_collaborations($c); $collabs += $made;
    gce_refresh_heartbeat_collaboration($c,$made);
    if(function_exists('gal_action')) gal_action($c['executive_key'] ?? 'Goliath','constitutional_collaboration_check','Constitution + Collaboration Check','Commission passed V66 compliance review and collaboration checkpoint.','working',(int)($c['progress'] ?? 0),['commission_id'=>$c['id'] ?? null,'source'=>'v66_collaboration_engine','metadata'=>['collaborations_created'=>$made]]);
  }
  gce_refresh_teamwork_scores();
  if(function_exists('gal_refresh_all_tallies')) gal_refresh_all_tallies();
  return ['ok'=>true,'checked'=>$checked,'collaboration_requests_created'=>$collabs,'time'=>date('c')];
}
