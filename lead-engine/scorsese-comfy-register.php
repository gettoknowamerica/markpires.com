<?php
/**
 * Goliath V80 — Scorsese Media Register
 * Makes ComfyUI outputs visible in Goliath as playable deliverables.
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');

function sr_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function sr_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function sr_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function sr_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function sr_insert($table,$row){$safe=[];foreach($row as $k=>$v){if(sr_col($table,$k))$safe[$k]=$v;}return $safe?gdb_insert($table,$safe):null;}
function sr_update($table,$id,$row){$safe=[];foreach($row as $k=>$v){if(sr_col($table,$k))$safe[$k]=$v;}if($safe)gdb_update($table,$safe,'id=:id',['id'=>(int)$id]);}
function sr_input(){ $raw=file_get_contents('php://input'); $json=json_decode($raw,true); if(is_array($json))return $json; if(!empty($_POST))return $_POST; return $_GET; }
function sr_out($a,$code=200){http_response_code($code);echo json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;}

try{
  $in=sr_input();
  $key=$in['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
  if(!hash_equals($expected,(string)$key)) sr_out(['success'=>false,'error'=>'bad_key'],403);

  $jobId=(int)($in['job_id']??($in['id']??0));
  $title=(string)($in['title']??($jobId?'Scorsese ComfyUI Render #'.$jobId:'Scorsese Imported Media'));
  $status=(string)($in['status']??'complete');
  $outputUrl=(string)($in['output_url']??($in['public_url']??''));
  $thumb=(string)($in['thumbnail_url']??($in['image_url']??$outputUrl));
  $path=(string)($in['output_path']??($in['file_path']??''));
  $summary=(string)($in['summary']??'Playable media generated or imported from ComfyUI.');
  $isVideo=preg_match('/\.(mp4|webm|mov)(\?|$)/i',$outputUrl.$path);
  $dtype=$isVideo?'video_package':'thumbnail_package';

  if($jobId && sr_table('scorsese_comfy_jobs')){
    sr_update('scorsese_comfy_jobs',$jobId,[
      'status'=>$status,
      'progress'=>100,
      'output_path'=>$path,
      'output_url'=>$outputUrl,
      'video_url'=>$isVideo?$outputUrl:null,
      'image_url'=>!$isVideo?$thumb:null,
      'thumbnail_url'=>$thumb,
      'updated_at'=>gdb_now()
    ]);
  }

  $deliverableId=null;
  if(sr_table('goliath_deliverables')){
    $existing=null;
    if($outputUrl){
      try{$existing=gdb_one("SELECT id FROM goliath_deliverables WHERE output_url=? OR public_url=? LIMIT 1",[$outputUrl,$outputUrl]);}catch(Throwable $e){}
    }
    if($existing){$deliverableId=(int)$existing['id'];}
    else{
      $deliverableId=sr_insert('goliath_deliverables',[
        'deliverable_uid'=>sr_uid('media'),
        'executive'=>'Scorsese',
        'executive_key'=>'scorsese',
        'deliverable_type'=>$dtype,
        'title'=>$title,
        'status'=>'ready',
        'file_path'=>$path,
        'public_url'=>$outputUrl,
        'summary'=>$summary,
        'metadata'=>sr_json(['job_id'=>$jobId,'thumbnail_url'=>$thumb,'output_path'=>$path,'source'=>'comfyui']),
        'evidence_status'=>'verified',
        'evidence'=>'Registered from ComfyUI output path: '.$path,
        'output_summary'=>$summary,
        'output_url'=>$outputUrl,
        'output_path'=>$path,
        'source_urls'=>sr_json([$outputUrl]),
        'source_record_ids'=>sr_json(['scorsese_comfy_job_id'=>$jobId]),
        'related_task_id'=>null,
        'related_completion_id'=>null,
        'next_action'=>'Preview video/media, approve, revise, or schedule.',
        'review_status'=>'ready',
        'priority'=>90
      ]);
    }
  }

  sr_out(['success'=>true,'version'=>'V80 Scorsese Media Register','job_id'=>$jobId,'deliverable_id'=>$deliverableId,'output_url'=>$outputUrl,'message'=>'Media is now registered for speed-to-content.']);
}catch(Throwable $e){
  sr_out(['success'=>false,'version'=>'V80 Scorsese Media Register','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()],500);
}
?>