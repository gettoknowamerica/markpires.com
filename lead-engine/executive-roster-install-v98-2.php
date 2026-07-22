<?php
/**
 * V98.2 Executive Roster + Shakespeare Workspace Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function ex982_exec($sql){if(function_exists('gdb_exec'))return gdb_exec($sql);$pdo=gdb();return $pdo->exec($sql);}
 function ex982_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function ex982_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ex982_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ex982_add($t,$c,$def,&$added,&$skipped){if(!ex982_table($t)){$skipped[]="$t missing";return;}if(ex982_col($t,$c)){$skipped[]="$t.$c exists";return;}ex982_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");$added[]="$t.$c";}
 function ex982_ins($t,$row){$safe=[];foreach($row as $k=>$v){if(ex982_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}

 $created=[];$added=[];$skipped=[];
 ex982_exec("CREATE TABLE IF NOT EXISTS executive_registry (
  id INT AUTO_INCREMENT PRIMARY KEY,
  executive_key VARCHAR(80) UNIQUE,
  display_name VARCHAR(120),
  title VARCHAR(255),
  mission MEDIUMTEXT,
  constitution_file TEXT NULL,
  identity_file TEXT NULL,
  workspace_url TEXT NULL,
  status VARCHAR(80) DEFAULT 'active',
  maturity_score INT DEFAULT 50,
  current_focus VARCHAR(255) NULL,
  handoff_to VARCHAR(255) NULL,
  capabilities_json JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status),
  INDEX(maturity_score)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='executive_registry';

 ex982_exec("CREATE TABLE IF NOT EXISTS executive_initiatives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  initiative_uid VARCHAR(80) UNIQUE,
  executive_key VARCHAR(80),
  title VARCHAR(255),
  business_goal VARCHAR(255),
  recommendation MEDIUMTEXT,
  proposed_next_action MEDIUMTEXT,
  priority INT DEFAULT 100,
  status VARCHAR(80) DEFAULT 'proposed',
  source_type VARCHAR(120) NULL,
  source_id INT NULL,
  metadata JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(executive_key),
  INDEX(status),
  INDEX(priority)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='executive_initiatives';

 ex982_exec("CREATE TABLE IF NOT EXISTS goliath_social_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  social_uid VARCHAR(80) UNIQUE,
  source_table VARCHAR(120),
  source_id INT NULL,
  title VARCHAR(255),
  content_type VARCHAR(120),
  target_channels VARCHAR(255),
  caption_json JSON NULL,
  asset_url TEXT NULL,
  status VARCHAR(80) DEFAULT 'queued',
  priority INT DEFAULT 100,
  created_by VARCHAR(80) DEFAULT 'shakespeare',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status),
  INDEX(source_table,source_id),
  INDEX(created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $created[]='goliath_social_queue';

 ex982_add('shakespeare_content_packages','viewer_notes',"MEDIUMTEXT NULL",$added,$skipped);
 ex982_add('shakespeare_content_packages','social_status',"VARCHAR(80) DEFAULT 'not_queued'",$added,$skipped);
 ex982_add('shakespeare_content_packages','social_queue_id',"INT NULL",$added,$skipped);
 ex982_add('shakespeare_content_packages','last_viewed_at',"DATETIME NULL",$added,$skipped);

 $execs=[
  ['goliath','Goliath','Chief Executive Operating System','Orchestrates priorities, handoffs, daily briefings, bottlenecks, and executive health.','/dashboard/goliath-mobile-command.php',90,'Scout,Jessica,Shakespeare,Einstein,Scorsese,Sherlock,Pandora,Mozart'],
  ['scout','Scout','Verified Lead Discovery and Contact Intelligence Director','Discovers opportunities, merges internal data, enriches contact intelligence through OpenClaw, and delivers call-ready dossiers.','/dashboard/scout-ready-contacts.php',90,'Sherlock,Einstein,Jessica'],
  ['jessica','Jessica','Chief Relationship and Human Touch Officer','Owns client follow-up, email drafts, appointment confirmations, reminders, relationship memory, and Human Touch.','/dashboard/jessica-relationship-center.php',82,'Mark,Shakespeare,Scout'],
  ['shakespeare','Shakespeare','Authority Builder and Content Publisher','Creates scenario articles, town authority hubs, Jessica content assets, and publish-ready authority packages.','/dashboard/shakespeare-authority-center.php',60,'Einstein,Jessica,Goliath Social,Scorsese'],
  ['einstein','Einstein','Chief Intelligence and Asset Compounding Officer','Scores SEO/AEO, campaigns, opportunity priority, analytics, missing schema, and compounding asset improvements.','/dashboard/einstein-intelligence-center.php',25,'Shakespeare,Goliath,Jessica'],
  ['scorsese','Scorsese','Cinema and Media Production Director','Turns listings, blogs, stories, and ideas into video, thumbnails, visuals, social clips, and premium media.','/dashboard/scorsese-studio-pro.php',75,'Goliath Social,Pandora,Shakespeare'],
  ['sherlock','Sherlock','Strategy and Verification Executive','Verifies facts, identifies duplicates, detects risk, tests assumptions, and proposes business opportunities.','/dashboard/sherlock-strategy-lab.php',15,'Scout,Einstein,Goliath'],
  ['pandora','Pandora','Brand and Visual Design Director','Owns visual identity, layouts, thumbnails, landing pages, graphics, print pieces, and brand consistency.','/dashboard/pandora-design-studio.php',10,'Scorsese,Shakespeare,Goliath Social'],
  ['mozart','Mozart','Audio, Voice, and Music Director','Owns audio polish, voice consistency, music beds, BeatSeat assets, podcasts, intros, outros, and sound identity.','/dashboard/mozart-audio-studio.php',10,'Scorsese,Goliath Social']
 ];
 foreach($execs as $e){
   $exists=gdb_one("SELECT id FROM executive_registry WHERE executive_key=? LIMIT 1",[$e[0]]);
   $row=['executive_key'=>$e[0],'display_name'=>$e[1],'title'=>$e[2],'mission'=>$e[3],'workspace_url'=>$e[4],'status'=>'active','maturity_score'=>$e[5],'current_focus'=>'Reach production-ready 80–85% baseline, then refine to super-agent level.','handoff_to'=>$e[6],'capabilities_json'=>json_encode(['boot'=>'constitution_identity_mission','handoff_to'=>explode(',',$e[6])]),'updated_at'=>gdb_now()];
   if($exists) gdb_update('executive_registry',$row,'id=:id',['id'=>(int)$exists['id']]);
   else { $row['created_at']=gdb_now(); ex982_ins('executive_registry',$row); }
 }

 echo json_encode(['ok'=>true,'version'=>'V98.2 Executive Roster + Shakespeare Workspace Installer','created'=>$created,'added'=>$added,'skipped'=>$skipped,'seeded_executives'=>count($execs),'next'=>'Open /dashboard/executive-roster.php and /dashboard/shakespeare-authority-center.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.2 Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>