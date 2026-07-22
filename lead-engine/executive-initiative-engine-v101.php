<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
function recent($e,$t){return !!gdb_one("SELECT id FROM executive_initiatives WHERE executive_key=? AND title=? AND created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR) LIMIT 1",[$e,$t]);}
$ideas=[
'scout'=>['Find 20 new expired/withdrawn seller opportunities','Refresh ready dossiers with missing phone/email/social fields','Separate today’s call list by expired, withdrawn, absentee, and luxury'],
'shakespeare'=>['Create a full campaign package for absentee owners','Create three richer CT town authority pages with story and visual requests','Turn every article into email, social captions, video brief, and CTA package'],
'scorsese'=>['Create visual briefs for Shakespeare’s top five campaigns','Generate thumbnails for every completed article package','Audit failed/stalled renders and recommend the best workflow engine per mission'],
'jessica'=>['Prepare follow-up drafts for every Scout-ready dossier','Create appointment confirmation templates with calendar-ready language','Identify any lead with no touch in 72 hours and create a reactivation mission'],
'sherlock'=>['Verify facts and claims for the top five content packages','Create source lists for town authority pages','Flag content that needs stronger evidence before publishing'],
'einstein'=>['Review top landing pages for CTA and conversion improvements','Recommend schema and internal links for new Shakespeare pages','Create a system health scorecard for daily executive output'],
'pandora'=>['Find three Connecticut trend angles for tomorrow’s content','Build seasonal hooks for buyer/seller campaigns','Recommend design/image directions for top campaigns'],
'mozart'=>['Create audio direction notes for Scorsese’s top campaigns','Draft House Detective/Discover CT sound identity ideas','Suggest music moods for each video campaign'],
'rockefeller'=>['Score today’s missions by likely revenue impact','Prioritize follow-up opportunities by commission potential','Recommend one monetization improvement']];
$limit=max(1,min(60,(int)($_GET['limit']??36)));$created=[];$n=0;
foreach($ideas as $exec=>$list){foreach($list as $title){if($n>=$limit)break 2;if(recent($exec,$title))continue;$packet=['source'=>'executive_initiative','owner'=>$exec,'expected_output'=>$title];$id=ins('executive_initiatives',['initiative_uid'=>uid('init'),'executive_key'=>$exec,'title'=>$title,'reason'=>'Creates measurable progress without waiting for Mark.','expected_impact'=>'Advances revenue, authority, relationships, or operations.','recommended_mission_packet_json'=>json_encode($packet),'status'=>'recommended','priority'=>85,'created_at'=>gdb_now()]);$created[]=['id'=>$id,'executive'=>$exec,'title'=>$title];$n++;}}
echo json_encode(['ok'=>true,'version'=>'V101.0 Executive Initiative Engine','created_count'=>count($created),'created'=>$created,'next'=>'Open /dashboard/goliath-organization-core.php','time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>