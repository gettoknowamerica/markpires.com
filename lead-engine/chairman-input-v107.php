<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=$_POST['key']??$_GET['key']??'';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function uid107($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));} function col107($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}} function ins107($t,$row){$safe=[];foreach($row as $k=>$v){if(col107($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
$session=$_POST['session_uid']??'';$text=trim($_POST['input_text']??'');$action=$_POST['action_type']??'comment';$target=$_POST['target_uid']??'';$delta=(int)($_POST['priority_delta']??0);
if(!$session||!$text){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'session_and_text_required']);exit;}
$inputUid=uid107('chair');ins107('goliath_chairman_inputs',['session_uid'=>$session,'input_uid'=>$inputUid,'input_text'=>$text,'action_type'=>$action,'target_uid'=>$target,'priority_delta'=>$delta,'status'=>'logged','created_at'=>gdb_now()]);ins107('goliath_council_messages',['session_uid'=>$session,'speaker_key'=>'mark','speaker_name'=>'Chairman Mark Pires','message_type'=>$action,'message_text'=>$text,'confidence_score'=>100,'opportunity_uid'=>$target,'created_at'=>gdb_now()]);
if($target&&$delta!==0){gdb()->exec("UPDATE goliath_opportunity_marketplace SET priority_score=GREATEST(1,LEAST(100,priority_score+$delta)), updated_at=NOW() WHERE opportunity_uid=".gdb()->quote($target));}
if($target&&in_array($action,['approve','priority_one'],true)){gdb()->exec("UPDATE goliath_opportunity_marketplace SET status='approved', updated_at=NOW() WHERE opportunity_uid=".gdb()->quote($target));}
echo json_encode(['ok'=>true,'version'=>'V107.0 Chairman Input','input_uid'=>$inputUid,'message'=>'Chairman input logged into council memory.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>