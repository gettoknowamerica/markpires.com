<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$key=$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function ins($t,$row){$safe=[];foreach($row as $k=>$v){if(col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
$date=date('Y-m-d');$execs=['goliath','scout','jessica','shakespeare','scorsese','sherlock','einstein','pandora','mozart','rockefeller'];$created=[];
foreach($execs as $e){if(gdb_one("SELECT id FROM executive_council_reports WHERE council_date=? AND executive_key=? LIMIT 1",[$date,$e]))continue;$inits=gdb_all("SELECT title,reason FROM executive_initiatives WHERE executive_key=? AND status='recommended' ORDER BY priority DESC,id DESC LIMIT 3",[$e])?:[];$titles=array_map(fn($x)=>$x['title'],$inits);ins('executive_council_reports',['report_uid'=>uid('council'),'council_date'=>$date,'executive_key'=>$e,'completed_today'=>'V101 organization pulse active.','learned_today'=>'Idle time is unacceptable. I must create or advance missions without waiting for Mark.','recommends_next'=>count($titles)?implode("\n",$titles):'Create one high-impact mission for tomorrow.','needs_help'=>'Goliath should assign shared missions when collaboration improves the outcome.','proposed_missions_json'=>json_encode($inits),'created_at'=>gdb_now()]);$created[]=$e;}
echo json_encode(['ok'=>true,'version'=>'V101.0 Executive Council Pulse','created_reports'=>$created,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>