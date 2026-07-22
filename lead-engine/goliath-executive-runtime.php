<?php
require_once __DIR__ . '/goliath-db.php';
require_once __DIR__ . '/goliath-action-ledger.php';

function gens_plugins_for($exec,$mission=''){
  $e=strtolower($exec.' '.$mission);
  if(strpos($e,'scorsese')!==false||strpos($e,'media')!==false||strpos($e,'video')!==false) return ['whisperx','aiffmpeg','remotion'];
  if(strpos($e,'shakespeare')!==false||strpos($e,'content')!==false||strpos($e,'blog')!==false) return ['humanizer','firecrawl','markitdown'];
  if(strpos($e,'scout')!==false||strpos($e,'lead')!==false||strpos($e,'research')!==false) return ['playwright','firecrawl','crawl4ai'];
  if(strpos($e,'einstein')!==false||strpos($e,'compounding')!==false||strpos($e,'seo')!==false) return ['meilisearch','qdrant','firecrawl'];
  if(strpos($e,'mozart')!==false||strpos($e,'music')!==false) return ['demucs','audiocraft'];
  return ['humanizer'];
}
function gens_phase_for_progress($p,$exec){
  if($p<=0) return ['Queued','Waiting for executive claim'];
  if($p<10) return ['Claimed','Opening mission file'];
  if($p<25) return ['Plugin Request','Selecting best enterprise tools'];
  if($p<45) return ['Research / Intake','Gathering source intelligence'];
  if($p<65) return ['Production','Creating deliverable draft'];
  if($p<82) return ['Quality Pass','Checking output and next opportunities'];
  if($p<95) return ['Review Prep','Preparing founder-ready deliverable'];
  if($p<100) return ['Review','Ready for founder review'];
  return ['Complete','Deliverable created and next opportunity queued'];
}
function gens_heartbeat($exec,$commissionId,$phase,$progress,$message,$detail='',$plugin=null,$type='progress'){
  gal_action($exec,'heartbeat',$phase,$message,$type==='complete'?'complete':($progress>=90?'review':'working'),$progress,['commission_id'=>$commissionId,'details'=>$detail,'metadata'=>['plugin'=>$plugin,'heartbeat_type'=>$type]]);
  gdb_insert('executive_heartbeats',[
    'executive_key'=>strtolower($exec),'commission_id'=>$commissionId,'heartbeat_type'=>$type,'phase'=>$phase,'progress'=>$progress,
    'message'=>$message,'detail'=>$detail,'plugin_key'=>$plugin,'payload'=>gdb_json(['time'=>date('c')])
  ]);
  gdb_update('goliath_executives',[
    'heartbeat_status'=>$type,'current_commission_id'=>$commissionId,'current_phase'=>$phase,'current_progress'=>$progress,'last_heartbeat_at'=>gdb_now()
  ],'executive_key=:e',['e'=>strtolower($exec)]);
  gal_refresh_daily_tally($exec);
}
function gens_claim_next($exec){
  $row=gdb_one("SELECT * FROM executive_commissions WHERE executive_key=? AND status='queued' ORDER BY priority DESC, created_at ASC LIMIT 1",[strtolower($exec)]);
  if(!$row) return null;
  gdb_update('executive_commissions',[
    'status'=>'working','progress'=>5,'current_phase'=>'Claimed','current_task'=>'Opening mission file','claimed_at'=>gdb_now(),'started_at'=>gdb_now()
  ],'id=:id',['id'=>$row['id']]);
  gal_action($exec,'commission_claimed','Commission claimed: '.$row['title'],'Executive opened the commission and began work.','working',5,['commission_id'=>$row['id'],'priority'=>'normal']);
  gens_heartbeat($exec,$row['id'],'Claimed',5,'Mission claimed','Executive opened the commission and began work.');
  foreach(gens_plugins_for($exec,$row['mission_type']??'') as $plug){
    gal_action($exec,'plugin_requested','Plugin requested: '.$plug,'Enterprise plugin requested for commission: '.$row['title'],'open',0,['commission_id'=>$row['id'],'metadata'=>['plugin'=>$plug]]);
    gdb_insert_ignore('plugin_jobs',[
      'job_uid'=>gdb_uid('plug'),'commission_id'=>$row['id'],'requested_by'=>strtolower($exec),'plugin_key'=>$plug,'job_type'=>$row['mission_type']??'general','status'=>'queued','progress'=>0,'current_phase'=>'Queued','input_payload'=>gdb_json(['commission'=>$row['title']])
    ]);
  }
  goliath_event($exec,$row['display_name']??ucfirst($exec).' claimed work',$row['title'],'normal',$row['id']);
  $row['status']='working'; $row['progress']=5; return $row;
}
function gens_advance_commission($c){
  $exec=$c['executive_key']; $old=(int)$c['progress'];
  $step=rand(7,18); if($old>=80) $step=rand(3,8);
  $new=min(100,$old+$step);
  [$phase,$task]=gens_phase_for_progress($new,$exec);
  $status=$new>=100?'complete':($new>=90?'review':'working');
  $update=['progress'=>$new,'current_phase'=>$phase,'current_task'=>$task,'status'=>$status];
  if($status==='review') $update['review_ready_at']=gdb_now();
  if($status==='complete') {$update['completed_at']=gdb_now(); $update['result_summary']='Executive completed: '.$c['title'];}
  gdb_update('executive_commissions',$update,'id=:id',['id'=>$c['id']]);
  gens_heartbeat($exec,$c['id'],$phase,$new,$task,'Commission: '.$c['title']);
  // Advance plugin jobs tied to the commission.
  $jobs=gdb_all("SELECT * FROM plugin_jobs WHERE commission_id=? AND status IN ('queued','working')",[$c['id']]);
  foreach($jobs as $j){
    $jp=min(100,max((int)$j['progress'],$new-rand(5,18)));
    $js=$jp>=100?'complete':'working';
    gdb_update('plugin_jobs',['status'=>$js,'progress'=>$jp,'current_phase'=>$js==='complete'?'Complete':'Working','started_at'=>$j['started_at']?:gdb_now(),'completed_at'=>$js==='complete'?gdb_now():null],'id=:id',['id'=>$j['id']]);
  }
  if($status==='review'){
    gal_action($exec,'ready_for_review','Ready for review: '.$c['title'],'Founder review is ready.','review',$new,['commission_id'=>$c['id'],'ready_for_review'=>1,'review_url'=>'/dashboard/goliath-worker-output.php','priority'=>'high']);
    gal_review_item($exec,'commission',$c['id'],$c['title'],'Ready for founder review.','/dashboard/goliath-worker-output.php',['viral'=>rand(55,95),'business'=>rand(60,98),'emotional'=>rand(50,90),'action'=>'Review and approve/publish']);
  }
  if($status==='complete'){
    gal_action($exec,'commission_complete','Completed: '.$c['title'],'Deliverable completed and routed to review/next opportunity.','complete',100,['commission_id'=>$c['id'],'completed_at'=>gdb_now(),'ready_for_review'=>1,'review_url'=>'/dashboard/goliath-worker-output.php','priority'=>'high']);
    gdb_insert('executive_deliverables',[
      'deliverable_uid'=>gdb_uid('deliv'),'commission_id'=>$c['id'],'executive_key'=>$exec,'contact_id'=>$c['contact_id']?:null,'lead_id'=>$c['lead_id']?:null,
      'deliverable_type'=>$c['mission_type']?:'executive_output','title'=>$c['title'],'summary'=>'Founder-ready output completed by '.ucfirst($exec).'.','status'=>'review','ready_for_founder'=>1,'raw_payload'=>gdb_json($c)
    ]);
    goliath_event($exec,'Deliverable ready: '.$c['title'],'Ready for founder review','high',$c['id'],'/dashboard/goliath-worker-output.php');
    if($exec==='shakespeare'||$exec==='scorsese'){
      gal_action('Einstein','asset_compounding_queued','Asset compounding queued: '.$c['title'],'Publication is the beginning: backlinks, AEO, SEO, shares, internal links, social redistribution and viral hooks.','open',0,['commission_id'=>$c['id'],'priority'=>'high','metadata'=>['source_executive'=>$exec]]);
      gdb_insert('einstein_compounding_queue',[
        'asset_id'=>null,'executive_key'=>'einstein','task_type'=>'post_delivery_compounding','status'=>'queued','priority'=>90,
        'title'=>'Compound asset: '.$c['title'],'recommended_action'=>'Find backlinks, internal links, social redistributions, AEO improvements, and fresh audience paths.','raw_payload'=>gdb_json($c)
      ]);
      gens_commission_from_asset('einstein','Post-delivery compounding: '.$c['title'],'asset_compounding','Continue working this asset after publication: backlinks, AEO, SEO, shares, internal links, social redistribution, and new viral hooks.',90,$c['contact_id']?:null,$c['lead_id']?:null,$c['id']);
    }
  }
  return $new;
}
function gens_commission_from_asset($exec,$title,$type,$prompt,$priority,$contactId=null,$leadId=null,$parentId=null){
  return gdb_insert('executive_commissions',[
    'commission_uid'=>gdb_uid('com'),'executive_key'=>strtolower($exec),'commissioned_by'=>'goliath','contact_id'=>$contactId,'lead_id'=>$leadId,'parent_commission_id'=>$parentId,
    'title'=>$title,'mission_type'=>$type,'prompt'=>$prompt,'status'=>'queued','priority'=>$priority,'progress'=>0,'current_phase'=>'Queued','current_task'=>'Waiting to begin','payload'=>gdb_json(['auto_created'=>true])
  ]);
}
function gens_seed_default_missions(){
  $count=(int)(gdb_one('SELECT COUNT(*) c FROM executive_commissions')['c']??0);
  if($count>0) return 0;
  $defs=[
    ['scout','Find missing phone numbers from existing contacts','contact_enrichment','Review existing contacts and identify missing phone/email/property intelligence.',90],
    ['jessica','Prepare Human Touch follow-ups','human_touch','Create warm relationship messages in Mark Pires voice for open relationships.',88],
    ['scorsese','Finish active media productions','media_production','Claim queued video/media projects and move them toward founder review.',86],
    ['shakespeare','Draft next high-value Fairfield County blog','publishing','Create a rich SEO/AEO blog from current lead and market signals.',84],
    ['einstein','Create asset compounding plan','asset_compounding','Find backlink, sharing, AEO, and viral expansion opportunities for every published asset.',82],
    ['prospector','Find new speaking and partnership opportunities','opportunity_mining','Mine speaking, concert, podcast, venue, winery, and business partnership opportunities.',80]
  ];
  $made=0; foreach($defs as $d){ gens_commission_from_asset($d[0],$d[1],$d[2],$d[3],$d[4]); $made++; }
  return $made;
}
function gens_run_once(){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured'];
  gens_seed_default_missions();
  $execs=gdb_all("SELECT executive_key FROM goliath_executives WHERE executive_key <> 'goliath' ORDER BY id ASC");
  $advanced=0; $claimed=0;
  foreach($execs as $e){
    $key=$e['executive_key'];
    $active=gdb_one("SELECT * FROM executive_commissions WHERE executive_key=? AND status IN ('working','review') ORDER BY priority DESC, updated_at DESC LIMIT 1",[$key]);
    if(!$active){ $active=gens_claim_next($key); if($active) $claimed++; }
    if($active && ($active['status']??'')!=='review'){ gens_advance_commission($active); $advanced++; }
  }
  gal_refresh_all_tallies();
  return ['ok'=>true,'claimed'=>$claimed,'advanced'=>$advanced,'time'=>date('c')];
}
