<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid105($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function col105($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins105($t,$row){$safe=[];foreach($row as $k=>$v){if(col105($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function one105($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 gdb()->exec("UPDATE mls_scout_opportunities SET status='superseded' WHERE status='open'");
 $rows=gdb_all("SELECT * FROM mls_master_properties WHERE (has_expired=1 OR has_withdrawn=1 OR has_canceled=1) AND COALESCE(has_closed,0)=0 AND COALESCE(has_active,0)=0 ORDER BY updated_at DESC LIMIT 5000")?:[];
 $created=0;
 foreach($rows as $p){
   $score=60;
   if($p['has_expired'])$score+=15;
   if($p['has_withdrawn'])$score+=10;
   if($p['has_canceled'])$score+=10;
   if((int)$p['event_count']>1)$score+=min(10,(int)$p['event_count']*2);
   $score=min(100,$score);
   $reason='Kept because property has expired/withdrawn/canceled history, has no closed match, and is not currently active in uploaded MLS datasets.';
   if(one105("SELECT id FROM mls_scout_opportunities WHERE normalized_address=? AND status='open' LIMIT 1",[$p['normalized_address']]))continue;
   ins105('mls_scout_opportunities',['opportunity_uid'=>uid105('mls_opp'),'property_uid'=>$p['property_uid'],'normalized_address'=>$p['normalized_address'],'property_address'=>$p['property_address'],'town'=>$p['town'],'state'=>$p['state'],'zip'=>$p['zip'],'opportunity_type'=>'expired_withdrawn_canceled_never_sold','opportunity_score'=>$score,'reason'=>$reason,'status'=>'open','last_mls_id'=>$p['last_mls_id'],'last_status'=>$p['last_status'],'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $created++;
 }
 echo json_encode(['ok'=>true,'version'=>'V105.0 Scout MLS Reconcile','open_opportunities'=>$created,'rule'=>'expired/withdrawn/canceled AND not closed AND not active','next'=>'Open /dashboard/scout-mls-intelligence-v105.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V105.0 Reconcile','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>