<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function ex($sql){return gdb()->exec($sql);}
 function one($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function col($t,$c){$r=one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function addc($t,$c,$d,&$chg){if(!col($t,$c)){ex("ALTER TABLE `$t` ADD COLUMN `$c` $d");$chg[]="$t.$c";}}
 function idx($t,$i,$cols,&$chg){$r=one("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$t,$i]);if(!((int)($r['c']??0))){try{ex("ALTER TABLE `$t` ADD INDEX `$i` ($cols)");$chg[]="$t index $i";}catch(Throwable $e){}}}
 $chg=[];
 $tables=[
 'goliath_projects'=>[
  'project_uid'=>'VARCHAR(100) NULL','title'=>'VARCHAR(255) NULL','project_type'=>'VARCHAR(100) NULL','business_unit'=>'VARCHAR(120) NULL','status'=>"VARCHAR(60) DEFAULT 'active'",'owner_executive'=>"VARCHAR(80) DEFAULT 'goliath'",'priority'=>'INT DEFAULT 50','health_score'=>'INT DEFAULT 0','revenue_potential'=>'DECIMAL(14,2) DEFAULT 0','authority_score'=>'INT DEFAULT 0','media_score'=>'INT DEFAULT 0','promotion_score'=>'INT DEFAULT 0','sales_score'=>'INT DEFAULT 0','analytics_score'=>'INT DEFAULT 0','current_phase'=>'VARCHAR(80) NULL','genome_json'=>'JSON NULL','why_text'=>'MEDIUMTEXT NULL','next_action'=>'MEDIUMTEXT NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
 ],
 'goliath_project_departments'=>[
  'project_uid'=>'VARCHAR(100) NULL','department_key'=>'VARCHAR(80) NULL','executive_key'=>'VARCHAR(80) NULL','status'=>"VARCHAR(60) DEFAULT 'waiting'",'score'=>'INT DEFAULT 0','summary'=>'MEDIUMTEXT NULL','deliverables_json'=>'JSON NULL','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'
 ],
 'goliath_project_deliverables'=>[
  'project_uid'=>'VARCHAR(100) NULL','executive_key'=>'VARCHAR(80) NULL','deliverable_type'=>'VARCHAR(100) NULL','title'=>'VARCHAR(255) NULL','status'=>"VARCHAR(60) DEFAULT 'needed'",'score'=>'INT DEFAULT 0','url'=>'VARCHAR(255) NULL','metadata_json'=>'JSON NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
 ],
 'goliath_project_timeline'=>[
  'project_uid'=>'VARCHAR(100) NULL','executive_key'=>'VARCHAR(80) NULL','event_type'=>'VARCHAR(100) NULL','title'=>'VARCHAR(255) NULL','details'=>'MEDIUMTEXT NULL','status'=>"VARCHAR(60) DEFAULT 'logged'",'created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'
 ],
 'goliath_project_dependencies'=>[
  'project_uid'=>'VARCHAR(100) NULL','from_executive'=>'VARCHAR(80) NULL','to_executive'=>'VARCHAR(80) NULL','dependency_type'=>'VARCHAR(100) NULL','status'=>"VARCHAR(60) DEFAULT 'open'",'details'=>'MEDIUMTEXT NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
 ]
 ];
 foreach($tables as $t=>$cols){ex("CREATE TABLE IF NOT EXISTS `$t` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");foreach($cols as $c=>$d)addc($t,$c,$d,$chg);}
 foreach(['goliath_projects'=>'project_uid','goliath_project_departments'=>'project_uid','goliath_project_deliverables'=>'project_uid','goliath_project_timeline'=>'project_uid','goliath_project_dependencies'=>'project_uid'] as $t=>$c)idx($t,$c,$c,$chg);
 echo json_encode(['ok'=>true,'version'=>'V106.0 Project Genome Migration','changed_count'=>count($chg),'changed'=>$chg,'next'=>'Run project-genome-engine-v106.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V106.0 Migration','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>