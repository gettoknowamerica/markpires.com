<?php
/**
 * Goliath V77.4.1 — Scout Homeowner Import Fix
 * - Recursively reads /public_html/data
 * - Handles CRSProspectingExport.csv files with DATA_START preamble
 * - Archives agent_master_list_import contacts by default (agent list is not homeowner list)
 * - Uses internal CRM only
 * - Safe inserts local_ai_tasks based on actual table columns
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function scout774_data_dir(){ return realpath(__DIR__.'/../data') ?: (__DIR__.'/../data'); }
function scout774_table($t){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }
function scout774_col($t,$c){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }
function scout774_uid($prefix='src'){ return function_exists('gdb_uid') ? gdb_uid($prefix) : $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)); }
function scout774_json($v){ return json_encode(is_array($v)?$v:[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function scout774_safe_insert($table,$row){
  $safe=[];
  foreach($row as $k=>$v){ if(scout774_col($table,$k)) $safe[$k]=$v; }
  if(!$safe) return null;
  return gdb_insert($table,$safe);
}

function scout774_install(){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'db_not_enabled'];

  gdb_exec("CREATE TABLE IF NOT EXISTS internal_contact_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source_uid VARCHAR(90) NOT NULL UNIQUE,
    source_file VARCHAR(255) NOT NULL,
    source_title VARCHAR(255) NULL,
    source_type VARCHAR(80) NOT NULL DEFAULT 'uploaded_data_file',
    row_count INT NOT NULL DEFAULT 0,
    imported_count INT NOT NULL DEFAULT 0,
    duplicate_count INT NOT NULL DEFAULT 0,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_file (source_file),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  gdb_exec("CREATE TABLE IF NOT EXISTS internal_crm_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contact_uid VARCHAR(90) NOT NULL UNIQUE,
    source_id BIGINT NULL,
    source_file VARCHAR(255) NULL,
    source_row INT NULL,
    owner_name VARCHAR(255) NULL,
    property_address VARCHAR(255) NULL,
    mailing_address VARCHAR(255) NULL,
    town VARCHAR(120) NULL,
    state VARCHAR(40) NULL,
    zip VARCHAR(40) NULL,
    phone_1 VARCHAR(80) NULL,
    phone_2 VARCHAR(80) NULL,
    email_1 VARCHAR(255) NULL,
    email_2 VARCHAR(255) NULL,
    contact_status VARCHAR(60) NOT NULL DEFAULT 'needs_research',
    research_status VARCHAR(60) NOT NULL DEFAULT 'queued',
    phone_confidence INT NOT NULL DEFAULT 0,
    email_confidence INT NOT NULL DEFAULT 0,
    priority_score INT NOT NULL DEFAULT 50,
    compliance_status VARCHAR(80) NOT NULL DEFAULT 'needs_review',
    evidence LONGTEXT NULL,
    notes LONGTEXT NULL,
    raw_data LONGTEXT NULL,
    last_researched_at DATETIME NULL,
    next_research_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_source_row (source_file, source_row),
    KEY idx_research (research_status),
    KEY idx_town (town),
    KEY idx_status (contact_status),
    KEY idx_next (next_research_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Add missing columns/indexes if earlier V77.4 table already exists.
  foreach([
    'contact_uid'=>"VARCHAR(90) NULL",
    'source_id'=>"BIGINT NULL",
    'source_file'=>"VARCHAR(255) NULL",
    'source_row'=>"INT NULL",
    'owner_name'=>"VARCHAR(255) NULL",
    'property_address'=>"VARCHAR(255) NULL",
    'mailing_address'=>"VARCHAR(255) NULL",
    'town'=>"VARCHAR(120) NULL",
    'state'=>"VARCHAR(40) NULL",
    'zip'=>"VARCHAR(40) NULL",
    'phone_1'=>"VARCHAR(80) NULL",
    'phone_2'=>"VARCHAR(80) NULL",
    'email_1'=>"VARCHAR(255) NULL",
    'email_2'=>"VARCHAR(255) NULL",
    'contact_status'=>"VARCHAR(60) NOT NULL DEFAULT 'needs_research'",
    'research_status'=>"VARCHAR(60) NOT NULL DEFAULT 'queued'",
    'phone_confidence'=>"INT NOT NULL DEFAULT 0",
    'email_confidence'=>"INT NOT NULL DEFAULT 0",
    'priority_score'=>"INT NOT NULL DEFAULT 50",
    'compliance_status'=>"VARCHAR(80) NOT NULL DEFAULT 'needs_review'",
    'evidence'=>"LONGTEXT NULL",
    'notes'=>"LONGTEXT NULL",
    'raw_data'=>"LONGTEXT NULL",
    'last_researched_at'=>"DATETIME NULL",
    'next_research_at'=>"DATETIME NULL"
  ] as $c=>$def){
    if(!scout774_col('internal_crm_contacts',$c)){ try{ gdb_exec("ALTER TABLE internal_crm_contacts ADD COLUMN {$c} {$def}"); }catch(Throwable $e){} }
  }
  try{ gdb_exec("UPDATE internal_crm_contacts SET contact_uid=CONCAT('contact_',id,'_',UNIX_TIMESTAMP()) WHERE contact_uid IS NULL OR contact_uid=''"); }catch(Throwable $e){}
  try{ gdb_exec("ALTER TABLE internal_crm_contacts ADD UNIQUE KEY uniq_source_row (source_file, source_row)"); }catch(Throwable $e){}
  try{ gdb_exec("ALTER TABLE internal_crm_contacts ADD KEY idx_research (research_status)"); }catch(Throwable $e){}
  try{ gdb_exec("ALTER TABLE internal_crm_contacts ADD KEY idx_town (town)"); }catch(Throwable $e){}

  gdb_exec("CREATE TABLE IF NOT EXISTS scout_research_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    batch_uid VARCHAR(90) NOT NULL UNIQUE,
    batch_type VARCHAR(80) NOT NULL DEFAULT 'contact_research',
    status VARCHAR(40) NOT NULL DEFAULT 'queued',
    total_contacts INT NOT NULL DEFAULT 0,
    assigned_count INT NOT NULL DEFAULT 0,
    completed_count INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    instructions LONGTEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  return ['ok'=>true,'tables'=>[
    'internal_contact_sources'=>scout774_table('internal_contact_sources'),
    'internal_crm_contacts'=>scout774_table('internal_crm_contacts'),
    'scout_research_batches'=>scout774_table('scout_research_batches')
  ]];
}

function scout774_data_files(){
  $dir=scout774_data_dir(); $out=[];
  if(!is_dir($dir)) return $out;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach($it as $f){
    if(!$f->isFile()) continue;
    $ext=strtolower(pathinfo($f->getFilename(),PATHINFO_EXTENSION));
    if(!in_array($ext,['csv','txt','tsv'],true)) continue;
    $rel=str_replace('\\','/',substr($f->getPathname(),strlen($dir)+1));
    $out[]=['file'=>$rel,'path'=>$f->getPathname(),'ext'=>$ext,'size'=>$f->getSize(),'modified'=>date('c',$f->getMTime())];
  }
  usort($out,function($a,$b){return strcmp($a['file'],$b['file']);});
  return $out;
}
function scout774_normalize_header($h){ $h=strtolower(trim((string)$h)); $h=preg_replace('/[^a-z0-9]+/','_',$h); return trim($h,'_'); }
function scout774_pick($row,$keys){ foreach($keys as $k){ if(isset($row[$k]) && trim((string)$row[$k])!=='') return trim((string)$row[$k]); } return null; }
function scout774_is_agent_file($file,$headers){
  $f=strtolower($file); $h=implode('|',$headers);
  return strpos($f,'agent_master')!==false || (strpos($h,'office_name')!==false && strpos($h,'mls_agent_type')!==false);
}
function scout774_is_homeowner_file($file,$headers){
  $h=implode('|',$headers); $f=strtolower($file);
  return strpos($f,'crsprospectingexport')!==false || strpos($h,'property_address')!==false || strpos($h,'owner_address')!==false || strpos($h,'parcel_id')!==false;
}
function scout774_read_csv_rows($path){
  $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION)); $delim=($ext==='tsv') ? "\t" : ',';
  $lines=file($path, FILE_IGNORE_NEW_LINES);
  if(!$lines) return ['headers'=>[],'rows'=>[],'data_start_line'=>0];

  $start=0;
  foreach($lines as $i=>$line){
    if(trim($line,'" ') === 'DATA_START'){ $start=$i+1; break; }
  }
  $csv=implode("\n",array_slice($lines,$start));
  $fh=fopen('php://temp','r+'); fwrite($fh,$csv); rewind($fh);
  $header=fgetcsv($fh,0,$delim);
  if(!$header){ fclose($fh); return ['headers'=>[],'rows'=>[],'data_start_line'=>$start];}
  $headers=array_map('scout774_normalize_header',$header);
  $rows=[]; $rowNum=$start+1;
  while(($cols=fgetcsv($fh,0,$delim))!==false){
    $rowNum++;
    $assoc=[];
    foreach($headers as $i=>$h){$assoc[$h]=$cols[$i]??'';}
    $assoc['_source_row']=$rowNum;
    $rows[]=$assoc;
  }
  fclose($fh);
  return ['headers'=>$headers,'rows'=>$rows,'data_start_line'=>$start];
}
function scout774_parse_name($row){
  $name=scout774_pick($row,['owner_1','owner_name','owner','name','full_name','property_owner','seller','contact_name']);
  if($name) return $name;
  $first=scout774_pick($row,['first_name','firstname','first']);
  $middle=scout774_pick($row,['middle','middle_name']);
  $last=scout774_pick($row,['last_name','lastname','last']);
  return trim(($first?:'').' '.($middle?:'').' '.($last?:'')) ?: null;
}
function scout774_parse_address($row){ return scout774_pick($row,['property_address','situs_address','site_address','listing_address','street_address','property']); }
function scout774_parse_mailing($row){ return scout774_pick($row,['owner_address','mailing_address','mail_address','mailing']); }
function scout774_parse_town($row){ return scout774_pick($row,['property_city','town','city','municipality','owner_city']); }
function scout774_parse_state($row){ return scout774_pick($row,['property_state','state','st','owner_state']); }
function scout774_parse_zip($row){ return scout774_pick($row,['property_zip','zip','zipcode','postal_code','owner_zip']); }

function scout774_import_file($path,$opts=[]){
  scout774_install();
  if(!is_file($path)) return ['ok'=>false,'error'=>'file_missing','path'=>$path];

  $rel=str_replace('\\','/',substr($path,strlen(scout774_data_dir())+1));
  $parsed=scout774_read_csv_rows($path);
  $headers=$parsed['headers']; $rows=$parsed['rows'];
  if(!$headers) return ['ok'=>false,'file'=>$rel,'error'=>'no_headers'];

  $include_agents=!empty($opts['include_agents']);
  $isAgent=scout774_is_agent_file($rel,$headers);
  $isHome=scout774_is_homeowner_file($rel,$headers);

  if($isAgent && !$include_agents){
    // Archive previously imported agent rows so they don't pollute Scout homeowner queue.
    try{ gdb_exec("UPDATE internal_crm_contacts SET contact_status='archived_agent_list', research_status='archived', notes=CONCAT(COALESCE(notes,''), '\nArchived by V77.4.1: agent master list is not homeowner prospect list.') WHERE source_file=?",[$rel]); }catch(Throwable $e){}
    return ['ok'=>true,'file'=>$rel,'skipped'=>true,'reason'=>'agent_master_list_not_homeowner_source','headers'=>$headers,'rows_seen'=>count($rows)];
  }
  if(!$isHome && !$include_agents){
    return ['ok'=>true,'file'=>$rel,'skipped'=>true,'reason'=>'not_detected_as_homeowner_property_file','headers'=>$headers,'rows_seen'=>count($rows)];
  }

  $source=gdb_one("SELECT * FROM internal_contact_sources WHERE source_file=? LIMIT 1",[$rel]);
  if(!$source){
    $sourceId=gdb_insert('internal_contact_sources',[
      'source_uid'=>scout774_uid('source'),
      'source_file'=>$rel,
      'source_title'=>preg_replace('/\.[^.]+$/','',basename($rel)),
      'source_type'=>'data_folder_homeowner_upload',
      'status'=>'active',
      'metadata'=>scout774_json(['path'=>'/data/'.$rel,'headers'=>$headers,'data_start_line'=>$parsed['data_start_line']])
    ]);
  } else { $sourceId=(int)$source['id']; }

  $imported=0; $dupes=0; $errors=[]; $seen=0;
  foreach($rows as $assoc){
    $seen++;
    $owner=scout774_parse_name($assoc);
    $addr=scout774_parse_address($assoc);
    if(!$owner && !$addr) continue;

    $sourceRow=(int)($assoc['_source_row']??($seen+1));
    $data=[
      'contact_uid'=>scout774_uid('contact'),
      'source_id'=>$sourceId,
      'source_file'=>$rel,
      'source_row'=>$sourceRow,
      'owner_name'=>$owner,
      'property_address'=>$addr,
      'mailing_address'=>scout774_parse_mailing($assoc),
      'town'=>scout774_parse_town($assoc),
      'state'=>scout774_parse_state($assoc),
      'zip'=>scout774_parse_zip($assoc),
      'phone_1'=>scout774_pick($assoc,['phone','phone_1','primary_phone','cell','mobile']),
      'phone_2'=>scout774_pick($assoc,['phone_2','secondary_phone','alt_phone']),
      'email_1'=>scout774_pick($assoc,['email','email_1','primary_email']),
      'email_2'=>scout774_pick($assoc,['email_2','secondary_email']),
      'contact_status'=>'needs_research',
      'research_status'=>'queued',
      'compliance_status'=>'needs_review',
      'evidence'=>'Imported from /data/'.$rel.' row '.$sourceRow,
      'raw_data'=>scout774_json($assoc),
      'next_research_at'=>date('Y-m-d H:i:s')
    ];
    try{ gdb_insert('internal_crm_contacts',$data); $imported++; }
    catch(Throwable $e){ if(stripos($e->getMessage(),'Duplicate')!==false){$dupes++;} else {$errors[]=['row'=>$sourceRow,'error'=>$e->getMessage()];} }
  }

  try{ gdb_update('internal_contact_sources',['row_count'=>$seen,'imported_count'=>$imported,'duplicate_count'=>$dupes,'updated_at'=>gdb_now()],'id=:id',['id'=>$sourceId]); }catch(Throwable $e){}

  return ['ok'=>true,'source_id'=>$sourceId,'file'=>$rel,'homeowner_file'=>$isHome,'rows_seen'=>$seen,'imported'=>$imported,'duplicates'=>$dupes,'errors'=>array_slice($errors,0,10),'headers'=>$headers];
}
function scout774_import_all_data_files($opts=[]){
  $results=[];
  foreach(scout774_data_files() as $f){ $results[]=scout774_import_file($f['path'],$opts); }
  return $results;
}
function scout774_create_batch($limit=75){
  scout774_install();
  $limit=max(1,min(250,(int)$limit));
  $contacts=gdb_all("SELECT * FROM internal_crm_contacts
    WHERE research_status IN ('queued','needs_research','retry')
      AND contact_status <> 'archived_agent_list'
      AND property_address IS NOT NULL AND property_address <> ''
      AND (next_research_at IS NULL OR next_research_at <= NOW())
    ORDER BY priority_score DESC, id ASC
    LIMIT {$limit}");

  if(!$contacts) return ['ok'=>true,'message'=>'no_contacts_ready','batch_id'=>null,'task_id'=>null,'count'=>0];

  $batchId=gdb_insert('scout_research_batches',[
    'batch_uid'=>scout774_uid('batch'),
    'batch_type'=>'contact_research',
    'status'=>'queued',
    'total_contacts'=>count($contacts),
    'assigned_count'=>count($contacts),
    'title'=>'Scout contact enrichment batch: '.count($contacts).' homeowners from /data',
    'instructions'=>'Scout must use public/reputable sources and approved plugins to find legally usable phone numbers/emails. No fabricated contacts. Save results only to internal_crm_contacts.',
    'metadata'=>scout774_json(['contact_ids'=>array_map(fn($c)=>(int)$c['id'],$contacts)])
  ]);

  $ids=array_map(fn($c)=>(int)$c['id'],$contacts);
  if($ids){ gdb_exec("UPDATE internal_crm_contacts SET research_status='assigned', updated_at=NOW() WHERE id IN (".implode(',',array_fill(0,count($ids),'?')).")",$ids); }

  $lines=[];
  foreach($contacts as $c){
    $lines[]=[
      'contact_id'=>(int)$c['id'],
      'owner_name'=>$c['owner_name'],
      'property_address'=>$c['property_address'],
      'mailing_address'=>$c['mailing_address'],
      'town'=>$c['town'],
      'state'=>$c['state'],
      'zip'=>$c['zip'],
      'existing_phone'=>$c['phone_1'],
      'existing_email'=>$c['email_1'],
      'evidence'=>$c['evidence']
    ];
  }

  $prompt="YOU ARE SCOUT.\n\nMISSION: Enrich this batch of expired/never-sold homeowner/property records from Mark's /data folder. Find verified phone numbers and emails where legally usable. Cross-check owner names and property addresses against public information. Save results to the internal CRM only.\n\nSTRICT RULES:\n1. Do not invent phone numbers, emails, owners, addresses, or sources.\n2. Every phone/email needs evidence: source URL, public record source, uploaded row ID, or internal record ID.\n3. Respect compliance. If uncertain, mark NEEDS_COMPLIANCE_REVIEW.\n4. If online tool access is unavailable, return NEEDS_TOOL_ACCESS and name the needed tool.\n5. Output a CSV-style result and update path/record IDs.\n\nREQUIRED OUTPUT:\nASSET_TYPE: verified_lead_csv\nEXECUTIVE: Scout\nBUSINESS_GOAL: Create callable/email-ready expired homeowner leads for Mark's internal CRM.\nACTIONABLE_ASSET: Internal CRM contact enrichment batch #{$batchId}\nEVIDENCE: source URLs / internal_crm_contacts IDs\nCLICKABLE_OUTPUTS: /dashboard/scout-contact-workspace.php?batch_id={$batchId}\nHANDOFFS: Jessica Gregory receives verified contacts for human-touch outreach.\nNEXT_ACTION: Mark reviews verified contacts and begins priority calls.\n\nCONTACT_BATCH_JSON:\n".json_encode($lines,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

  $taskId=null;
  if(scout774_table('local_ai_tasks')){
    try{
      $taskId=scout774_safe_insert('local_ai_tasks',[
        'task_uid'=>scout774_uid('lat'),
        'commission_id'=>null,
        'agent'=>'Scout',
        'task_type'=>'scout_internal_crm_contact_research',
        'model'=>'goliath-local-worker',
        'prompt'=>$prompt,
        'status'=>'queued',
        'priority'=>260,
        'progress'=>0,
        'result'=>null,
        'metadata'=>scout774_json(['batch_id'=>$batchId,'contact_ids'=>$ids,'source'=>'/data']),
        'created_at'=>gdb_now(),
        'updated_at'=>gdb_now()
      ]);
    }catch(Throwable $e){ $taskId=null; }
  }

  return ['ok'=>true,'batch_id'=>$batchId,'task_id'=>$taskId,'count'=>count($contacts),'contacts'=>array_slice($lines,0,5)];
}
?>