<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function uid108($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function one108($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
function all108($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];} }
function table108($t){$r=one108("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
function col108($t,$c){$r=one108("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
function ins108($t,$row){$safe=[];foreach($row as $k=>$v){if(col108($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
$execs=['goliath'=>'Executive Board','chief_of_staff'=>'Operations','scout'=>'Sales Intelligence','jessica'=>'Relationships','shakespeare'=>'Authority','scorsese'=>'Creative Production','sherlock'=>'Verification','einstein'=>'Intelligence','pandora'=>'Trends','mozart'=>'Audio','rockefeller'=>'Finance'];
try{gdb()->exec("DELETE FROM executive_department_boards WHERE DATE(created_at)=CURDATE()");}catch(Throwable $e){}
$boards=0;
foreach($execs as $exec=>$dept){
 if(table108('goliath_opportunity_marketplace')){
  foreach(all108("SELECT * FROM goliath_opportunity_marketplace WHERE status IN ('proposed','council','approved') AND (executive_key=? OR recommended_team_json LIKE ?) ORDER BY priority_score DESC LIMIT 5",[$exec,'%'.$exec.'%']) as $o){
   ins108('executive_department_boards',['board_uid'=>uid108('board'),'executive_key'=>$exec,'lane'=>'inbox','title'=>$o['title'],'source_table'=>'goliath_opportunity_marketplace','source_id'=>$o['id'],'priority'=>(int)$o['priority_score'],'confidence_score'=>(int)$o['confidence_score'],'status'=>'open','details'=>$o['details'],'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $boards++;
  }
 }
 if(table108('goliath_project_departments')){
  foreach(all108("SELECT d.*,p.title project_title FROM goliath_project_departments d LEFT JOIN goliath_projects p ON p.project_uid=d.project_uid WHERE d.executive_key=? ORDER BY d.score DESC LIMIT 5",[$exec]) as $d){
   $lane=$d['status']==='working'?'working':($d['status']==='waiting'?'blocked':'review');
   ins108('executive_department_boards',['board_uid'=>uid108('board'),'executive_key'=>$exec,'lane'=>$lane,'title'=>($d['project_title']?:'Project').' — '.$d['summary'],'source_table'=>'goliath_project_departments','source_id'=>$d['id'],'priority'=>(int)$d['score'],'confidence_score'=>(int)$d['score'],'status'=>$d['status'],'details'=>$d['summary'],'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $boards++;
  }
 }
}
$vaulted=0;
if(table108('scorsese_raw_projects')){
 foreach(all108("SELECT * FROM scorsese_raw_projects ORDER BY id DESC LIMIT 100") as $r){
  if(one108("SELECT id FROM scorsese_content_vault WHERE file_url=? LIMIT 1",[$r['file_url']]))continue;
  ins108('scorsese_content_vault',['asset_uid'=>uid108('asset'),'source_project_uid'=>$r['project_uid'],'title'=>$r['title'],'brand_style'=>$r['brand_style'],'asset_type'=>'raw_video','file_url'=>$r['file_url'],'tags_json'=>json_encode([$r['brand_style'],'raw_video','repurpose','shorts','reels','captions','thumbnails']),'score'=>70,'reuse_notes'=>'Mine for hooks, shorts, reels, captions, thumbnails, blog support, and sponsor cuts.','status'=>'available','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  $vaulted++;
 }
}
$credits=0;
if(table108('goliath_projects')&&table108('goliath_project_departments')){
 foreach(all108("SELECT * FROM goliath_projects WHERE status='active' ORDER BY updated_at DESC LIMIT 40") as $p){
  if(one108("SELECT id FROM executive_collaboration_credits WHERE project_uid=? LIMIT 1",[$p['project_uid']]))continue;
  $deps=all108("SELECT executive_key,score FROM goliath_project_departments WHERE project_uid=?",[$p['project_uid']]); $total=0; foreach($deps as $d)$total+=max(1,(int)$d['score']);
  foreach($deps as $d){$pct=$total?round(max(1,(int)$d['score'])/$total*100):0; ins108('executive_collaboration_credits',['credit_uid'=>uid108('credit'),'project_uid'=>$p['project_uid'],'executive_key'=>$d['executive_key'],'credit_percent'=>$pct,'credit_reason'=>'Credit assigned from project department participation and score.','source'=>'v108_accountability','created_at'=>gdb_now()]); $credits++;}
 }
}
$today=date('Y-m-d');$kpis=0;$rankings=[];
foreach($execs as $exec=>$dept){
 $active=one108("SELECT COUNT(*) c FROM executive_department_boards WHERE executive_key=? AND lane IN ('inbox','working','review')",[$exec])?:['c'=>0];
 $blocked=one108("SELECT COUNT(*) c FROM executive_department_boards WHERE executive_key=? AND lane='blocked'",[$exec])?:['c'=>0];
 $opps=one108("SELECT COUNT(*) c,AVG(confidence_score) conf,AVG(revenue_score) rev FROM goliath_opportunity_marketplace WHERE executive_key=? OR recommended_team_json LIKE ?",[$exec,'%'.$exec.'%'])?:['c'=>0,'conf'=>70,'rev'=>50];
 $done=table108('executive_mission_assignments')?(one108("SELECT COUNT(*) c FROM executive_mission_assignments WHERE executive_key=? AND status IN ('complete','completed','delivered')",[$exec])?:['c'=>0]):['c'=>0];
 $collab=one108("SELECT COALESCE(SUM(credit_percent),0) c FROM executive_collaboration_credits WHERE executive_key=?",[$exec])?:['c'=>0];
 $confidence=(int)round($opps['conf']??75); $score=max(0,min(100,60+min(20,(int)$opps['c']*2)+min(10,(int)$done['c'])+min(10,(int)$collab['c']/20)-(int)$blocked['c']*5)); $revenue=(float)($opps['rev']??50)*1000;
 ins108('executive_kpi_daily',['kpi_uid'=>uid108('kpi'),'kpi_date'=>$today,'executive_key'=>$exec,'department'=>$dept,'completed_count'=>(int)$done['c'],'active_count'=>(int)$active['c'],'blocked_count'=>(int)$blocked['c'],'opportunities_count'=>(int)$opps['c'],'revenue_influenced'=>$revenue,'collaboration_score'=>min(100,(int)$collab['c']),'confidence_score'=>$confidence,'quality_score'=>75,'operating_score'=>$score,'summary'=>ucfirst(str_replace('_',' ',$exec)).' operating as '.$dept.' department.','created_at'=>gdb_now()]);
 $rankings[]=['executive'=>$exec,'department'=>$dept,'score'=>$score,'active'=>(int)$active['c'],'blocked'=>(int)$blocked['c'],'revenue'=>$revenue]; $kpis++;
}
usort($rankings,fn($a,$b)=>$b['score']<=>$a['score']);
$priorities=table108('goliath_opportunity_marketplace')?all108("SELECT title,priority_score,confidence_score,status FROM goliath_opportunity_marketplace ORDER BY priority_score DESC LIMIT 10"):[];
$pending=table108('goliath_opportunity_marketplace')?all108("SELECT * FROM goliath_opportunity_marketplace WHERE status IN ('council','approved') ORDER BY priority_score DESC LIMIT 10"):[];
ins108('goliath_chairman_briefings',['brief_uid'=>uid108('brief'),'brief_date'=>$today,'title'=>'Chairman Briefing '.date('Y-m-d H:i'),'summary'=>'V108 accountability updated departments, KPIs, collaboration credits, content vault, and rankings.','top_priorities_json'=>json_encode($priorities),'executive_rankings_json'=>json_encode($rankings),'pending_decisions_json'=>json_encode($pending),'created_at'=>gdb_now()]);
echo json_encode(['ok'=>true,'version'=>'V108.0 Executive Accountability Engine','department_cards'=>$boards,'vaulted_assets'=>$vaulted,'credits_created'=>$credits,'kpis_created'=>$kpis,'top_executive'=>$rankings[0]??null,'next'=>'Open /dashboard/executive-accountability-v108.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V108.0 Accountability','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>