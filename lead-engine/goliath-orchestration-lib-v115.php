<?php
if(!function_exists('g115_uid')){
function g115_uid(string $prefix):string{return function_exists('gdb_uid')?gdb_uid($prefix):$prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5));}
function g115_cols(string $table):array{$rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];$o=[];foreach($rows as $r)$o[$r['column_name']]=true;return $o;}
function g115_insert_safe(string $table,array $row):int{$cols=g115_cols($table);$safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;if(!$safe)throw new RuntimeException("No compatible columns for $table");$id=gdb_insert($table,$safe);return (int)$id;}
function g115_read_file(string $path):string{return is_file($path)?trim((string)file_get_contents($path)):'';}
function g115_constitution_dir():string{return __DIR__.'/goliath-constitutions-v115';}
function g115_exec_file(string $executive):string{
 $map=['goliath'=>'Goliath.md','shakespeare'=>'Shakespeare.md','scout'=>'Scout.md','jessica'=>'Jessica.md','scorsese'=>'Scorsese.md','einstein'=>'Einstein.md','columbo'=>'Columbo.md','prospector'=>'Prospector.md','rockefeller'=>'Rockefeller.md','pandora'=>'Pandora.md','mozart'=>'Mozart.md','sherlock'=>'Sherlock.md'];
 return g115_constitution_dir().'/executives/'.($map[strtolower($executive)]??ucfirst(strtolower($executive)).'.md');
}
function g115_constitution(string $executive):string{
 $dir=g115_constitution_dir();
 $files=['00-preamble.md','00.5-operating-principles.md','01-core-values.md','02-executive-operating-principles.md','04-executive-collaboration.md','06-deliverables-standard.md','07-continuous-learning.md', '11-v114-orchestration-law.md','12-v115-clockwork-law.md'];
 $chunks=[];foreach($files as $f){$x=g115_read_file($dir.'/'.$f);if($x!=='')$chunks[]=$x;}
 $personal=g115_read_file(g115_exec_file($executive));if($personal!=='')$chunks[]=$personal;
 return implode("\n\n==============================\n\n",$chunks);
}
function g115_create_local_task(string $executive,string $type,string $title,string $prompt,int $priority,array $metadata=[]):int{
 $constitution=g115_constitution($executive);
 $full="GOLIATH OMNI V115 — CONSTITUTIONAL EXECUTION\n\nREAD AND FOLLOW THIS BEFORE WORKING:\n\n".$constitution."\n\nCURRENT ASSIGNMENT:\n".$prompt."\n\nNON-NEGOTIABLE OUTPUT RULES:\n- Work on the actual shared artifact.\n- Return a complete tangible deliverable, never a status brief.\n- Cite evidence or source URLs when research is requested.\n- If editing content, return the complete revised content.\n- State the next recipient and what changed.\n";
 return g115_insert_safe('local_ai_tasks',[
  'task_uid'=>g115_uid('v115_task'),'agent'=>ucfirst($executive),'executive_key'=>strtolower($executive),'task_type'=>$type,'type'=>$type,'model'=>'goliath-local-worker','title'=>$title,'prompt'=>$full,'status'=>'queued','workflow_state'=>'queued','priority'=>$priority,'progress'=>0,'metadata'=>gdb_json(array_merge($metadata,['v115'=>true,'executive'=>$executive])),'metadata_json'=>gdb_json(array_merge($metadata,['v115'=>true,'executive'=>$executive])),'created_at'=>gdb_now(),'updated_at'=>gdb_now()
 ]);
}
}
?>