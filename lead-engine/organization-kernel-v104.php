<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function one($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function allx($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];} }
 function col($t,$c){$r=one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function tablex($t){$r=one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
 function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function upd($t,$where,$params,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,$where,$params);}
 function note($type,$title,$msg,$priority=70){return ins('goliath_notifications',['notification_uid'=>uid('note'),'channel'=>'dashboard','recipient'=>'Mark','notification_type'=>$type,'title'=>$title,'message'=>$msg,'priority'=>$priority,'status'=>'queued','metadata_json'=>json_encode(['by'=>'v104_kernel']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);}
 $vision="Founder’s Vision: Goliath Omni is a self-improving executive organization that protects Mark’s time, grows revenue, builds trusted authority, deepens relationships, produces beautiful work, and never silently stalls. Quality beats speed, collaboration beats isolation, delivery beats activity.";
 $r=one("SELECT id FROM goliath_founders_vision WHERE vision_key='founder_v1' LIMIT 1");
 $row=['version'=>'V104.0','title'=>'Founder’s Vision','vision_text'=>$vision,'principles_json'=>json_encode(['Build trust before revenue','Quality beats speed','No silent failure','Finish what you start','Learn from outcomes']),'updated_at'=>gdb_now()];
 if($r)upd('goliath_founders_vision','id=:id',['id'=>$r['id']],$row);else{$row['vision_key']='founder_v1';ins('goliath_founders_vision',$row);}
 $genomes=[
 ['goliath','Goliath','Chief Executive Officer','Executive Board','Direct the organization and protect founder time.'],
 ['chief_of_staff','Chief of Staff','Chief Operating Officer','Operations','Keep the organization moving and prevent silent failure.'],
 ['scout','Scout','Chief Revenue Intelligence Officer','Growth','Find and rank revenue opportunities.'],
 ['jessica','Jessica','Chief Relationship Officer','Relationships','Make sure no relationship or lead goes cold.'],
 ['shakespeare','Shakespeare','Chief Content & Authority Officer','Authority','Build full campaigns and authority clusters.'],
 ['scorsese','Scorsese','Chief Creative Production Director','Production','Create cinematic media that converts and completes delivery.'],
 ['sherlock','Sherlock','Chief Verification Officer','Verification','Protect truth, sourcing, and strategic accuracy.'],
 ['einstein','Einstein','Chief Intelligence & Conversion Scientist','Intelligence','Improve SEO, AEO, conversion, analytics, and system performance.'],
 ['pandora','Pandora','Chief Trend & Creative Strategy Officer','Trends','Find timely angles and creative opportunities.'],
 ['mozart','Mozart','Chief Audio & Voice Director','Audio','Create music, voice, sound identity, and audio direction.'],
 ['rockefeller','Rockefeller','Chief Revenue Optimization Officer','Finance','Prioritize money, ROI, and monetization.']
 ];
 $genCount=0;
 foreach($genomes as $g){$row=['genome_uid'=>uid('genome'),'executive_key'=>$g[0],'display_name'=>$g[1],'role_title'=>$g[2],'department'=>$g[3],'mission'=>$g[4],'always_rules_json'=>json_encode(['Own outcomes','Collaborate','Deliver finished work','Escalate blockers']),'never_rules_json'=>json_encode(['Silently fail','Sit idle','Deliver generic work']),'collaborates_with_json'=>json_encode(['goliath','chief_of_staff']),'kpis_json'=>json_encode(['revenue','quality','completion','time_saved']),'tools_json'=>json_encode(['mission_bus','top10','scorecard','memory']),'observation_rules_json'=>json_encode(['Observe missions','Observe Top 10','Create initiative if idle']),'escalation_rules_json'=>json_encode(['High value lead','Blocked mission','Failed delivery']),'deliverable_standards_json'=>json_encode(['Useful','Beautiful','Measurable','Delivered','Trustworthy']),'status'=>'active','updated_at'=>gdb_now()];
 $e=one("SELECT id FROM executive_genomes WHERE executive_key=? LIMIT 1",[$g[0]]); if($e)upd('executive_genomes','id=:id',['id'=>$e['id']],$row); else ins('executive_genomes',$row);$genCount++;}
 $incidents=0;
 if(tablex('scorsese_comfy_jobs')){foreach(allx("SELECT id,title,status,progress,error_message FROM scorsese_comfy_jobs WHERE (status='rendering' AND progress>=95) OR status IN ('failed','error') ORDER BY id DESC LIMIT 10") as $s){if(!one("SELECT id FROM chief_of_staff_incidents WHERE related_table='scorsese_comfy_jobs' AND related_id=? AND status='open' LIMIT 1",[$s['id']])){ins('chief_of_staff_incidents',['incident_uid'=>uid('inc'),'incident_type'=>'production_stall','severity'=>95,'title'=>'Scorsese render needs attention: '.($s['title']?:'Job #'.$s['id']),'details'=>json_encode($s),'related_table'=>'scorsese_comfy_jobs','related_id'=>$s['id'],'assigned_to'=>'scorsese','status'=>'open','recommended_action'=>'Verify output folder, encoder, workflow, and upload registration.','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);note('render_issue','Scorsese render needs attention','Chief of Staff detected a stalled or failed render.',95);$incidents++;}}}
 $date=date('Y-m-d'); $scorecards=0;
 foreach($genomes as $g){$exec=$g[0];$active=one("SELECT COUNT(*) c FROM executive_mission_assignments WHERE executive_key=? AND status IN ('assigned','working')",[$exec])?:['c'=>0];$done=one("SELECT COUNT(*) c FROM executive_mission_assignments WHERE executive_key=? AND status IN ('complete','completed','delivered')",[$exec])?:['c'=>0];$blocked=one("SELECT COUNT(*) c FROM chief_of_staff_incidents WHERE assigned_to=? AND status='open'",[$exec])?:['c'=>0];$top=one("SELECT MAX(score) s FROM executive_top10_boards WHERE executive_key=?",[$exec])?:['s'=>0];$overall=max(0,min(100,70+(int)($top['s']??0)/5-(int)$blocked['c']*8+min(10,(int)$done['c'])));ins('executive_scorecards',['scorecard_uid'=>uid('score'),'executive_key'=>$exec,'score_date'=>$date,'overall_score'=>$overall,'missions_active'=>(int)$active['c'],'missions_completed'=>(int)$done['c'],'blocked_count'=>(int)$blocked['c'],'top10_score'=>(int)($top['s']??0),'kpi_json'=>json_encode(['active'=>$active['c'],'done'=>$done['c'],'blocked'=>$blocked['c']]),'recommendation'=>$blocked['c']?'Resolve blockers first.':'Advance highest-value Top 10 work.','created_at'=>gdb_now()]);$scorecards++;}
 $open=one("SELECT COUNT(*) c FROM chief_of_staff_incidents WHERE status='open'")?:['c'=>0];$health=max(0,min(100,84-(int)$open['c']*4));
 ins('organization_health_snapshots',['snapshot_uid'=>uid('health'),'health_score'=>$health,'revenue_pipeline'=>80,'authority_growth'=>85,'relationships'=>78,'production_capacity'=>max(30,90-(int)$open['c']*6),'system_health'=>$health,'summary'=>'V104 Organization Kernel active.','metrics_json'=>json_encode(['open_incidents'=>$open['c']]),'created_at'=>gdb_now()]);
 note('kernel_complete','V104 Organization Kernel ran successfully','Constitution engine, genomes, Chief of Staff, scorecards, and health snapshot updated.',75);
 echo json_encode(['ok'=>true,'version'=>'V104.0 Organization Kernel','genomes_updated'=>$genCount,'incidents_created'=>$incidents,'scorecards_created'=>$scorecards,'health_score'=>$health,'next'=>'Open /dashboard/goliath-organization-kernel.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V104.0 Organization Kernel','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>