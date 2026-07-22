<?php
/**
 * V96.3 Relationship Memory Helper
 */
if(!function_exists('rel963_col')){
function rel963_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('rel963_table')){
function rel963_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('rel963_uid')){
function rel963_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
}
if(!function_exists('rel963_json')){
function rel963_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
if(!function_exists('rel963_insert')){
function rel963_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(rel963_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
}
if(!function_exists('rel963_timeline')){
function rel963_timeline($exec,$type,$title,$details,$meta=[],$contactId=null,$dossierId=null,$priority=50){
 if(rel963_table('relationship_timeline')) rel963_insert('relationship_timeline',['event_uid'=>rel963_uid('rel'),'contact_id'=>$contactId,'dossier_id'=>$dossierId,'executive_key'=>$exec,'event_type'=>$type,'title'=>$title,'details'=>$details,'metadata'=>rel963_json($meta),'priority'=>$priority,'is_new'=>1,'created_at'=>gdb_now()]);
}
}
if(!function_exists('rel963_memory')){
function rel963_memory($exec,$type,$title,$content,$meta=[],$contactId=null,$dossierId=null,$importance=50){
 if(rel963_table('relationship_memory')) rel963_insert('relationship_memory',['memory_uid'=>rel963_uid('mem'),'contact_id'=>$contactId,'dossier_id'=>$dossierId,'memory_type'=>$type,'title'=>$title,'content'=>$content,'importance'=>$importance,'source_executive'=>$exec,'metadata'=>rel963_json($meta),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
}
}
?>