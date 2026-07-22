<?php
/**
 * V96.1 Executive Boot Installer
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';

  $key=$_GET['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  function vi96_exec($sql){if(function_exists('gdb_exec')) return gdb_exec($sql); $pdo=gdb(); return $pdo->exec($sql);}
  function vi96_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function vi96_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
  function vi96_add($t,$c,$def,&$added,&$skipped){if(!vi96_table($t)){$skipped[]="$t missing";return;} if(vi96_col($t,$c)){$skipped[]="$t.$c exists";return;} vi96_exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");$added[]="$t.$c";}

  $created=[];$added=[];$skipped=[];

  vi96_exec("CREATE TABLE IF NOT EXISTS executive_boot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boot_uid VARCHAR(80) UNIQUE,
    executive_key VARCHAR(80),
    mission_type VARCHAR(120) NULL,
    commission_id INT NULL,
    task_id INT NULL,
    dossier_id INT NULL,
    boot_hash VARCHAR(128),
    identity_file TEXT NULL,
    constitution_files JSON NULL,
    boot_context JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(mission_type),
    INDEX(commission_id),
    INDEX(task_id),
    INDEX(dossier_id),
    INDEX(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_boot_logs';

  vi96_exec("CREATE TABLE IF NOT EXISTS executive_mission_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_uid VARCHAR(80) UNIQUE,
    executive_key VARCHAR(80),
    lead_id INT NULL,
    contact_id INT NULL,
    dossier_id INT NULL,
    commission_id INT NULL,
    task_id INT NULL,
    event_type VARCHAR(120),
    title VARCHAR(255),
    details MEDIUMTEXT,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(lead_id),
    INDEX(contact_id),
    INDEX(dossier_id),
    INDEX(event_type),
    INDEX(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_mission_timeline';

  vi96_exec("CREATE TABLE IF NOT EXISTS executive_mission_context (
    id INT AUTO_INCREMENT PRIMARY KEY,
    context_uid VARCHAR(80) UNIQUE,
    executive_key VARCHAR(80),
    context_type VARCHAR(120),
    related_table VARCHAR(120),
    related_id INT NULL,
    title VARCHAR(255),
    content MEDIUMTEXT,
    context_json JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(executive_key),
    INDEX(context_type),
    INDEX(related_table,related_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $created[]='executive_mission_context';

  vi96_add('scout_intel_dossiers','boot_hash',"VARCHAR(128) NULL",$added,$skipped);
  vi96_add('scout_intel_dossiers','completion_score',"INT DEFAULT 0",$added,$skipped);
  vi96_add('scout_intel_dossiers','personal_intelligence',"MEDIUMTEXT NULL",$added,$skipped);
  vi96_add('scout_intel_dossiers','social_profiles',"JSON NULL",$added,$skipped);
  vi96_add('scout_intel_dossiers','jessica_status',"VARCHAR(80) NULL",$added,$skipped);
  vi96_add('scout_intel_dossiers','sherlock_status',"VARCHAR(80) NULL",$added,$skipped);
  vi96_add('scout_intel_dossiers','einstein_score',"INT DEFAULT 0",$added,$skipped);

  echo json_encode([
    'ok'=>true,
    'version'=>'V96.1 Executive Boot Installer',
    'created'=>$created,
    'added'=>$added,
    'skipped'=>$skipped,
    'next'=>'Run /lead-engine/executive-boot-v96.php?key=timetomakethedonuts&exec=scout to confirm Scout loads his identity and Constitution.',
    'time'=>date('c')
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
  echo json_encode(['ok'=>false,'version'=>'V96.1 Executive Boot Installer','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>