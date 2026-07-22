<?php
/**
 * V100.4 Scorsese Quality Director
 * Stops junk 1-second/text-gibberish renders by upgrading Scorsese queued jobs
 * into image-based, English-only, research-first cinematic video briefs.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function q104_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function q104_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function q104_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(q104_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function q104_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function q104_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(q104_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function q104_town($title){
   foreach(['Darien','Greenwich','New Canaan','Stamford','Westport','Fairfield','Norwalk','Wilton','Weston','Ridgefield','Trumbull','Monroe','Shelton','Bridgeport','Bethel','Danbury','Newtown'] as $town){
     if(stripos($title,$town)!==false) return $town;
   }
   return '';
 }
 function q104_prompt($title,$town){
   $townLine=$town ? "This video is specifically about {$town}, Connecticut. Show upscale Connecticut streetscapes, coastal New England character where appropriate, town center charm, parks, beautiful homes, lifestyle scenes, commuters, restaurants, community energy, and polished local real estate storytelling." : "This video is specifically about Connecticut real estate lifestyle, Fairfield County homes, local community, buyers, sellers, and market opportunity.";
   return "SCORSESE CORE DIRECTIVE — QUALITY FIRST.

Create a polished 10-second cinematic vertical real estate video for Mark Pires.

TITLE: {$title}

{$townLine}

MANDATORY RULES:
1. Duration target: 10 seconds minimum. Never create 1-second or 2-second clips.
2. No fake text, no gibberish, no unreadable lettering, no random letters.
3. If text appears, it must be simple English only: '{$title}' or 'Mark Pires | Connecticut Real Estate'. Prefer no text if the model cannot render clean English.
4. Use real-world visual reference logic: the video should look like authentic Fairfield County / Connecticut, not generic fantasy.
5. Do not hallucinate signs or town names. No fake street signs.
6. Make it dynamic: drone-style opener, elegant push-in, cinematic parallax, luxury magazine lighting, smooth motion.
7. Style: premium real estate commercial, Discover CT energy, warm human lifestyle, high-end but local.
8. End visual: confident, polished, ready for social media.

NEGATIVE PROMPT:
gibberish text, random letters, fake words, unreadable signs, distorted typography, watermark, low quality, blurry, deformed houses, distorted people, bad hands, horror, cartoon, messy composition, one second video, static image only.";
 }

 if(!q104_table('scorsese_comfy_jobs')) throw new Exception('scorsese_comfy_jobs missing');

 $limit=max(1,min(300,(int)($_GET['limit']??150)));
 $rows=gdb_all("SELECT * FROM scorsese_comfy_jobs WHERE status IN ('queued','failed','error') ORDER BY id DESC LIMIT {$limit}")?:[];

 $updated=[];$requeued=[];
 foreach($rows as $r){
   $title=$r['title']?:('Scorsese video job '.$r['id']);
   $town=q104_town($title);
   $prompt=q104_prompt($title,$town);
   $meta=json_decode($r['metadata']??'[]',true)?:[];
   $meta['v100_4_quality_director']=true;
   $meta['quality_rules']='10s minimum, English-only text, no gibberish, research-first town visuals';
   $meta['town']=$town;
   q104_update('scorsese_comfy_jobs',(int)$r['id'],[
    'prompt'=>$prompt,
    'status'=>'queued',
    'progress'=>0,
    'priority'=>max(98,(int)($r['priority']??0)),
    'error_message'=>null,
    'metadata'=>json_encode($meta,JSON_UNESCAPED_SLASHES),
    'updated_at'=>gdb_now()
   ]);
   $updated[]=['job_id'=>(int)$r['id'],'title'=>$title,'town'=>$town?:null];
 }

 if(q104_table('relationship_timeline')){
   q104_insert('relationship_timeline',[
    'event_uid'=>q104_uid('rel'),
    'executive_key'=>'scorsese',
    'event_type'=>'quality_director_patch',
    'title'=>'Scorsese quality rules upgraded',
    'details'=>'Queued Scorsese jobs were rewritten with 10-second minimum, English-only text rules, no gibberish, and town-specific cinematic direction.',
    'metadata'=>json_encode(['updated_count'=>count($updated)],JSON_UNESCAPED_SLASHES),
    'priority'=>100,
    'is_new'=>1,
    'created_at'=>gdb_now()
   ]);
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V100.4 Scorsese Quality Director',
  'updated_count'=>count($updated),
  'updated'=>$updated,
  'next'=>'Run the Scorsese return worker. If Comfy template still outputs 1-second clips, the workflow template itself needs frame/duration settings changed.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.4 Scorsese Quality Director','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>