<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$raw=json_decode(file_get_contents('php://input'),true);
if(!is_array($raw)) $raw=array_merge($_POST,$_GET);
$key=$raw['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$exec=strtolower(preg_replace('/[^a-z0-9_\-]+/','',$raw['exec']??'goliath'));
$prompt=trim((string)($raw['prompt']??''));
$assetId=(int)($raw['asset_id']??0);
$mode=trim((string)($raw['mode']??'new'));
if($prompt===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'prompt_required']);exit;}
function c81($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function t81($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function uid81($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function ins81($table,$row){$safe=[];foreach($row as $k=>$v){if(c81($table,$k))$safe[$k]=$v;}return $safe?gdb_insert($table,$safe):null;}
$asset=null;if($assetId&&t81('goliath_deliverables')){try{$asset=gdb_one("SELECT * FROM goliath_deliverables WHERE id=? LIMIT 1",[$assetId]);}catch(Throwable $e){}}
$ctx='';if($asset){$ctx="\nSELECTED ASSET:\nID: $assetId\nTitle: ".($asset['title']??'')."\nType: ".($asset['deliverable_type']??'')."\nSummary: ".mb_substr((string)($asset['output_summary']??''),0,1600)."\nURL: ".($asset['output_url']??'')."\n";}
$rules=[
'shakespeare'=>'Create publish-ready writing only: article/blog/page/email/caption. No debriefs.',
'scout'=>'Create CRM-ready leads only: owner, address, phone, email, source, confidence, next action. Never invent.',
'jessica'=>'Create outreach-ready emails/messages only. Warm, human, relationship-first. No reports.',
'prospector'=>'Create verified opportunities only: organization, URL, contact path, fit, deadline, next action.',
'einstein'=>'Create concrete SEO/AEO fixes only: page, issue, schema, priority, implementation.',
'columbo'=>'Create YouTube/social growth assets only: title, hook, thumbnail, description, virality.',
'rockefeller'=>'Create revenue/prioritization assets only: ROI, pipeline, priority action.',
'pandora'=>'Create partnership/expansion assets only: opportunity, package, pitch angle.',
'mozart'=>'Create audio assets only: cue, hook, music/voiceover direction.',
'goliath'=>'Create executive council decisions only: teams, missions, priorities, bottlenecks.'
];
$full="V81.1 EXECUTIVE STUDIO REQUEST\nExecutive: $exec\nMode: $mode\n\nDo NOT return an executive brief. Do NOT explain the work. Create the actual finished asset Mark can use immediately.\n\n".($rules[$exec]??'Create a finished usable asset, not a summary.')."\n$ctx\nMARK INSTRUCTIONS:\n$prompt\n\nREQUIRED:\nASSET_TITLE:\nASSET_TYPE:\nCONTENT:\nNEXT_ACTION:\n";
try{
 $taskId=null;
 if(t81('local_ai_tasks')){$taskId=ins81('local_ai_tasks',['task_uid'=>uid81('lat'),'agent'=>ucfirst($exec),'task_type'=>'v81_executive_studio_prompt','model'=>'goliath-local-worker','prompt'=>$full,'status'=>'queued','priority'=>240,'progress'=>0,'metadata'=>json_encode(['exec'=>$exec,'mode'=>$mode,'asset_id'=>$assetId,'source'=>'v81_1_studio'],JSON_UNESCAPED_SLASHES),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);}
 echo json_encode(['ok'=>true,'success'=>true,'version'=>'V81.1 Executive Studio Prompt','task_id'=>$taskId,'exec'=>$exec],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>