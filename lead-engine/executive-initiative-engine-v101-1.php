<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function recent($e,$t){try{return !!gdb_one("SELECT id FROM executive_initiatives WHERE executive_key=? AND title=? AND created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR) LIMIT 1",[$e,$t]);}catch(Throwable $x){return false;}}
 $ideas=[
 'scout'=>[['Find 20 new expired/withdrawn seller opportunities','Revenue pipeline expansion'],['Refresh ready dossiers with missing phone/email/social fields','Speed-to-lead improvement'],['Segment today’s calls by expired, withdrawn, absentee, and luxury','Founder time saved']],
 'shakespeare'=>[['Create a full campaign package for absentee owners','Authority + Jessica support'],['Upgrade three CT town authority pages with story and visual requests','SEO/AEO authority'],['Turn every article into email, social captions, video brief, and CTA package','Campaign completeness']],
 'scorsese'=>[['Create visual briefs for Shakespeare’s top five campaigns','Video quality lift'],['Generate thumbnails for every completed article package','Click-through lift'],['Audit failed/stalled renders and choose best workflow per mission','Production reliability']],
 'jessica'=>[['Prepare follow-up drafts for every Scout-ready dossier','Relationship momentum'],['Create appointment confirmation templates with calendar-ready language','Professional touchpoint'],['Identify leads with no touch in 72 hours and create reactivation missions','No missed relationships']],
 'sherlock'=>[['Verify facts and claims for the top five content packages','Trust protection'],['Create source lists for town authority pages','Authority proof'],['Flag content needing stronger evidence before publishing','Risk reduction']],
 'einstein'=>[['Review top landing pages for CTA and conversion improvements','Conversion lift'],['Recommend schema and internal links for new Shakespeare pages','SEO/AEO lift'],['Create a system health scorecard for daily executive output','Operational intelligence']],
 'pandora'=>[['Find three Connecticut trend angles for tomorrow’s content','Creative timing'],['Build seasonal hooks for buyer/seller campaigns','Engagement lift'],['Recommend design/image directions for top campaigns','Visual quality']],
 'mozart'=>[['Create audio direction notes for Scorsese’s top campaigns','Production polish'],['Draft House Detective/Discover CT sound identity ideas','Brand memory'],['Suggest music moods for each video campaign','Emotional lift']],
 'rockefeller'=>[['Score today’s missions by likely revenue impact','ROI focus'],['Prioritize follow-up opportunities by commission potential','Pipeline focus'],['Recommend one monetization improvement','Business growth']]
 ];
 $limit=max(1,min(60,(int)($_GET['limit']??36)));$created=[];$n=0;
 foreach($ideas as $exec=>$list){foreach($list as $i){if($n>=$limit)break 2;if(recent($exec,$i[0]))continue;$packet=['source'=>'executive_initiative','owner'=>$exec,'expected_output'=>$i[0],'reason'=>$i[1]];$id=ins('executive_initiatives',['initiative_uid'=>uid('init'),'executive_key'=>$exec,'title'=>$i[0],'reason'=>$i[1],'expected_impact'=>'Advances revenue, authority, relationships, operations, or founder time saved.','recommended_mission_packet_json'=>json_encode($packet,JSON_UNESCAPED_SLASHES),'status'=>'recommended','priority'=>85,'created_at'=>gdb_now()]);$created[]=['id'=>$id,'executive'=>$exec,'title'=>$i[0]];$n++;}}
 echo json_encode(['ok'=>true,'version'=>'V101.1 Executive Initiative Engine','created_count'=>count($created),'created'=>$created,'next'=>'Open /dashboard/goliath-organization-core.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V101.1 Executive Initiative Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>