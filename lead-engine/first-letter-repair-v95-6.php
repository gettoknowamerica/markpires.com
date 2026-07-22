<?php
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function t956($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function c956($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function e956($sql,$p=[]){$pdo=gdb();$st=$pdo->prepare($sql);$st->execute($p);return $st->rowCount();}
 function rep956($table,$col,$from,$to,&$changed){if(!t956($table)||!c956($table,$col))return;try{$n=e956("UPDATE `$table` SET `$col`=REPLACE(`$col`,?,?) WHERE `$col` LIKE ?",[$from,$to,'%'.$from.'%']);if($n)$changed[]="$table.$col: $from->$to ($n)";}catch(Throwable $e){}}
 function exact956($table,$col,$from,$to,&$changed){if(!t956($table)||!c956($table,$col))return;try{$n=e956("UPDATE `$table` SET `$col`=? WHERE LOWER(`$col`)=LOWER(?)",[$to,$from]);if($n)$changed[]="$table.$col exact: $from->$to ($n)";}catch(Throwable $e){}}
 $changed=[];
 $exec=['oliath'=>'goliath','essica'=>'jessica','cout'=>'scout','corsese'=>'scorsese','ozart'=>'mozart','hakespeare'=>'shakespeare','instein'=>'einstein','olumbo'=>'columbo','rospector'=>'prospector','ockefeller'=>'rockefeller','andora'=>'pandora','olmes'=>'holmes'];
 $town=['tamford'=>'Stamford','tanford'=>'Stamford','reenwich'=>'Greenwich','estport'=>'Westport','airfield'=>'Fairfield','orwalk'=>'Norwalk','ew Canaan'=>'New Canaan','ewcanaan'=>'New Canaan','idgefield'=>'Ridgefield','ilton'=>'Wilton','eston'=>'Weston','arien'=>'Darien','onroe'=>'Monroe','rumbull'=>'Trumbull','helton'=>'Shelton','tratford'=>'Stratford','ridgeport'=>'Bridgeport'];
 $slug=['/blog/tamford'=>'/blog/stamford','blog/tamford'=>'blog/stamford','tamford-home-selling-guide'=>'stamford-home-selling-guide','/blog/reenwich'=>'/blog/greenwich','/blog/estport'=>'/blog/westport','/blog/airfield'=>'/blog/fairfield','/blog/orwalk'=>'/blog/norwalk','/blog/ew-canaan'=>'/blog/new-canaan','/blog/arien'=>'/blog/darien'];
 foreach(['executive_commissions','local_ai_tasks','executive_deliverables','executive_events','executive_memory','goliath_executive_heartbeat','goliath_browser_jobs'] as $tb){foreach(['executive_key','executive','agent'] as $col){foreach($exec as $bad=>$good)exact956($tb,$col,$bad,$good,$changed);}}
 foreach(['internal_crm_contacts','scout_intel_dossiers','goliath_browser_jobs','executive_deliverables','local_ai_tasks'] as $tb){foreach(['town','target_town','preview','title','recommended_blog','next_action','public_notes','notes','evidence','evidence_log','deliverable_json','result_json','prompt','raw_json','raw_data','metadata','result'] as $col){foreach($town as $bad=>$good){rep956($tb,$col,$bad,$good,$changed);rep956($tb,$col,ucfirst($bad),$good,$changed);}foreach($slug as $bad=>$good)rep956($tb,$col,$bad,$good,$changed);}}
 echo json_encode(['ok'=>true,'version'=>'V95.6 First-Letter Repair','changed_count'=>count($changed),'changed'=>$changed,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V95.6 First-Letter Repair','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>