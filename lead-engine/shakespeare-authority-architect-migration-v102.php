<?php
/**
 * V102.0 Shakespeare Authority Architect Migration
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function sx_db(){return gdb();}
 function sx_exec($sql){return sx_db()->exec($sql);}
 function sx_one($sql,$p=[]){try{return gdb_one($sql,$p)?:null;}catch(Throwable $e){return null;}}
 function sx_table($t){$r=sx_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
 function sx_col($t,$c){$r=sx_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
 function sx_add($t,$c,$def,&$changes){if(!sx_col($t,$c)){sx_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");$changes[]="$t.$c";}}
 function sx_idx($t,$idx,$cols,&$changes){$r=sx_one("SELECT COUNT(*) c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?",[$t,$idx]);if(!((int)($r['c']??0))){try{sx_exec("ALTER TABLE `$t` ADD INDEX `$idx` ($cols)");$changes[]="$t index $idx";}catch(Throwable $e){}}}
 $changes=[];

 sx_exec("CREATE TABLE IF NOT EXISTS shakespeare_authority_clusters (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'cluster_uid'=>"VARCHAR(100) NULL",'cluster_type'=>"VARCHAR(80) NULL",'cluster_name'=>"VARCHAR(180) NULL",'town'=>"VARCHAR(120) NULL",
  'topic'=>"VARCHAR(180) NULL",'audience'=>"VARCHAR(180) NULL",'status'=>"VARCHAR(60) DEFAULT 'active'",
  'authority_score'=>"INT DEFAULT 0",'pages_json'=>"JSON NULL",'internal_links_json'=>"JSON NULL",'missing_pages_json'=>"JSON NULL",
  'next_action'=>"MEDIUMTEXT NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){sx_add('shakespeare_authority_clusters',$c,$d,$changes);}
 sx_idx('shakespeare_authority_clusters','cluster_uid','cluster_uid',$changes); sx_idx('shakespeare_authority_clusters','cluster_type','cluster_type',$changes); sx_idx('shakespeare_authority_clusters','status','status',$changes);

 sx_exec("CREATE TABLE IF NOT EXISTS shakespeare_campaign_packages (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'package_uid'=>"VARCHAR(100) NULL",'mission_uid'=>"VARCHAR(100) NULL",'source'=>"VARCHAR(120) NULL",
  'title'=>"VARCHAR(255) NULL",'slug'=>"VARCHAR(255) NULL",'audience'=>"VARCHAR(180) NULL",'scenario'=>"VARCHAR(120) NULL",'town'=>"VARCHAR(120) NULL",
  'status'=>"VARCHAR(60) DEFAULT 'draft'",'authority_score'=>"INT DEFAULT 0",'research_score'=>"INT DEFAULT 0",'seo_score'=>"INT DEFAULT 0",
  'story_score'=>"INT DEFAULT 0",'visual_score'=>"INT DEFAULT 0",'conversion_score'=>"INT DEFAULT 0",'verification_score'=>"INT DEFAULT 0",
  'article_html'=>"MEDIUMTEXT NULL",'article_summary'=>"MEDIUMTEXT NULL",'faq_json'=>"JSON NULL",'email_json'=>"JSON NULL",
  'social_json'=>"JSON NULL",'video_brief_json'=>"JSON NULL",'visual_requests_json'=>"JSON NULL",'seo_json'=>"JSON NULL",
  'schema_json'=>"JSON NULL",'internal_links_json'=>"JSON NULL",'sherlock_request_json'=>"JSON NULL",'scorsese_request_json'=>"JSON NULL",
  'jessica_request_json'=>"JSON NULL",'einstein_request_json'=>"JSON NULL",'pandora_request_json'=>"JSON NULL",
  'next_opportunities_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){sx_add('shakespeare_campaign_packages',$c,$d,$changes);}
 sx_idx('shakespeare_campaign_packages','package_uid','package_uid',$changes); sx_idx('shakespeare_campaign_packages','status','status',$changes); sx_idx('shakespeare_campaign_packages','scenario','scenario',$changes); sx_idx('shakespeare_campaign_packages','town','town',$changes);

 sx_exec("CREATE TABLE IF NOT EXISTS shakespeare_content_gaps (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 foreach([
  'gap_uid'=>"VARCHAR(100) NULL",'gap_type'=>"VARCHAR(80) NULL",'title'=>"VARCHAR(255) NULL",'town'=>"VARCHAR(120) NULL",'topic'=>"VARCHAR(180) NULL",
  'reason'=>"MEDIUMTEXT NULL",'recommended_url'=>"VARCHAR(255) NULL",'priority'=>"INT DEFAULT 50",'status'=>"VARCHAR(60) DEFAULT 'open'",
  'recommended_team_json'=>"JSON NULL",'created_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP",'updated_at'=>"DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
 ] as $c=>$d){sx_add('shakespeare_content_gaps',$c,$d,$changes);}
 sx_idx('shakespeare_content_gaps','gap_uid','gap_uid',$changes); sx_idx('shakespeare_content_gaps','status','status',$changes); sx_idx('shakespeare_content_gaps','priority','priority',$changes);

 echo json_encode(['ok'=>true,'version'=>'V102.0 Shakespeare Authority Architect Migration','changed_count'=>count($changes),'changed'=>$changes,'next'=>'Run shakespeare-authority-architect-v102.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V102.0 Shakespeare Authority Architect Migration','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>