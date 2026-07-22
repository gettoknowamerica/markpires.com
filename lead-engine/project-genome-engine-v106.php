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
 function project($title,$type,$unit,$priority,$why,$genome,$seed=[]){
   $p=one("SELECT * FROM goliath_projects WHERE title=? AND status NOT IN ('archived','complete','completed') LIMIT 1",[$title]);
   $uid=$p['project_uid']??uid('project');
   $health=(int)($genome['health_score']??70);
   $row=['project_uid'=>$uid,'title'=>$title,'project_type'=>$type,'business_unit'=>$unit,'status'=>'active','owner_executive'=>'goliath','priority'=>$priority,'health_score'=>$health,'revenue_potential'=>(float)($genome['revenue_potential']??0),'authority_score'=>(int)($genome['authority_score']??50),'media_score'=>(int)($genome['media_score']??20),'promotion_score'=>(int)($genome['promotion_score']??20),'sales_score'=>(int)($genome['sales_score']??40),'analytics_score'=>(int)($genome['analytics_score']??30),'current_phase'=>$genome['phase']??'research','genome_json'=>json_encode($genome,JSON_UNESCAPED_SLASHES),'why_text'=>$why,'next_action'=>$genome['next_action']??'Advance project departments and create missing deliverables.','updated_at'=>gdb_now()];
   if($p)upd('goliath_projects','id=:id',['id'=>$p['id']],$row); else {$row['created_at']=gdb_now();ins('goliath_projects',$row);}
   $deps=[
    ['research','einstein','working','Research/SEO opportunity brief'],
    ['verification','sherlock','waiting','Facts, sources, risk check'],
    ['authority','shakespeare','waiting','Authority package, FAQ, schema, internal links'],
    ['media','scorsese','waiting','Video, shorts, thumbnails, visual package'],
    ['relationship','jessica','waiting','Email, CRM, follow-up, promotion'],
    ['revenue','rockefeller','waiting','Revenue forecast and ROI'],
    ['operations','chief_of_staff','working','Monitor blockers and completion']
   ];
   foreach($deps as $d){ if(!one("SELECT id FROM goliath_project_departments WHERE project_uid=? AND department_key=? LIMIT 1",[$uid,$d[0]])) ins('goliath_project_departments',['project_uid'=>$uid,'department_key'=>$d[0],'executive_key'=>$d[1],'status'=>$d[2],'score'=>($d[2]=='working'?65:25),'summary'=>$d[3],'deliverables_json'=>json_encode([$d[3]],JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);}
   foreach(['Research Brief'=>'einstein','Authority Page'=>'shakespeare','FAQ + Schema'=>'shakespeare','Video Brief'=>'scorsese','Shorts/Reels Package'=>'scorsese','Email Follow-up'=>'jessica','Revenue Forecast'=>'rockefeller'] as $del=>$exec){
     if(!one("SELECT id FROM goliath_project_deliverables WHERE project_uid=? AND title=? LIMIT 1",[$uid,$del])) ins('goliath_project_deliverables',['project_uid'=>$uid,'executive_key'=>$exec,'deliverable_type'=>strtolower(str_replace(' ','_',$del)),'title'=>$del,'status'=>'needed','score'=>0,'metadata_json'=>json_encode($seed,JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);
   }
   if(!one("SELECT id FROM goliath_project_timeline WHERE project_uid=? AND title='Project genome created' LIMIT 1",[$uid])) ins('goliath_project_timeline',['project_uid'=>$uid,'executive_key'=>'goliath','event_type'=>'project_created','title'=>'Project genome created','details'=>$why,'created_at'=>gdb_now()]);
   return $uid;
 }
 $created=[];

 // Create projects from Shakespeare packages.
 if(tablex('shakespeare_campaign_packages')){
   foreach(allx("SELECT id,title,slug,authority_score,town,scenario,status FROM shakespeare_campaign_packages ORDER BY authority_score DESC,id DESC LIMIT 8") as $p){
     $title='Authority Project: '.$p['title'];
     $gen=['source'=>'shakespeare_campaign_packages','source_id'=>$p['id'],'phase'=>'authority','health_score'=>(int)$p['authority_score'],'authority_score'=>(int)$p['authority_score'],'media_score'=>35,'promotion_score'=>25,'revenue_potential'=>25000,'next_action'=>'Einstein verifies SEO gaps; Shakespeare strengthens content; Scorsese creates media; Jessica promotes.'];
     $created[]=project($title,'authority_campaign','Mark Pires Real Estate',90,'One content idea becomes five deliverables: page, video, email, social, follow-up.',$gen,$p);
   }
 }
 // Create project from MLS opportunities.
 if(tablex('mls_scout_opportunities')){
   $count=one("SELECT COUNT(*) c,AVG(opportunity_score) avgscore FROM mls_scout_opportunities WHERE status='open'")?:['c'=>0,'avgscore'=>0];
   if((int)$count['c']>0){
     $gen=['source'=>'mls_scout_opportunities','phase'=>'sales','health_score'=>80,'authority_score'=>55,'media_score'=>25,'promotion_score'=>60,'sales_score'=>90,'revenue_potential'=>(int)$count['c']*18000,'open_opportunities'=>(int)$count['c'],'next_action'=>'Scout creates call list, Sherlock verifies ownership, Jessica prepares outreach.'];
     $created[]=project('Scout MLS Never-Sold Opportunity Pipeline','seller_lead_pipeline','Mark Pires Real Estate',98,'Expired/withdrawn/canceled properties that did not sell and are not active become real call-ready opportunities.',$gen,$count);
   }
 }
 // Create project from raw Scorsese uploads.
 if(tablex('scorsese_raw_projects')){
   foreach(allx("SELECT * FROM scorsese_raw_projects WHERE status IN ('uploaded','queued','working') ORDER BY id DESC LIMIT 5") as $r){
     $gen=['source'=>'scorsese_raw_projects','source_id'=>$r['id'],'phase'=>'production','health_score'=>72,'media_score'=>55,'promotion_score'=>35,'revenue_potential'=>5000,'file_url'=>$r['file_url'],'next_action'=>'Scorsese finds hooks and clips; Shakespeare writes captions; Jessica schedules promotion.'];
     $created[]=project('Raw Video Repurpose: '.$r['title'],'media_repurpose',$r['brand_style']?:'Discover Connecticut',88,'One raw video becomes shorts, reels, TikToks, titles, captions, thumbnails, and social posts.',$gen,$r);
   }
 }
 echo json_encode(['ok'=>true,'version'=>'V106.0 Project Genome Engine','projects_touched'=>count($created),'project_uids'=>$created,'next'=>'Open /dashboard/goliath-projects-v106.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V106.0 Project Genome','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>