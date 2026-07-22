<?php
/**
 * V95 Universal Executive Engine Helpers
 */
if(!function_exists('v95_table')){
function v95_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('v95_col')){
function v95_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('v95_uid')){
function v95_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
}
if(!function_exists('v95_json')){
function v95_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
if(!function_exists('v95_insert')){
function v95_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(v95_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
}
if(!function_exists('v95_update')){
function v95_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(v95_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
}
if(!function_exists('v95_event')){
function v95_event($exec,$type,$title,$details='',$meta=[]){try{return v95_insert('executive_events',['event_uid'=>v95_uid('ev'),'executive_key'=>$exec,'event_type'=>$type,'title'=>$title,'details'=>$details,'metadata'=>v95_json($meta),'created_at'=>gdb_now()]);}catch(Throwable $e){return null;}}
}
if(!function_exists('v95_heartbeat')){
function v95_heartbeat($exec,$row){
  if(!v95_table('goliath_executive_heartbeat')) return null;
  $existing=gdb_one("SELECT id FROM goliath_executive_heartbeat WHERE executive_key=? LIMIT 1",[$exec]);
  $row['executive_key']=$exec; $row['updated_at']=gdb_now();
  if($existing){v95_update('goliath_executive_heartbeat',(int)$existing['id'],$row);return (int)$existing['id'];}
  return v95_insert('goliath_executive_heartbeat',$row);
}
}
if(!function_exists('v95_deliverable_exists')){
function v95_deliverable_exists($source,$id){
  if(!$source||!$id) return false;
  if(!v95_table('executive_deliverables')) return false;
  if(!v95_col('executive_deliverables','source_table') || !v95_col('executive_deliverables','source_id')) return false;
  try{
    $r=gdb_one("SELECT id FROM executive_deliverables WHERE source_table=? AND source_id=? LIMIT 1",[$source,(int)$id]);
    return !empty($r['id']);
  }catch(Throwable $e){
    return false;
  }
}
}
if(!function_exists('v95_create_deliverable')){
function v95_create_deliverable($row){
  if(empty($row['deliverable_uid'])) $row['deliverable_uid']=v95_uid('deliv');
  if(empty($row['status'])) $row['status']='new';
  if(!isset($row['viewed'])) $row['viewed']=0;
  if(empty($row['created_at'])) $row['created_at']=gdb_now();
  if(empty($row['updated_at'])) $row['updated_at']=gdb_now();

  // V95.2 FK safety:
  // Your executive_deliverables table has foreign keys to executive_commissions.
  // A value of 0 is NOT valid for a FK; use NULL unless the referenced row exists.
  foreach(['commission_id'=>'executive_commissions','task_id'=>'local_ai_tasks','browser_job_id'=>'goliath_browser_jobs'] as $field=>$table){
    if(array_key_exists($field,$row)){
      $val=(int)($row[$field]??0);
      if($val<=0){ $row[$field]=null; continue; }
      if(v95_table($table)){
        try{
          $exists=gdb_one("SELECT id FROM `$table` WHERE id=? LIMIT 1",[$val]);
          if(empty($exists['id'])) $row[$field]=null;
        }catch(Throwable $e){
          $row[$field]=null;
        }
      }
    }
  }

  $id=v95_insert('executive_deliverables',$row);
  if($id) v95_event($row['executive_key']??'goliath','deliverable_created',$row['title']??'New deliverable',$row['preview']??'', ['deliverable_id'=>$id]);
  return $id;
}
}
if(!function_exists('v95_memory')){
function v95_memory($exec,$type,$title,$content,$meta=[],$importance=50){
  return v95_insert('executive_memory',['memory_uid'=>v95_uid('mem'),'executive_key'=>$exec,'memory_type'=>$type,'title'=>$title,'content'=>$content,'metadata'=>v95_json($meta),'importance'=>$importance,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
}
}
if(!function_exists('v95_exec_name')){
function v95_exec_name($raw){
  $k=strtolower(trim((string)$raw));
  $k=preg_replace('/[^a-z]/','',$k);
  $aliases=['oliath'=>'goliath','essica'=>'jessica','cout'=>'scout','corsese'=>'scorsese','hakespeare'=>'shakespeare','instein'=>'einstein','olumbo'=>'columbo'];
  return $aliases[$k]??$k;
}
}
if(!function_exists('v95_title_from_text')){
function v95_title_from_text($s,$fallback='Executive Deliverable'){
  $s=trim(strip_tags((string)$s));
  if(!$s) return $fallback;
  $s=preg_replace('/\s+/',' ',$s);
  return mb_substr($s,0,90);
}
}
?>