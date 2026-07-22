<?php
/**
 * V101.1 Foundation Migration Engine
 * Repairs existing V101 tables safely by adding missing columns instead of assuming fresh tables.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function m101_db(){ $db=gdb(); if(!$db) throw new Exception('Goliath DB unavailable'); return $db; }
 function m101_exec($sql){ return m101_db()->exec($sql); }
 function m101_one($sql,$p=[]){try{return gdb_one($sql,$p)?:null;}catch(Throwable $e){return null;}}
 function m101_table($t){$r=m101_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
 function m101_col($t,$c){$r=m101_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function m101_add($t,$c,$def,&$changes){ if(!m101_col($t,$c)){ m101_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def"); $changes[]="$t.$c"; } }
 function m101_index_exists($t,$idx){$r=m101_one("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$t,$idx]);return ((int)($r['c']??0))>0;}
 function m101_idx($t,$idx,$cols,&$changes){ if(m101_table($t)&&!m101_index_exists($t,$idx)){ try{m101_exec("ALTER TABLE `$t` ADD INDEX `$idx` ($cols)");$changes[]="$t index $idx";}catch(Throwable $e){} } }

 $changes=[];

 m101_exec("CREATE TABLE IF NOT EXISTS goliath_core_charter (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 m101_add('goliath_core_charter','charter_key',"VARCHAR(80) NULL",$changes);
 m101_add('goliath_core_charter','version',"VARCHAR(40) NULL",$changes);
 m101_add('goliath_core_charter','title',"VARCHAR(255) NULL",$changes);
 m101_add('goliath_core_charter','charter_text',"MEDIUMTEXT NULL",$changes);
 m101_add('goliath_core_charter','laws_json',"JSON NULL",$changes);
 m101_add('goliath_core_charter','created_at',"DATETIME DEFAULT CURRENT_TIMESTAMP",$changes);
 m101_add('goliath_core_charter','updated_at',"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",$changes);
 if(!m101_index_exists('goliath_core_charter','charter_key')){try{m101_exec("ALTER TABLE goliath_core_charter ADD UNIQUE KEY charter_key (charter_key)");$changes[]='goliath_core_charter unique charter_key';}catch(Throwable $e){}}

 m101_exec("CREATE TABLE IF NOT EXISTS executive_dna_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'executive_key'=>"VARCHAR(80) NULL",'executive_name'=>"VARCHAR(120) NULL",'title'=>"VARCHAR(180) NULL",
  'identity_text'=>"MEDIUMTEXT NULL",'mission_text'=>"MEDIUMTEXT NULL",'constitution_text'=>"MEDIUMTEXT NULL",
  'responsibilities_json'=>"JSON NULL",'knowledge_sources_json'=>"JSON NULL",'kpis_json'=>"JSON NULL",
  'daily_routine_json'=>"JSON NULL",'initiative_rules_json'=>"JSON NULL",'collaboration_rules_json'=>"JSON NULL",
  'quality_standards_json'=>"JSON NULL",'improvement_loop_json'=>"JSON NULL",
  'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('executive_dna_profiles',$c,$d,$changes);}
 if(!m101_index_exists('executive_dna_profiles','executive_key')){try{m101_exec("ALTER TABLE executive_dna_profiles ADD UNIQUE KEY executive_key (executive_key)");$changes[]='executive_dna_profiles unique executive_key';}catch(Throwable $e){}}

 m101_exec("CREATE TABLE IF NOT EXISTS goliath_missions (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'mission_uid'=>"VARCHAR(100) NULL",'title'=>"VARCHAR(255) NULL",'mission_type'=>"VARCHAR(80) NULL",'source'=>"VARCHAR(120) NULL",
  'priority'=>"INT DEFAULT 50",'revenue_potential'=>"DECIMAL(12,2) DEFAULT 0",'status'=>"VARCHAR(60) DEFAULT 'proposed'",
  'owner_executive'=>"VARCHAR(80) DEFAULT 'goliath'",'assigned_executives_json'=>"JSON NULL",'mission_packet_json'=>"JSON NULL",
  'outcome_goal'=>"MEDIUMTEXT NULL",'next_action'=>"MEDIUMTEXT NULL",'due_at'=>"DATETIME NULL",
  'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('goliath_missions',$c,$d,$changes);}
 if(!m101_index_exists('goliath_missions','mission_uid')){try{m101_exec("ALTER TABLE goliath_missions ADD UNIQUE KEY mission_uid (mission_uid)");$changes[]='goliath_missions unique mission_uid';}catch(Throwable $e){}}
 m101_idx('goliath_missions','status','status',$changes); m101_idx('goliath_missions','priority','priority',$changes);

 m101_exec("CREATE TABLE IF NOT EXISTS executive_mission_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'mission_uid'=>"VARCHAR(100) NULL",'executive_key'=>"VARCHAR(80) NULL",'assignment_type'=>"VARCHAR(80) NULL",
  'status'=>"VARCHAR(60) DEFAULT 'assigned'",'instructions'=>"MEDIUMTEXT NULL",'requested_help_json'=>"JSON NULL",
  'output_summary'=>"MEDIUMTEXT NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('executive_mission_assignments',$c,$d,$changes);}
 m101_idx('executive_mission_assignments','mission_uid','mission_uid',$changes); m101_idx('executive_mission_assignments','executive_key','executive_key',$changes);

 m101_exec("CREATE TABLE IF NOT EXISTS executive_initiatives (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'initiative_uid'=>"VARCHAR(100) NULL",'executive_key'=>"VARCHAR(80) NULL",'title'=>"VARCHAR(255) NULL",
  'reason'=>"MEDIUMTEXT NULL",'expected_impact'=>"MEDIUMTEXT NULL",'recommended_mission_packet_json'=>"JSON NULL",
  'status'=>"VARCHAR(60) DEFAULT 'recommended'",'priority'=>"INT DEFAULT 50",
  'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('executive_initiatives',$c,$d,$changes);}
 if(!m101_index_exists('executive_initiatives','initiative_uid')){try{m101_exec("ALTER TABLE executive_initiatives ADD UNIQUE KEY initiative_uid (initiative_uid)");$changes[]='executive_initiatives unique initiative_uid';}catch(Throwable $e){}}
 m101_idx('executive_initiatives','executive_key','executive_key',$changes); m101_idx('executive_initiatives','status','status',$changes); m101_idx('executive_initiatives','priority','priority',$changes);

 m101_exec("CREATE TABLE IF NOT EXISTS executive_council_reports (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'report_uid'=>"VARCHAR(100) NULL",'council_date'=>"DATE NULL",'executive_key'=>"VARCHAR(80) NULL",
  'completed_today'=>"MEDIUMTEXT NULL",'learned_today'=>"MEDIUMTEXT NULL",'recommends_next'=>"MEDIUMTEXT NULL",
  'needs_help'=>"MEDIUMTEXT NULL",'proposed_missions_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('executive_council_reports',$c,$d,$changes);}
 if(!m101_index_exists('executive_council_reports','report_uid')){try{m101_exec("ALTER TABLE executive_council_reports ADD UNIQUE KEY report_uid (report_uid)");$changes[]='executive_council_reports unique report_uid';}catch(Throwable $e){}}
 m101_idx('executive_council_reports','council_date','council_date',$changes); m101_idx('executive_council_reports','executive_key','executive_key',$changes);

 m101_exec("CREATE TABLE IF NOT EXISTS executive_org_memory (
  id INT AUTO_INCREMENT PRIMARY KEY
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'memory_uid'=>"VARCHAR(100) NULL",'scope'=>"VARCHAR(80) NULL",'executive_key'=>"VARCHAR(80) NULL",'memory_type'=>"VARCHAR(80) NULL",
  'title'=>"VARCHAR(255) NULL",'memory_text'=>"MEDIUMTEXT NULL",'evidence_json'=>"JSON NULL",'confidence'=>"INT DEFAULT 70",
  'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){m101_add('executive_org_memory',$c,$d,$changes);}
 if(!m101_index_exists('executive_org_memory','memory_uid')){try{m101_exec("ALTER TABLE executive_org_memory ADD UNIQUE KEY memory_uid (memory_uid)");$changes[]='executive_org_memory unique memory_uid';}catch(Throwable $e){}}
 m101_idx('executive_org_memory','scope','scope',$changes); m101_idx('executive_org_memory','executive_key','executive_key',$changes);

 echo json_encode(['ok'=>true,'version'=>'V101.1 Foundation Migration Engine','changed_count'=>count($changes),'changed'=>$changes,'next'=>'Run executive-organization-core-install-v101-1.php, then executive-initiative-engine-v101-1.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V101.1 Foundation Migration Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>