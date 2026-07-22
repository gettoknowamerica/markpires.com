<?php
/**
 * Goliath Omni V75.5 — Comfy Production Pipeline Bridge
 * Primary table: goliath_comfy_jobs
 * Compatibility: can migrate/seed from Scorsese text completions.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if (file_exists(__DIR__.'/goliath-action-ledger.php')) require_once __DIR__.'/goliath-action-ledger.php';

function gc55_expected_key(){ return defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts'; }
function gc55_in(){
  $raw=file_get_contents('php://input');
  $json=json_decode($raw,true);
  $in=[];
  if(is_array($_GET)) $in=array_merge($in,$_GET);
  if(is_array($_POST)) $in=array_merge($in,$_POST);
  if($raw && !is_array($json)){ $p=[]; parse_str($raw,$p); if(is_array($p)) $in=array_merge($in,$p); }
  if(is_array($json)) $in=array_merge($in,$json);
  return [$in,$raw];
}
function gc55_key_ok($in=null){
  if($in===null){ [$in,$raw]=gc55_in(); }
  $key=$in['key']??'';
  return hash_equals(gc55_expected_key(),(string)$key);
}
function gc55_out($arr,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
function gc55_table($table){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]); return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }
function gc55_col($table,$col){ try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$table,$col]); return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;} }
function gc55_json($v){ return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function gc55_trunc($s,$n=64000){ $s=(string)$s; return mb_strlen($s)>$n ? mb_substr($s,0,$n)."\n\n[Truncated by V75.5]" : $s; }

function gc55_install(){
  if(!gdb_enabled()) return false;
  $sql="CREATE TABLE IF NOT EXISTS `goliath_comfy_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_uid` VARCHAR(120) NULL,
    `source_completion_id` INT NULL,
    `source_commission_id` INT NULL,
    `executive` VARCHAR(80) DEFAULT 'Scorsese',
    `title` VARCHAR(255) NULL,
    `prompt` LONGTEXT NULL,
    `negative_prompt` TEXT NULL,
    `workflow_json` LONGTEXT NULL,
    `media_type` VARCHAR(40) DEFAULT 'image',
    `status` VARCHAR(40) DEFAULT 'queued',
    `priority` INT DEFAULT 80,
    `progress` INT DEFAULT 0,
    `comfy_prompt_id` VARCHAR(160) NULL,
    `output_url` TEXT NULL,
    `output_path` TEXT NULL,
    `output_type` VARCHAR(40) NULL,
    `thumbnail_url` TEXT NULL,
    `notes` LONGTEXT NULL,
    `error_message` TEXT NULL,
    `metadata` LONGTEXT NULL,
    `claimed_at` DATETIME NULL,
    `submitted_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    KEY `idx_status` (`status`),
    KEY `idx_source_completion` (`source_completion_id`),
    KEY `idx_source_commission` (`source_commission_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  gdb_exec($sql);
  // Add missing columns for older installs.
  $cols=[
    'negative_prompt'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `negative_prompt` TEXT NULL",
    'media_type'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `media_type` VARCHAR(40) DEFAULT 'image'",
    'comfy_prompt_id'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `comfy_prompt_id` VARCHAR(160) NULL",
    'thumbnail_url'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `thumbnail_url` TEXT NULL",
    'error_message'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `error_message` TEXT NULL",
    'submitted_at'=>"ALTER TABLE `goliath_comfy_jobs` ADD COLUMN `submitted_at` DATETIME NULL"
  ];
  foreach($cols as $c=>$sql){ if(!gc55_col('goliath_comfy_jobs',$c)){ try{ gdb_exec($sql); }catch(Throwable $e){} } }
  return true;
}

function gc55_health(){
  gc55_install();
  $tables=['goliath_comfy_jobs','goliath_worker_completions','executive_commissions','goliath_review_queue','goliath_notifications'];
  $out=[]; foreach($tables as $t) $out[$t]=gc55_table($t);
  $counts=[];
  if(gdb_enabled() && gc55_table('goliath_comfy_jobs')){
    foreach(['queued','working','submitted','rendering','complete','completed','failed','needs_workflow'] as $s){
      $counts[$s]=(int)((gdb_one("SELECT COUNT(*) c FROM goliath_comfy_jobs WHERE status=?",[$s])?:['c'=>0])['c']);
    }
    $counts['today_complete']=(int)((gdb_one("SELECT COUNT(*) c FROM goliath_comfy_jobs WHERE status IN ('complete','completed') AND DATE(COALESCE(completed_at,updated_at,created_at))=CURRENT_DATE")?:['c'=>0])['c']);
  }
  return ['ok'=>gdb_enabled() && gc55_table('goliath_comfy_jobs'),'version'=>'V75.5','tables'=>$out,'counts'=>$counts,'time'=>date('c')];
}

function gc55_seed_from_scorsese($limit=25){
  gc55_install();
  if(!gdb_enabled() || !gc55_table('goliath_worker_completions') || !gc55_table('goliath_comfy_jobs')) return 0;
  $rows=gdb_all("SELECT * FROM goliath_worker_completions wc
    WHERE LOWER(wc.executive)='scorsese'
      AND NOT EXISTS (SELECT 1 FROM goliath_comfy_jobs j WHERE j.source_completion_id=wc.id)
    ORDER BY wc.created_at DESC LIMIT ".(int)$limit);
  $made=0;
  foreach($rows as $r){
    $output=(string)($r['output']??'');
    $title=(string)($r['title']??'Scorsese Media Render');
    // Only convert true production briefs into render jobs.
    $hay=strtolower($title.' '.$output);
    if(!preg_match('/comfy|render|video|thumbnail|image|cinematic|asset|media|reel|short|visual|poster/',$hay)) continue;
    $prompt="Create the actual visual media asset requested by Scorsese.\n\nTITLE:\n{$title}\n\nSCORSESE PRODUCTION BRIEF:\n{$output}\n\nReturn a polished review-ready image/video asset for Mark Pires. Style: cinematic, premium, vivid, high-conversion, brand-safe.";
    $metadata=['source'=>'v75_5_seed_from_scorsese_completion','completion_id'=>$r['id']??null,'commission_id'=>$r['commission_id']??null];
    gdb_insert('goliath_comfy_jobs',[
      'job_uid'=>gdb_uid('comfy'),
      'source_completion_id'=>$r['id']??null,
      'source_commission_id'=>$r['commission_id']??null,
      'executive'=>'Scorsese',
      'title'=>$title,
      'prompt'=>$prompt,
      'media_type'=>'image',
      'status'=>'queued',
      'priority'=>95,
      'progress'=>0,
      'metadata'=>gc55_json($metadata),
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
    ]);
    $made++;
  }
  return $made;
}

function gc55_pull(){
  gc55_install();
  $job=gdb_one("SELECT * FROM goliath_comfy_jobs
    WHERE status IN ('queued','retry','needs_workflow')
    ORDER BY priority DESC, created_at ASC, id ASC LIMIT 1");
  if(!$job) return null;
  gdb_update('goliath_comfy_jobs',[
    'status'=>'working','progress'=>10,'claimed_at'=>gdb_now(),'updated_at'=>gdb_now()
  ],'id=:id',['id'=>(int)$job['id']]);
  return gdb_one('SELECT * FROM goliath_comfy_jobs WHERE id=? LIMIT 1',[(int)$job['id']]);
}

function gc55_update($in){
  gc55_install();
  $id=(int)($in['id']??($in['job_id']??0));
  if(!$id) return ['success'=>false,'error'=>'missing_job_id','received_keys'=>array_keys($in)];
  $job=gdb_one('SELECT * FROM goliath_comfy_jobs WHERE id=? LIMIT 1',[$id]);
  if(!$job) return ['success'=>false,'error'=>'job_not_found','job_id'=>$id];

  $status=(string)($in['status']??'complete');
  $outputUrl=(string)($in['output_url']??($in['file_url']??''));
  $outputPath=(string)($in['output_path']??($in['file_path']??''));
  $thumb=(string)($in['thumbnail_url']??'');
  $notes=gc55_trunc((string)($in['notes']??($in['result']??'')),32000);
  $error=(string)($in['error_message']??($in['error']??''));
  $promptId=(string)($in['comfy_prompt_id']??($in['prompt_id']??''));
  $progress=(int)($in['progress']??(in_array($status,['complete','completed'])?100:50));
  $u=$outputUrl ?: $outputPath;
  $type='text';
  if(preg_match('/\.(mp4|mov|webm|m4v)(\?|$)/i',$u)) $type='video';
  elseif(preg_match('/\.(png|jpg|jpeg|webp|gif|svg)(\?|$)/i',$u)) $type='image';

  $row=[
    'status'=>$status,
    'progress'=>max(0,min(100,$progress)),
    'output_url'=>$outputUrl?:null,
    'output_path'=>$outputPath?:null,
    'output_type'=>$type,
    'thumbnail_url'=>$thumb?:null,
    'notes'=>$notes,
    'error_message'=>$error?:null,
    'comfy_prompt_id'=>$promptId?:($job['comfy_prompt_id']??null),
    'updated_at'=>gdb_now()
  ];
  if(in_array($status,['complete','completed','failed'])) $row['completed_at']=gdb_now();
  if(in_array($status,['submitted','rendering'])) $row['submitted_at']=gdb_now();
  gdb_update('goliath_comfy_jobs',$row,'id=:id',['id'=>$id]);

  $reviewId=null;
  if(in_array($status,['complete','completed']) && gc55_table('goliath_review_queue')){
    try{
      $reviewId=gdb_insert('goliath_review_queue',[
        'review_uid'=>gdb_uid('review'),
        'executive'=>'Scorsese',
        'source_type'=>'goliath_comfy_media',
        'source_id'=>(string)$id,
        'title'=>$job['title']??'Scorsese media asset',
        'summary'=>$notes ?: ('ComfyUI media ready: '.$u),
        'review_status'=>'ready',
        'recommended_action'=>'Preview this generated Scorsese media asset. Approve, request revisions, or send to publishing.',
        'review_url'=>'/dashboard/scorsese-media-center.php?job='.(int)$id,
        'metadata'=>gc55_json(['comfy_job_id'=>$id,'output_url'=>$outputUrl,'output_path'=>$outputPath,'thumbnail_url'=>$thumb,'source'=>'v75_5_comfy_pipeline'])
      ]);
    }catch(Throwable $e){}
  }
  if(function_exists('gal_notify')) @gal_notify('Scorsese','ComfyUI media ready',$job['title']??'Scorsese media asset','high','/dashboard/scorsese-media-center.php?job='.(int)$id,['comfy_job_id'=>$id,'review_id'=>$reviewId]);
  if(function_exists('gal_action')) @gal_action('Scorsese','comfy_media_completed',$job['title']??'Scorsese media asset',$notes ?: 'ComfyUI returned a media asset.','complete',100,['comfy_job_id'=>$id,'output_url'=>$outputUrl,'output_path'=>$outputPath,'review_id'=>$reviewId]);
  return ['success'=>true,'job_id'=>$id,'status'=>$status,'review_id'=>$reviewId,'output_type'=>$type,'output_url'=>$outputUrl,'output_path'=>$outputPath,'message'=>'Scorsese media asset attached and ready for review.'];
}
?>