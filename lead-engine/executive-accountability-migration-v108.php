<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function ex108($sql){return gdb()->exec($sql);}
function one108($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
function col108($t,$c){$r=one108("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
function add108($t,$c,$d,&$chg){if(!col108($t,$c)){ex108("ALTER TABLE `$t` ADD COLUMN `$c` $d");$chg[]="$t.$c";}}
$chg=[];
$tables=[
'executive_department_boards'=>[
'board_uid'=>'VARCHAR(100) NULL','executive_key'=>'VARCHAR(80) NULL','lane'=>'VARCHAR(40) NULL','title'=>'VARCHAR(255) NULL','source_table'=>'VARCHAR(120) NULL','source_id'=>'INT NULL','priority'=>'INT DEFAULT 50','confidence_score'=>'INT DEFAULT 0','status'=>"VARCHAR(60) DEFAULT 'open'",'details'=>'MEDIUMTEXT NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
'executive_kpi_daily'=>[
'kpi_uid'=>'VARCHAR(100) NULL','kpi_date'=>'DATE NULL','executive_key'=>'VARCHAR(80) NULL','department'=>'VARCHAR(120) NULL','completed_count'=>'INT DEFAULT 0','active_count'=>'INT DEFAULT 0','blocked_count'=>'INT DEFAULT 0','opportunities_count'=>'INT DEFAULT 0','revenue_influenced'=>'DECIMAL(14,2) DEFAULT 0','collaboration_score'=>'INT DEFAULT 0','confidence_score'=>'INT DEFAULT 0','quality_score'=>'INT DEFAULT 0','operating_score'=>'INT DEFAULT 0','summary'=>'MEDIUMTEXT NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'],
'executive_collaboration_credits'=>[
'credit_uid'=>'VARCHAR(100) NULL','project_uid'=>'VARCHAR(100) NULL','executive_key'=>'VARCHAR(80) NULL','credit_percent'=>'INT DEFAULT 0','credit_reason'=>'MEDIUMTEXT NULL','source'=>'VARCHAR(120) NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'],
'goliath_chairman_briefings'=>[
'brief_uid'=>'VARCHAR(100) NULL','brief_date'=>'DATE NULL','title'=>'VARCHAR(255) NULL','summary'=>'MEDIUMTEXT NULL','top_priorities_json'=>'JSON NULL','executive_rankings_json'=>'JSON NULL','pending_decisions_json'=>'JSON NULL','created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP'],
'scorsese_content_vault'=>[
'asset_uid'=>'VARCHAR(100) NULL','source_project_uid'=>'VARCHAR(100) NULL','title'=>'VARCHAR(255) NULL','brand_style'=>'VARCHAR(100) NULL','asset_type'=>'VARCHAR(80) NULL','file_url'=>'VARCHAR(255) NULL','tags_json'=>'JSON NULL','score'=>'INT DEFAULT 0','reuse_notes'=>'MEDIUMTEXT NULL','status'=>"VARCHAR(60) DEFAULT 'available'",'created_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP']
];
foreach($tables as $t=>$cols){ex108("CREATE TABLE IF NOT EXISTS `$t` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); foreach($cols as $c=>$d)add108($t,$c,$d,$chg);}
echo json_encode(['ok'=>true,'version'=>'V108.0 Executive Accountability Migration','changed_count'=>count($chg),'changed'=>$chg,'next'=>'Run executive-accountability-engine-v108.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V108.0 Migration','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>