<?php
function gbi_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gbi_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function gbi_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function gbi_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function gbi_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(gbi_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
function gbi_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(gbi_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
function gbi_event($jobId,$exec,$type,$title,$details='',$meta=[]){return gbi_insert('goliath_browser_events',['event_uid'=>gbi_uid('gbe'),'browser_job_id'=>$jobId,'executive_key'=>$exec,'event_type'=>$type,'title'=>$title,'details'=>$details,'metadata'=>gbi_json($meta),'created_at'=>gdb_now()]);}
function gbi_heartbeat($exec,$row){$ex=gdb_one("SELECT id FROM goliath_executive_heartbeat WHERE executive_key=? LIMIT 1",[$exec]);$row['executive_key']=$exec;$row['updated_at']=gdb_now(); if($ex){gbi_update('goliath_executive_heartbeat',(int)$ex['id'],$row);return (int)$ex['id'];} return gbi_insert('goliath_executive_heartbeat',$row);}
function gbi_qurl($q){return 'https://www.google.com/search?q='.rawurlencode($q);}
?>