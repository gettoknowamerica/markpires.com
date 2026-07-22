<?php
/**
 * V103.0 Universal Executive Runtime
 * One START command for the organization. Goliath observes, prioritizes, assigns, and keeps every executive moving.
 */
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??($_POST['key']??''); $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function u_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function u_one($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function u_all($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];} }
 function u_col($t,$c){$r=u_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function u_table($t){$r=u_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
 function u_ins($t,$row){$safe=[];foreach($row as $k=>$v){if(u_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function u_upd($t,$where,$params,$row){$safe=[];foreach($row as $k=>$v){if(u_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,$where,$params);}
 function u_slug($s){$s=strtolower(trim((string)$s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
 function u_event($type,$title,$details='',$from='goliath',$to=null,$mission=null,$priority=70,$meta=[]){
   return u_ins('goliath_mission_bus_events',['event_uid'=>u_uid('bus'),'mission_uid'=>$mission,'event_type'=>$type,'from_executive'=>$from,'to_executive'=>$to,'title'=>$title,'details'=>$details,'priority'=>$priority,'status'=>'new','metadata_json'=>json_encode($meta,JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);
 }
 function u_mission($title,$type,$source,$priority,$owner,$team,$packet,$goal,$next){
   $exists=u_one("SELECT mission_uid,id FROM goliath_missions WHERE title=? AND status NOT IN ('complete','completed','delivered','archived') LIMIT 1",[$title]);
   if($exists) return $exists['mission_uid'];
   $uid=u_uid('mission');
   u_ins('goliath_missions',['mission_uid'=>$uid,'title'=>$title,'mission_type'=>$type,'source'=>$source,'priority'=>$priority,'status'=>'proposed','owner_executive'=>$owner,'assigned_executives_json'=>json_encode($team,JSON_UNESCAPED_SLASHES),'mission_packet_json'=>json_encode($packet,JSON_UNESCAPED_SLASHES),'outcome_goal'=>$goal,'next_action'=>$next,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   foreach($team as $exec){
     u_ins('executive_mission_assignments',['mission_uid'=>$uid,'executive_key'=>$exec,'assignment_type'=>$type,'status'=>'assigned','instructions'=>$next,'requested_help_json'=>json_encode(['collaborators'=>array_values(array_diff($team,[$exec]))],JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);
   }
   u_event('mission_created',$title,$goal,'goliath',null,$uid,$priority,$packet);
   return $uid;
 }

 $metrics=['missions_created'=>0,'assignments_created'=>0,'top10_updated'=>0,'events_created'=>0,'executives_active'=>0];

 // 1. Promote executive initiatives into real missions.
 if(u_table('executive_initiatives')){
   $inits=u_all("SELECT * FROM executive_initiatives WHERE status='recommended' ORDER BY priority DESC, created_at ASC LIMIT 18");
   foreach($inits as $i){
     $exec=$i['executive_key']?:'goliath';
     $team=array_values(array_unique(['goliath',$exec]));
     $title=$i['title']?:'Executive initiative';
     $packet=json_decode($i['recommended_mission_packet_json']??'[]',true)?:['source'=>'initiative'];
     $uid=u_mission($title,'executive_initiative','executive_initiatives',(int)($i['priority']??80),$exec,$team,$packet,$i['expected_impact']??'Advance organization output.','Advance this initiative and report back to Goliath.');
     u_upd('executive_initiatives','id=:id',['id'=>$i['id']],['status'=>'promoted','updated_at'=>gdb_now()]);
     $metrics['missions_created']++;
   }
 }

 // 2. Convert Shakespeare packages into collaboration missions.
 if(u_table('shakespeare_campaign_packages')){
   $pkgs=u_all("SELECT * FROM shakespeare_campaign_packages WHERE status IN ('needs_enrichment','ready_for_review','draft') ORDER BY authority_score DESC, id DESC LIMIT 12");
   foreach($pkgs as $p){
     $title='Authority Package: '.($p['title']??'Campaign');
     $team=['goliath','shakespeare','sherlock','einstein','scorsese','jessica','pandora'];
     $packet=['package_id'=>$p['id'],'title'=>$p['title'],'slug'=>$p['slug'],'score'=>$p['authority_score'],'video_brief'=>json_decode($p['video_brief_json']??'[]',true),'visual_requests'=>json_decode($p['visual_requests_json']??'[]',true)];
     u_mission($title,'authority_package','shakespeare_campaign_packages',90,'shakespeare',$team,$packet,'Turn this content into a full authority campaign with verification, SEO, visuals, video, and follow-up.','Each executive completes their part of the campaign package.');
     $metrics['missions_created']++;
   }
 }

 // 3. Scorsese stalled render audit mission.
 if(u_table('scorsese_comfy_jobs')){
   $stalled=u_one("SELECT COUNT(*) c FROM scorsese_comfy_jobs WHERE status IN ('failed','error') OR (status='rendering' AND progress>=95)") ?: ['c'=>0];
   if((int)$stalled['c']>0){
     u_mission('Scorsese Render Reliability Audit','production_reliability','scorsese_comfy_jobs',98,'scorsese',['goliath','scorsese','einstein'],'{"stalled_or_failed_jobs":true}','Identify the exact workflow/path/encoder issue and restore reliable delivery.','Scorsese audits workflow choice; Einstein reviews system health; Goliath prioritizes fix.');
     $metrics['missions_created']++;
   }
 }

 // 4. Top 10 boards: clear and rebuild.
 if(u_table('executive_top10_boards')){
   try{gdb()->exec("DELETE FROM executive_top10_boards");}catch(Throwable $e){}
   $boards=[
    'shakespeare'=>u_table('shakespeare_campaign_packages')?u_all("SELECT id,title,authority_score score,status,scenario reason FROM shakespeare_campaign_packages ORDER BY authority_score DESC,id DESC LIMIT 10"):[],
    'scorsese'=>u_table('scorsese_comfy_jobs')?u_all("SELECT id,title,COALESCE(viral_score, priority, 75) score,status,COALESCE(error_message,'Video production item') reason FROM scorsese_comfy_jobs ORDER BY COALESCE(viral_score, priority, id) DESC LIMIT 10"):[],
    'scout'=>u_table('scout_intel_dossiers')?u_all("SELECT id,COALESCE(owner_name,property_address,CONCAT('Dossier #',id)) title,COALESCE(completion_score,lead_score,70) score,COALESCE(jessica_status,status,'ready') status,COALESCE(opportunity_type,'Revenue opportunity') reason FROM scout_intel_dossiers ORDER BY COALESCE(einstein_score,completion_score,lead_score,0) DESC,id DESC LIMIT 10"):[],
    'jessica'=>u_table('jessica_email_drafts')?u_all("SELECT id,COALESCE(subject,CONCAT('Draft #',id)) title,85 score,COALESCE(status,'draft') status,'Relationship follow-up' reason FROM jessica_email_drafts ORDER BY id DESC LIMIT 10"):[],
    'sherlock'=>u_table('shakespeare_campaign_packages')?u_all("SELECT id,CONCAT('Verify: ',title) title,(100-COALESCE(verification_score,50)) score,status,'Needs proof/citation review' reason FROM shakespeare_campaign_packages ORDER BY verification_score ASC,id DESC LIMIT 10"):[],
    'einstein'=>u_table('shakespeare_campaign_packages')?u_all("SELECT id,CONCAT('Optimize: ',title) title,(100-COALESCE(seo_score,60)) score,status,'SEO/AEO opportunity' reason FROM shakespeare_campaign_packages ORDER BY seo_score ASC,id DESC LIMIT 10"):[],
    'pandora'=>u_table('shakespeare_campaign_packages')?u_all("SELECT id,CONCAT('Trend angle: ',title) title,80 score,status,'Creative/timing opportunity' reason FROM shakespeare_campaign_packages ORDER BY id DESC LIMIT 10"):[],
    'mozart'=>u_table('shakespeare_campaign_packages')?u_all("SELECT id,CONCAT('Audio mood: ',title) title,75 score,status,'Music/voice direction needed' reason FROM shakespeare_campaign_packages ORDER BY id DESC LIMIT 10"):[]
   ];
   foreach($boards as $exec=>$rows){
     $rank=1;
     foreach($rows as $r){
       u_ins('executive_top10_boards',['board_uid'=>u_uid('top10'),'executive_key'=>$exec,'rank_no'=>$rank,'title'=>$r['title']??'Opportunity','score'=>(int)($r['score']??75),'status'=>$r['status']??'open','reason'=>$r['reason']??'High priority','source_table'=>'auto','source_id'=>(int)($r['id']??0),'direct_url'=>'/dashboard/goliath-organization-runtime.php','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
       $rank++; $metrics['top10_updated']++;
     }
   }
 }

 // 5. Runtime state and log.
 $active=u_all("SELECT executive_key,COUNT(*) c FROM executive_mission_assignments WHERE status IN ('assigned','working') GROUP BY executive_key");
 $metrics['executives_active']=count($active);
 u_ins('goliath_universal_runtime_logs',['run_uid'=>u_uid('run'),'status'=>'complete','summary'=>'Universal runtime observed initiatives, packages, stalled renders, rebuilt Top 10 boards, and assigned shared missions.','metrics_json'=>json_encode($metrics,JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);
 $state=u_one("SELECT id FROM goliath_runtime_state WHERE state_key='universal_runtime' LIMIT 1");
 $stateRow=['state_value'=>'running','metadata_json'=>json_encode(['last_run'=>date('c'),'metrics'=>$metrics],JSON_UNESCAPED_SLASHES),'updated_at'=>gdb_now()];
 if($state)u_upd('goliath_runtime_state','id=:id',['id'=>$state['id']],$stateRow); else {$stateRow['state_key']='universal_runtime';u_ins('goliath_runtime_state',$stateRow);}

 echo json_encode(['ok'=>true,'version'=>'V103.0 Universal Executive Runtime','metrics'=>$metrics,'next'=>'Leave local starter running. Open /dashboard/goliath-organization-runtime.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V103.0 Universal Executive Runtime','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>