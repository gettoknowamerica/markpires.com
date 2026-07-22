<?php
/**
 * Goliath V71.1 — Einstein Asset Compounding Engine SAFE PATCH
 * Fixes 500s by using safe table/column checks and catching DB exceptions.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-action-ledger.php';

if(!function_exists('gac_substr')){
  function gac_substr($s,$start,$len){
    $s=(string)$s;
    return function_exists('mb_substr') ? mb_substr($s,$start,$len) : substr($s,$start,$len);
  }
}
function gac_table($table){
  try{
    if(!gdb_enabled()) return false;
    $r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
    return ((int)($r['c']??0))>0;
  }catch(Throwable $e){ return false; }
}
function gac_columns($table){
  static $cache=[];
  if(isset($cache[$table])) return $cache[$table];
  if(!gac_table($table)) return $cache[$table]=[];
  try{
    $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table]);
    return $cache[$table]=array_map(fn($r)=>$r['column_name'],$rows);
  }catch(Throwable $e){ return $cache[$table]=[]; }
}
function gac_insert_safe($table,$row){
  $cols=gac_columns($table);
  if(!$cols) return 0;
  $filtered=[];
  foreach($row as $k=>$v){ if(in_array($k,$cols,true)) $filtered[$k]=$v; }
  if(!$filtered) return 0;
  try{ return gdb_insert($table,$filtered); }catch(Throwable $e){ error_log("V71 insert $table failed: ".$e->getMessage()); return 0; }
}
function gac_update_safe($table,$row,$where,$params=[]){
  $cols=gac_columns($table);
  if(!$cols) return false;
  $filtered=[];
  foreach($row as $k=>$v){ if(in_array($k,$cols,true)) $filtered[$k]=$v; }
  if(!$filtered) return false;
  try{ return gdb_update($table,$filtered,$where,$params); }catch(Throwable $e){ error_log("V71 update $table failed: ".$e->getMessage()); return false; }
}
function gac_health(){
  $tables=['goliath_asset_compounding_queue','goliath_asset_compounding_actions','goliath_worker_completions','goliath_review_queue','executive_commissions','executive_heartbeats','goliath_notifications'];
  $out=[]; foreach($tables as $t){$out[$t]=gac_table($t);}
  $counts=[];
  if(gdb_enabled()){
    foreach(['goliath_asset_compounding_queue'=>'queue','goliath_asset_compounding_actions'=>'actions'] as $t=>$k){
      try{$counts[$k]=gac_table($t)?(int)((gdb_one("SELECT COUNT(*) c FROM `$t`")?:['c'=>0])['c']):0;}catch(Throwable $e){$counts[$k]=null;}
    }
    try{$counts['completed_assets']=gac_table('goliath_worker_completions')?(int)((gdb_one("SELECT COUNT(*) c FROM goliath_worker_completions")?:['c'=>0])['c']):0;}catch(Throwable $e){$counts['completed_assets']=null;}
  }
  return ['ok'=>gdb_enabled()&&!in_array(false,$out,true),'configured'=>gdb_enabled(),'tables'=>$out,'counts'=>$counts,'time'=>date('c')];
}
function gac_asset_type($title,$output){
  $txt=strtolower($title.' '.$output);
  if(str_contains($txt,'video')||str_contains($txt,'short')||str_contains($txt,'media')) return 'video';
  if(str_contains($txt,'blog')||str_contains($txt,'article')) return 'blog';
  if(str_contains($txt,'lead')||str_contains($txt,'phone')||str_contains($txt,'seller')||str_contains($txt,'buyer')) return 'lead_intelligence';
  if(str_contains($txt,'song')||str_contains($txt,'music')) return 'music';
  return 'content';
}
function gac_seed_from_completions($limit=20){
  if(!gdb_enabled()||!gac_table('goliath_worker_completions')||!gac_table('goliath_asset_compounding_queue')) return 0;
  try{
    $rows=gdb_all("SELECT * FROM goliath_worker_completions wc WHERE NOT EXISTS (SELECT 1 FROM goliath_asset_compounding_queue q WHERE q.source_completion_id=wc.id) ORDER BY wc.created_at DESC LIMIT ".(int)$limit);
  }catch(Throwable $e){ error_log('V71 seed select failed: '.$e->getMessage()); return 0; }
  $made=0;
  foreach($rows as $r){
    $title=$r['title']??'Completed Executive Asset';
    $output=$r['output']??'';
    $type=gac_asset_type($title,$output);
    $id=gac_insert_safe('goliath_asset_compounding_queue',[
      'compounding_uid'=>gdb_uid('compound'),
      'source_completion_id'=>$r['id']??null,
      'source_commission_id'=>$r['commission_id']??null,
      'source_executive'=>$r['executive']??null,
      'asset_type'=>$type,
      'asset_title'=>$title,
      'asset_summary'=>gac_substr(strip_tags($output),0,1200),
      'status'=>'queued',
      'priority'=>($type==='lead_intelligence'?95:85),
      'current_step'=>'Einstein intake: identify SEO/AEO/backlink/repurpose opportunities',
      'recommended_next_action'=>'Create compounding actions and assign supporting commissions.',
      'metadata'=>gdb_json(['runtime'=>'V71.1','principle'=>'Publication is birth, not completion.'])
    ]);
    if($id) $made++;
  }
  return $made;
}
function gac_action_templates($assetType,$title){
  $base=[
    ['seo','SEO refresh plan','Optimize title, description, internal links, entities, and keyword targets.'],
    ['aeo','AEO answer package','Create FAQ and direct-answer snippets for AI search and Google AI Overviews.'],
    ['backlink','Backlink opportunity list','Find authority sites, community pages, podcasts, directories, and partners that may link or mention this asset.'],
    ['repurpose','Repurpose package','Turn this asset into social posts, email copy, shorts, newsletter blocks, and follow-up content.'],
    ['refresh','Evergreen refresh schedule','Schedule future updates so the asset never dies after publication.']
  ];
  if($assetType==='lead_intelligence') array_unshift($base,['human_touch','Jessica relationship follow-up','Create warm follow-up, call priority, and relationship timeline action for Mark.']);
  if($assetType==='video') $base[]=['youtube','YouTube optimization','Prepare title, thumbnail direction, chapters, description, tags, shorts plan, and pinned comment.'];
  if($assetType==='music') $base[]=['music_distribution','Music content expansion','Create lyrics/video/shorts/storytelling strategy and Scorsese handoff.'];
  return $base;
}
function gac_expand_queue($limit=20){
  if(!gdb_enabled()||!gac_table('goliath_asset_compounding_queue')||!gac_table('goliath_asset_compounding_actions')) return 0;
  try{$rows=gdb_all("SELECT * FROM goliath_asset_compounding_queue WHERE status IN ('queued','working') ORDER BY priority DESC, updated_at ASC LIMIT ".(int)$limit);}
  catch(Throwable $e){ error_log('V71 expand select failed: '.$e->getMessage()); return 0; }
  $made=0;
  foreach($rows as $q){
    try{$existing=(int)((gdb_one('SELECT COUNT(*) c FROM goliath_asset_compounding_actions WHERE compounding_id=?',[(int)$q['id']])?:['c'=>0])['c']);}
    catch(Throwable $e){$existing=0;}
    if($existing===0){
      foreach(gac_action_templates($q['asset_type']??'content',$q['asset_title']??'Completed Asset') as $a){
        $id=gac_insert_safe('goliath_asset_compounding_actions',[
          'action_uid'=>gdb_uid('ca'),
          'compounding_id'=>$q['id'],
          'action_type'=>$a[0],
          'action_title'=>$a[1].': '.($q['asset_title']??'Completed Asset'),
          'action_summary'=>$a[2],
          'status'=>'queued',
          'priority'=>$q['priority']??80,
          'assigned_executive'=>($a[0]==='human_touch'?'Jessica':'Einstein'),
          'metadata'=>gdb_json(['runtime'=>'V71.1','asset_type'=>$q['asset_type']??'content'])
        ]);
        if($id) $made++;
      }
    }
    $counts=[];
    try{
      $counts=gdb_one("SELECT SUM(action_type='seo') seo, SUM(action_type='aeo') aeo, SUM(action_type='backlink') backlink, SUM(action_type='repurpose') repurpose, SUM(action_type='refresh') refresh FROM goliath_asset_compounding_actions WHERE compounding_id=?",[(int)$q['id']])?:[];
    }catch(Throwable $e){}
    gac_update_safe('goliath_asset_compounding_queue',[
      'status'=>'working',
      'seo_actions'=>(int)($counts['seo']??0),
      'aeo_actions'=>(int)($counts['aeo']??0),
      'backlink_actions'=>(int)($counts['backlink']??0),
      'repurpose_actions'=>(int)($counts['repurpose']??0),
      'refresh_actions'=>(int)($counts['refresh']??0),
      'current_step'=>'Compounding actions created and queued for Einstein.'
    ],'id=:id',['id'=>(int)$q['id']]);
    if(function_exists('gal_action')) gal_action('Einstein','asset_compounding_started','Compounding started: '.($q['asset_title']??'Completed Asset'),'Einstein created post-delivery optimization actions so this asset continues to grow.','working',25,['asset_id'=>$q['id']??null,'source'=>'v71_asset_compounding']);
  }
  return $made;
}
function gac_create_einstein_commissions($limit=15){
  if(!gdb_enabled()||!gac_table('goliath_asset_compounding_actions')||!gac_table('executive_commissions')) return 0;
  try{$rows=gdb_all("SELECT a.*, q.source_commission_id FROM goliath_asset_compounding_actions a LEFT JOIN goliath_asset_compounding_queue q ON q.id=a.compounding_id WHERE a.status='queued' ORDER BY a.priority DESC, a.created_at ASC LIMIT ".(int)$limit);}
  catch(Throwable $e){ error_log('V71 commission select failed: '.$e->getMessage()); return 0; }
  $made=0;
  foreach($rows as $a){
    try{$dupe=gdb_one('SELECT id FROM executive_commissions WHERE commission_type=? AND title=? LIMIT 1',['asset_compounding_action',$a['action_title']]);}
    catch(Throwable $e){$dupe=null;}
    if($dupe){gac_update_safe('goliath_asset_compounding_actions',['status'=>'assigned'],'id=:id',['id'=>(int)$a['id']]); continue;}
    $id=gac_insert_safe('executive_commissions',[
      'commission_uid'=>gdb_uid('com'),
      'executive_key'=>strtolower($a['assigned_executive']?:'Einstein'),
      'title'=>$a['action_title'],
      'commission_type'=>'asset_compounding_action',
      'status'=>'queued',
      'priority'=>(int)$a['priority'],
      'progress'=>0,
      'current_task'=>$a['action_summary'],
      'metadata'=>gdb_json(['source'=>'v71_asset_compounding','compounding_action_id'=>$a['id'],'compounding_id'=>$a['compounding_id'],'source_commission_id'=>$a['source_commission_id']??null])
    ]);
    gac_update_safe('goliath_asset_compounding_actions',['status'=>'assigned'],'id=:id',['id'=>(int)$a['id']]);
    if($id) $made++;
  }
  return $made;
}
function gac_run(){
  try{
    $h=gac_health();
    if(!$h['ok']) return ['ok'=>false,'error'=>'V71 tables missing or DB not configured','health'=>$h,'time'=>date('c')];
    $seeded=gac_seed_from_completions();
    $actions=gac_expand_queue();
    $commissions=gac_create_einstein_commissions();
    if(gac_table('executive_heartbeats')){
      gac_update_safe('executive_heartbeats',[
        'current_task'=>'Compounding completed assets through SEO/AEO/backlinks',
        'current_step'=>'Asset compounding active',
        'plugin_in_use'=>'Einstein V71.1',
        'updated_at'=>gdb_now(),
        'last_heartbeat_at'=>gdb_now()
      ],'executive_key=:e',['e'=>'einstein']);
    }
    return ['ok'=>true,'assets_seeded'=>$seeded,'actions_created'=>$actions,'commissions_created'=>$commissions,'time'=>date('c')];
  }catch(Throwable $e){
    http_response_code(200);
    return ['ok'=>false,'error'=>'V71 safe runner caught exception','detail'=>$e->getMessage(),'time'=>date('c')];
  }
}
?>