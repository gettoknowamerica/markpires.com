<?php
/**
 * Goliath V96.1 Executive Kernel
 * Loads Constitution + Executive Identity + Capabilities + Mission Context before work begins.
 */

if(!function_exists('gx96_table')){
function gx96_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('gx96_col')){
function gx96_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('gx96_uid')){
function gx96_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
}
if(!function_exists('gx96_json')){
function gx96_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
if(!function_exists('gx96_insert')){
function gx96_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(gx96_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
}
if(!function_exists('gx96_update')){
function gx96_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(gx96_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
}
if(!function_exists('gx96_norm_exec')){
function gx96_norm_exec($exec){
  $e=strtolower(trim((string)$exec));
  $e=preg_replace('/[^a-z]/','',$e);
  $map=['holmes'=>'sherlock','cout'=>'scout','corsese'=>'scorsese','hakespeare'=>'shakespeare','essica'=>'jessica','instein'=>'einstein','olumbo'=>'columbo','oliath'=>'goliath'];
  return $map[$e]??($e?:'goliath');
}
}
if(!function_exists('gx96_public_exec')){
function gx96_public_exec($exec){
  $e=gx96_norm_exec($exec);
  return $e==='sherlock'?'Sherlock':ucfirst($e);
}
}
if(!function_exists('gx96_core_roots')){
function gx96_core_roots(){
  $roots=[
    realpath(__DIR__.'/../goliath-core'),
    realpath(__DIR__.'/../../goliath-core'),
    realpath($_SERVER['DOCUMENT_ROOT'].'/goliath-core'),
    realpath(__DIR__.'/../GoliathCore'),
    realpath(__DIR__.'/../../GoliathCore')
  ];
  return array_values(array_unique(array_filter($roots)));
}
}
if(!function_exists('gx96_read_file')){
function gx96_read_file($file,$max=120000){
  if(!$file||!file_exists($file)||!is_readable($file)) return '';
  $s=file_get_contents($file,false,null,0,$max);
  return trim((string)$s);
}
}
if(!function_exists('gx96_find_core_file')){
function gx96_find_core_file($relativeOptions){
  foreach(gx96_core_roots() as $root){
    foreach((array)$relativeOptions as $rel){
      $p=$root.'/'.ltrim($rel,'/');
      if(file_exists($p)) return $p;
    }
  }
  return null;
}
}
if(!function_exists('gx96_load_constitution')){
function gx96_load_constitution(){
  $docs=[]; $files=[];
  foreach(gx96_core_roots() as $root){
    $dir=$root.'/constitution';
    if(is_dir($dir)){
      foreach(glob($dir.'/*.md')?:[] as $f){$files[$f]=basename($f);}
      foreach(glob($dir.'/doctrine/*.md')?:[] as $f){$files[$f]='doctrine/'.basename($f);}
    }
  }
  ksort($files);
  foreach($files as $f=>$name){
    $text=gx96_read_file($f,30000);
    if($text!=='') $docs[]=['file'=>$name,'path'=>$f,'text'=>$text];
  }
  return $docs;
}
}
if(!function_exists('gx96_load_capabilities')){
function gx96_load_capabilities(){
  $out=[];
  $files=[
    gx96_find_core_file(['knowledge/capabilities.json','executive-tool-capability-dictionary.json']),
    gx96_find_core_file(['knowledge/plugins.md','plugin-capabilities-v77.md'])
  ];
  foreach($files as $f){
    if($f && file_exists($f)){
      $txt=gx96_read_file($f,80000);
      $json=json_decode($txt,true);
      $out[]=['file'=>basename($f),'path'=>$f,'type'=>is_array($json)?'json':'text','content'=>is_array($json)?$json:$txt];
    }
  }
  return $out;
}
}
if(!function_exists('gx96_load_identity')){
function gx96_load_identity($exec){
  $exec=gx96_norm_exec($exec);
  $aliases=[$exec];
  if($exec==='sherlock') $aliases[]='holmes';
  if($exec==='goliath') $aliases[]='goliath';
  foreach($aliases as $e){
    $p=gx96_find_core_file([
      "executive-offices/$e.md",
      "executive-offices/$e/$e.md",
      "$e/$e.md",
      "$e.md"
    ]);
    if($p) return ['executive'=>$exec,'display'=>gx96_public_exec($exec),'path'=>$p,'text'=>gx96_read_file($p,80000)];
  }
  return ['executive'=>$exec,'display'=>gx96_public_exec($exec),'path'=>null,'text'=>"I am ".gx96_public_exec($exec).". I serve the Goliath Omni operating system and execute my mission under the Constitution."];
}
}
if(!function_exists('gx96_extract_brief')){
function gx96_extract_brief($text,$max=2200){
  $text=trim(strip_tags((string)$text));
  $text=preg_replace('/\s+/', ' ', $text);
  if(strlen($text)>$max) $text=substr($text,0,$max).'...';
  return $text;
}
}
if(!function_exists('gx96_build_boot_context')){
function gx96_build_boot_context($exec,$mission=[]){
  $exec=gx96_norm_exec($exec);
  $constitution=gx96_load_constitution();
  $identity=gx96_load_identity($exec);
  $capabilities=gx96_load_capabilities();

  $constitutionBrief=[];
  foreach($constitution as $d){
    $constitutionBrief[]=$d['file'].": ".gx96_extract_brief($d['text'],650);
  }

  $boot=[
    'version'=>'V96.1 Executive Boot Sequence',
    'executive'=>$exec,
    'display'=>gx96_public_exec($exec),
    'loaded_at'=>date('c'),
    'constitution_files'=>array_map(fn($d)=>$d['file'],$constitution),
    'identity_file'=>$identity['path'],
    'capability_files'=>array_map(fn($c)=>$c['file'],$capabilities),
    'constitution_brief'=>implode("\n\n",$constitutionBrief),
    'identity_brief'=>gx96_extract_brief($identity['text'],3000),
    'mission'=>$mission,
    'operating_order'=>[
      'Load Constitution',
      'Load Executive Identity',
      'Load Capabilities',
      'Load Mission Context',
      'Accept Mission',
      'Execute Under Role',
      'Record Evidence',
      'Deliver Work',
      'Answer Completion Reflection',
      'Handoff Next Executive'
    ],
    'completion_reflection_required'=>[
      'What did I accomplish?',
      'What did I learn?',
      'What could improve next time?',
      'Which executive should receive this next?'
    ]
  ];
  $boot['boot_hash']=hash('sha256',gx96_json($boot));
  return $boot;
}
}
if(!function_exists('gx96_boot')){
function gx96_boot($exec,$mission=[]){
  $boot=gx96_build_boot_context($exec,$mission);
  if(gx96_table('executive_boot_logs')){
    gx96_insert('executive_boot_logs',[
      'boot_uid'=>gx96_uid('boot'),
      'executive_key'=>$boot['executive'],
      'mission_type'=>$mission['mission_type']??($mission['type']??null),
      'commission_id'=>(int)($mission['commission_id']??0)?:null,
      'task_id'=>(int)($mission['task_id']??0)?:null,
      'dossier_id'=>(int)($mission['dossier_id']??0)?:null,
      'boot_hash'=>$boot['boot_hash'],
      'identity_file'=>$boot['identity_file'],
      'constitution_files'=>gx96_json($boot['constitution_files']),
      'boot_context'=>gx96_json($boot),
      'created_at'=>gdb_now()
    ]);
  }
  if(gx96_table('goliath_executive_heartbeat')){
    $existing=gdb_one("SELECT id FROM goliath_executive_heartbeat WHERE executive_key=? LIMIT 1",[$boot['executive']]);
    $hb=[
      'executive_key'=>$boot['executive'],
      'status'=>'booted',
      'current_step'=>'Loaded Constitution + identity before mission',
      'progress'=>3,
      'message'=>$boot['display'].' booted under V96.1 Executive Kernel.',
      'metadata'=>gx96_json(['boot_hash'=>$boot['boot_hash'],'identity_file'=>$boot['identity_file']]),
      'updated_at'=>gdb_now()
    ];
    if($existing) gx96_update('goliath_executive_heartbeat',(int)$existing['id'],$hb);
    else gx96_insert('goliath_executive_heartbeat',$hb);
  }
  return $boot;
}
}
if(!function_exists('gx96_timeline')){
function gx96_timeline($exec,$event,$title,$details='',$meta=[]){
  if(!gx96_table('executive_mission_timeline')) return null;
  return gx96_insert('executive_mission_timeline',[
    'event_uid'=>gx96_uid('evt'),
    'executive_key'=>gx96_norm_exec($exec),
    'event_type'=>$event,
    'title'=>$title,
    'details'=>$details,
    'metadata'=>gx96_json($meta),
    'created_at'=>gdb_now()
  ]);
}
}
if(!function_exists('gx96_complete_reflection')){
function gx96_complete_reflection($exec,$mission,$answers){
  gx96_timeline($exec,'completion_reflection','Mission reflection completed',gx96_json($answers),$mission);
  if(gx96_table('executive_memory')){
    gx96_insert('executive_memory',[
      'memory_uid'=>gx96_uid('mem'),
      'executive_key'=>gx96_norm_exec($exec),
      'memory_type'=>'completion_reflection',
      'title'=>'Mission reflection: '.($mission['title']??$mission['mission_type']??'mission'),
      'content'=>gx96_json($answers),
      'metadata'=>gx96_json($mission),
      'importance'=>70,
      'created_at'=>gdb_now(),
      'updated_at'=>gdb_now()
    ]);
  }
}
}
?>