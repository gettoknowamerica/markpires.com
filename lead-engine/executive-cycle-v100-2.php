<?php
/**
 * V100.2 Always-Running Executive Cycle
 * One URL that keeps the whole company moving:
 * - Dispatches executive commissions
 * - Builds production packages
 * - Pushes Scorsese jobs
 * - Runs Jessica/Shakespeare handoffs
 * - Runs morning/council refresh where available
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function v102_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v102_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function v102_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function v102_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function v102_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(v102_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function v102_url($file,$params=[]){
   $params['key']=$_GET['key']??($_POST['key']??'');
   return 'https://'.($_SERVER['HTTP_HOST']??'www.markpires.com').'/lead-engine/'.$file.'?'.http_build_query($params);
 }
 function v102_call($label,$file,$params=[]){
   $path=__DIR__.'/'.$file;
   if(!file_exists($path)) return ['label'=>$label,'ok'=>false,'skipped'=>true,'reason'=>'missing '.$file];
   $url=v102_url($file,$params);
   $ctx=stream_context_create(['http'=>['timeout'=>45,'ignore_errors'=>true]]);
   $raw=@file_get_contents($url,false,$ctx);
   $json=json_decode((string)$raw,true);
   return ['label'=>$label,'ok'=>(bool)($json['ok']??$json['success']??false),'url'=>$url,'result'=>$json?:substr((string)$raw,0,600)];
 }

 // Install tiny heartbeat tables if missing.
 v102_exec("CREATE TABLE IF NOT EXISTS executive_cycle_heartbeats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cycle_uid VARCHAR(80) UNIQUE,
  cycle_type VARCHAR(80),
  status VARCHAR(80),
  summary MEDIUMTEXT NULL,
  result_json JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(cycle_type),
  INDEX(status),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 v102_exec("CREATE TABLE IF NOT EXISTS executive_autorun_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) UNIQUE,
  setting_value TEXT NULL,
  enabled TINYINT(1) DEFAULT 1,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 $mode=$_GET['mode']??'all';
 $limit=max(1,min(200,(int)($_GET['limit']??50)));
 $calls=[];

 if($mode==='all' || $mode==='dispatcher'){
   $calls[]=v102_call('Universal dispatcher','executive-dispatcher.php',['limit'=>$limit]);
   $calls[]=v102_call('Executive engine','executive-engine.php',['limit'=>$limit]);
 }

 if($mode==='all' || $mode==='scout'){
   $calls[]=v102_call('Scout revenue kernel','scout-revenue-kernel-v96-1.php',['limit'=>$limit]);
   $calls[]=v102_call('Scout dossier completer','scout-dossier-completer-v95-7.php',['limit'=>$limit]);
 }

 if($mode==='all' || $mode==='jessica'){
   $calls[]=v102_call('Jessica relationship engine','jessica-relationship-engine-v96-2.php',['limit'=>$limit]);
   $calls[]=v102_call('Jessica Shakespeare handoff','jessica-shakespeare-handoff-v99-4.php',['limit'=>$limit]);
 }

 if($mode==='all' || $mode==='production'){
   $calls[]=v102_call('V100 production collaboration','v100-production-collaboration-engine.php',['limit'=>$limit]);
 }

 if($mode==='all' || $mode==='scorsese'){
   $calls[]=v102_call('Scorsese force push','v100-scorsese-force-push.php',['limit'=>$limit]);
 }

 if($mode==='all' || $mode==='brief'){
   $calls[]=v102_call('Executive council nightly','executive-council-nightly-v99-1.php',[]);
   $calls[]=v102_call('Morning brief','morning-brief-v96-3.php',[]);
 }

 $summary=[];
 foreach($calls as $c){$summary[]=$c['label'].': '.(($c['ok']??false)?'ok':(($c['skipped']??false)?'skipped':'check'));}
 v102_insert('executive_cycle_heartbeats',[
  'cycle_uid'=>v102_uid('cycle'),
  'cycle_type'=>$mode,
  'status'=>'complete',
  'summary'=>implode(' | ',$summary),
  'result_json'=>json_encode($calls,JSON_UNESCAPED_SLASHES),
  'created_at'=>gdb_now()
 ]);

 echo json_encode([
  'ok'=>true,
  'version'=>'V100.2 Always-Running Executive Cycle',
  'mode'=>$mode,
  'calls'=>$calls,
  'next'=>'Keep the local autorunner PowerShell open, or add a Hostinger cron to this URL every 5 minutes.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.2 Always-Running Executive Cycle','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>