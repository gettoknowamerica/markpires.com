<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function ex105($sql){return gdb()->exec($sql);}
 function one105($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function col105($t,$c){$r=one105("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function add105($t,$c,$d,&$chg){if(!col105($t,$c)){ex105("ALTER TABLE `$t` ADD COLUMN `$c` $d");$chg[]="$t.$c";}}
 function idx105($t,$i,$cols,&$chg){$r=one105("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$t,$i]);if(!((int)($r['c']??0))){try{ex105("ALTER TABLE `$t` ADD INDEX `$i` ($cols)");$chg[]="$t index $i";}catch(Throwable $e){}}}
 $chg=[];
 $tables=[
  'mls_import_batches'=>[
   'batch_uid'=>'VARCHAR(100) NULL','listing_type'=>'VARCHAR(40) NULL','original_filename'=>'VARCHAR(255) NULL','stored_path'=>'VARCHAR(255) NULL','row_count'=>'INT DEFAULT 0','imported_count'=>'INT DEFAULT 0','status'=>"VARCHAR(60) DEFAULT 'imported'",'notes'=>'MEDIUMTEXT NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'
  ],
  'mls_property_events'=>[
   'event_uid'=>'VARCHAR(100) NULL','batch_uid'=>'VARCHAR(100) NULL','listing_type'=>'VARCHAR(40) NULL','mls_id'=>'VARCHAR(100) NULL','normalized_address'=>'VARCHAR(255) NULL','property_address'=>'VARCHAR(255) NULL','town'=>'VARCHAR(120) NULL','state'=>'VARCHAR(40) NULL','zip'=>'VARCHAR(20) NULL','list_price'=>'DECIMAL(14,2) DEFAULT 0','sold_price'=>'DECIMAL(14,2) DEFAULT 0','status_text'=>'VARCHAR(120) NULL','status_date'=>'DATE NULL','raw_json'=>'JSON NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'
  ],
  'mls_master_properties'=>[
   'property_uid'=>'VARCHAR(100) NULL','normalized_address'=>'VARCHAR(255) NULL','property_address'=>'VARCHAR(255) NULL','town'=>'VARCHAR(120) NULL','state'=>'VARCHAR(40) NULL','zip'=>'VARCHAR(20) NULL','last_mls_id'=>'VARCHAR(100) NULL','has_active'=>'TINYINT DEFAULT 0','has_closed'=>'TINYINT DEFAULT 0','has_expired'=>'TINYINT DEFAULT 0','has_withdrawn'=>'TINYINT DEFAULT 0','has_canceled'=>'TINYINT DEFAULT 0','last_status'=>'VARCHAR(80) NULL','last_event_at'=>'DATETIME NULL','event_count'=>'INT DEFAULT 0','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'
  ],
  'mls_scout_opportunities'=>[
   'opportunity_uid'=>'VARCHAR(100) NULL','property_uid'=>'VARCHAR(100) NULL','normalized_address'=>'VARCHAR(255) NULL','property_address'=>'VARCHAR(255) NULL','town'=>'VARCHAR(120) NULL','state'=>'VARCHAR(40) NULL','zip'=>'VARCHAR(20) NULL','opportunity_type'=>'VARCHAR(80) NULL','opportunity_score'=>'INT DEFAULT 0','reason'=>'MEDIUMTEXT NULL','status'=>"VARCHAR(60) DEFAULT 'open'",'last_mls_id'=>'VARCHAR(100) NULL','last_status'=>'VARCHAR(80) NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
  ],
  'scorsese_raw_projects'=>[
   'project_uid'=>'VARCHAR(100) NULL','title'=>'VARCHAR(255) NULL','brand_style'=>'VARCHAR(80) NULL','source_type'=>'VARCHAR(80) NULL','original_filename'=>'VARCHAR(255) NULL','file_url'=>'VARCHAR(255) NULL','prompt'=>'MEDIUMTEXT NULL','status'=>"VARCHAR(60) DEFAULT 'uploaded'",'score'=>'INT DEFAULT 0','deliverables_json'=>'JSON NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
  ]
 ];
 foreach($tables as $t=>$cols){ex105("CREATE TABLE IF NOT EXISTS `$t` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); foreach($cols as $c=>$d)add105($t,$c,$d,$chg);}
 idx105('mls_property_events','normalized_address','normalized_address',$chg); idx105('mls_property_events','listing_type','listing_type',$chg);
 idx105('mls_master_properties','normalized_address','normalized_address',$chg); idx105('mls_scout_opportunities','status','status',$chg);
 idx105('scorsese_raw_projects','status','status',$chg);
 echo json_encode(['ok'=>true,'version'=>'V105.0 Executive Workspaces Intake Migration','changed_count'=>count($chg),'changed'=>$chg,'next'=>'Open /dashboard/mission-control-intake-v105.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V105.0 Migration','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>