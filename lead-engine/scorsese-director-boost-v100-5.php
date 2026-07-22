<?php
/**
 * V100.5 Scorsese Director Boost
 * Upgrades queued Scorsese jobs into production-brief prompts before the next render starts.
 * Does NOT touch currently rendering jobs.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');

try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';

 $key=$_GET['key']??($_POST['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function d105_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function d105_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function d105_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(d105_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function d105_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function d105_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(d105_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}

 function d105_town($title){
   foreach(['Darien','Greenwich','New Canaan','Stamford','Westport','Fairfield','Norwalk','Wilton','Weston','Ridgefield','Trumbull','Monroe','Shelton','Bridgeport','Bethel','Danbury','Newtown'] as $town){
     if(stripos($title,$town)!==false) return $town;
   }
   return '';
 }
 function d105_audience($title){
   $t=strtolower($title);
   if(strpos($t,'california')!==false)return 'California and West Coast relocation buyers considering Connecticut';
   if(strpos($t,'modern')!==false)return 'design-minded buyers seeking modern homes in Connecticut';
   if(strpos($t,'expired')!==false)return 'homeowners whose listing expired and need confidence for a relaunch';
   if(strpos($t,'absentee')!==false)return 'absentee owners who need a simple long-distance selling plan';
   if(strpos($t,'waterfront')!==false)return 'buyers dreaming about Connecticut waterfront living';
   if(strpos($t,'buyer')!==false)return 'active buyers preparing to tour homes';
   if(strpos($t,'seller')!==false)return 'Connecticut homeowners preparing to sell';
   return 'Connecticut real estate buyers, sellers, and relocation clients';
 }
 function d105_scenes($title,$town){
   $townText=$town?:'Fairfield County Connecticut';
   return [
    "0–2 seconds: cinematic establishing shot of {$townText}, luxury Connecticut feel, golden hour, premium real estate energy.",
    "2–5 seconds: elegant town lifestyle visuals — downtown charm, tree-lined streets, local cafés, parks, coastal or New England character.",
    "5–8 seconds: beautiful homes, architectural details, smooth gimbal/drone-style motion, warm human lifestyle.",
    "8–12 seconds: emotional story beat — why this place or topic matters to buyers/sellers right now.",
    "12–15 seconds: polished closing shot with room for later Mark Pires branding overlay. No generated text required."
   ];
 }
 function d105_prompt($title,$town,$audience,$sourceSummary=''){
   $townLine=$town ? "LOCATION CONTEXT: {$town}, Connecticut. Authentic Fairfield County / New England visuals. Do not invent fake signs. Use real-feeling downtown, coastal, neighborhood, park, luxury home, and community imagery." : "LOCATION CONTEXT: Connecticut / Fairfield County real estate lifestyle.";
   $scenes=implode("\n",d105_scenes($title,$town));
   $sourceSummary=trim(strip_tags((string)$sourceSummary));
   if($sourceSummary)$sourceSummary="\nSOURCE CONTEXT FROM SHAKESPEARE / PACKAGE:\n".$sourceSummary."\n";
   return "SCORSESE DIRECTOR'S CUT BRIEF — WAN 2.7 TEXT TO VIDEO

PROJECT TITLE:
{$title}

AUDIENCE:
{$audience}

{$townLine}
{$sourceSummary}
VIDEO SPECS:
Length target: 15 seconds.
Aspect ratio: 16:9 if the workflow is landscape, 9:16 if the workflow is vertical.
Style: premium cinematic real estate commercial.
Motion: smooth, dynamic, elegant, never static.
Language: English only if text is later overlaid. Prefer no AI-generated text inside the video.

STORYBOARD:
{$scenes}

DIRECTING NOTES:
Create a real estate commercial, not a random AI art clip.
Use authentic Connecticut luxury, local life, architecture, coastal New England atmosphere where appropriate.
Make it feel like Discover CT plus luxury real estate marketing.
No unreadable signs, no gibberish letters, no fake typography.
Avoid people close-ups unless natural and realistic.
Prioritize beautiful homes, town centers, water, parks, restaurants, streetscapes, lifestyle, and motion.
Camera language: drone establishing shot, slow push-in, parallax, gimbal movement, elegant transitions, polished social media pacing.

POSITIVE PROMPT:
Ultra cinematic luxury Connecticut real estate commercial, {$title}, {$audience}, authentic Fairfield County lifestyle, golden hour lighting, smooth drone shot, premium homes, town center charm, parks, waterfront where appropriate, tree-lined streets, warm human lifestyle, professional Hollywood cinematography, realistic motion, high detail, sharp focus, elegant composition, luxury magazine aesthetic, viral social media real estate video, beautiful natural colors, cinematic depth of field, polished commercial quality.

NEGATIVE PROMPT:
gibberish text, unreadable text, random letters, fake words, subtitles, watermark, logo, blurry, low resolution, low quality, noisy image, flickering, distorted faces, duplicate people, extra fingers, bad hands, bad anatomy, cartoon, anime, CGI, fantasy, oversaturated colors, warped buildings, melting objects, shaky camera, jump cuts, compression artifacts, ugly composition, deformed architecture, incorrect perspective, one second video, two second video, static still image.";
 }

 if(!d105_table('scorsese_comfy_jobs')) throw new Exception('scorsese_comfy_jobs missing');

 $limit=max(1,min(300,(int)($_GET['limit']??120)));
 $rows=gdb_all("SELECT * FROM scorsese_comfy_jobs WHERE status='queued' ORDER BY priority DESC,id ASC LIMIT {$limit}")?:[];

 $updated=[];
 foreach($rows as $r){
   $title=$r['title']?:('Scorsese video job '.$r['id']);
   $town=d105_town($title);
   $audience=d105_audience($title);
   $summary='';
   if(!empty($r['production_package_id']) && d105_table('production_packages')){
     $pkg=gdb_one("SELECT package_summary,title FROM production_packages WHERE id=? LIMIT 1",[(int)$r['production_package_id']]);
     if($pkg)$summary=($pkg['title']??'')."\n".($pkg['package_summary']??'');
   }
   $prompt=d105_prompt($title,$town,$audience,$summary);
   $meta=json_decode($r['metadata']??'[]',true)?:[];
   $meta['v100_5_director_boost']=true;
   $meta['director_boosted_at']=date('c');
   $meta['town']=$town;
   $meta['audience']=$audience;
   $meta['duration_target_seconds']=15;
   d105_update('scorsese_comfy_jobs',(int)$r['id'],[
    'prompt'=>$prompt,
    'priority'=>max(99,(int)($r['priority']??0)),
    'metadata'=>json_encode($meta,JSON_UNESCAPED_SLASHES),
    'updated_at'=>gdb_now()
   ]);
   $updated[]=['job_id'=>(int)$r['id'],'title'=>$title,'town'=>$town?:null,'audience'=>$audience];
 }

 if(d105_table('relationship_timeline')){
   d105_insert('relationship_timeline',[
    'event_uid'=>d105_uid('rel'),
    'executive_key'=>'scorsese',
    'event_type'=>'director_boost',
    'title'=>'Scorsese Director Boost applied',
    'details'=>'Queued video jobs were upgraded with storyboard, audience, town context, English-only rules, and 15-second cinematic direction.',
    'metadata'=>json_encode(['updated_count'=>count($updated)],JSON_UNESCAPED_SLASHES),
    'priority'=>100,
    'is_new'=>1,
    'created_at'=>gdb_now()
   ]);
 }

 echo json_encode([
  'ok'=>true,
  'version'=>'V100.5 Scorsese Director Boost',
  'updated_count'=>count($updated),
  'updated'=>$updated,
  'note'=>'Currently rendering jobs were not touched. This boosts the next queued jobs before they render.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 echo json_encode(['ok'=>false,'version'=>'V100.5 Scorsese Director Boost','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>