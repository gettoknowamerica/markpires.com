<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function dbx(){return gdb();}
 function ex($sql){return dbx()->exec($sql);}
 function one($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function col($t,$c){$r=one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function add($t,$c,$def,&$chg){if(!col($t,$c)){ex("ALTER TABLE `$t` ADD COLUMN `$c` $def");$chg[]="$t.$c";}}
 function idx($t,$i,$cols,&$chg){$r=one("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$t,$i]);if(!((int)($r['c']??0))){try{ex("ALTER TABLE `$t` ADD INDEX `$i` ($cols)");$chg[]="$t index $i";}catch(Throwable $e){}}}
 $chg=[];
 ex("CREATE TABLE IF NOT EXISTS goliath_runtime_state(id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(['state_key'=>"VARCHAR(100) NULL",'state_value'=>"MEDIUMTEXT NULL",'metadata_json'=>"JSON NULL",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP"] as $c=>$d)add('goliath_runtime_state',$c,$d,$chg);
 idx('goliath_runtime_state','state_key','state_key',$chg);

 ex("CREATE TABLE IF NOT EXISTS goliath_mission_bus_events(id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(['event_uid'=>"VARCHAR(100) NULL",'mission_uid'=>"VARCHAR(100) NULL",'event_type'=>"VARCHAR(80) NULL",'from_executive'=>"VARCHAR(80) NULL",'to_executive'=>"VARCHAR(80) NULL",'title'=>"VARCHAR(255) NULL",'details'=>"MEDIUMTEXT NULL",'priority'=>"INT DEFAULT 50",'status'=>"VARCHAR(60) DEFAULT 'new'",'metadata_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP"] as $c=>$d)add('goliath_mission_bus_events',$c,$d,$chg);
 idx('goliath_mission_bus_events','mission_uid','mission_uid',$chg); idx('goliath_mission_bus_events','event_type','event_type',$chg); idx('goliath_mission_bus_events','status','status',$chg);

 ex("CREATE TABLE IF NOT EXISTS executive_top10_boards(id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(['board_uid'=>"VARCHAR(100) NULL",'executive_key'=>"VARCHAR(80) NULL",'rank_no'=>"INT DEFAULT 0",'title'=>"VARCHAR(255) NULL",'score'=>"INT DEFAULT 0",'status'=>"VARCHAR(80) NULL",'reason'=>"MEDIUMTEXT NULL",'source_table'=>"VARCHAR(120) NULL",'source_id'=>"INT NULL",'direct_url'=>"VARCHAR(255) NULL",'metadata_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"] as $c=>$d)add('executive_top10_boards',$c,$d,$chg);
 idx('executive_top10_boards','executive_key','executive_key',$chg); idx('executive_top10_boards','rank_no','rank_no',$chg);

 ex("CREATE TABLE IF NOT EXISTS goliath_universal_runtime_logs(id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach(['run_uid'=>"VARCHAR(100) NULL",'status'=>"VARCHAR(60) NULL",'summary'=>"MEDIUMTEXT NULL",'metrics_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP"] as $c=>$d)add('goliath_universal_runtime_logs',$c,$d,$chg);
 idx('goliath_universal_runtime_logs','created_at','created_at',$chg);

 echo json_encode(['ok'=>true,'version'=>'V103.0 Universal Runtime Migration','changed_count'=>count($chg),'changed'=>$chg,'next'=>'Run universal-executive-runtime-v103.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V103.0 Universal Runtime Migration','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>