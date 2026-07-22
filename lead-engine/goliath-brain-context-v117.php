<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function b117_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function b117_one(string $sql,array $params=[]):array{
 try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function b117_all(string $sql,array $params=[]):array{
 try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function b117_table(string $table):bool{
 $row=b117_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 return (int)($row['c']??0)>0;
}
function b117_cols(string $table):array{
 $rows=b117_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table]);
 $out=[];foreach($rows as $row)$out[(string)$row['column_name']]=true;return $out;
}
function b117_read(string $path,int $limit=9000):string{
 if(!is_file($path))return '';
 $text=trim((string)file_get_contents($path));
 if(mb_strlen($text)>$limit)$text=mb_substr($text,0,$limit)."\n[truncated]";
 return $text;
}
function b117_constitution_dir():string{
 foreach([
  __DIR__.'/goliath-constitutions-v115',
  __DIR__.'/goliath-constitutions-v114'
 ] as $dir){
  if(is_dir($dir))return $dir;
 }
 return '';
}
function b117_compact(string $text,int $limit):string{
 $text=preg_replace('/\r\n?/',"\n",$text);
 $text=preg_replace('/[ \t]+/',' ',$text);
 $text=preg_replace('/\n{3,}/',"\n\n",$text);
 $text=trim($text);
 return mb_strlen($text)>$limit?mb_substr($text,0,$limit)."\n[truncated]":$text;
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(b117_key(),$key)){
 http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;
}

try{
 $constitutionDir=b117_constitution_dir();
 $constitutionalFiles=[
  '00-preamble.md',
  '00.5-operating-principles.md',
  '01-core-values.md',
  '02-executive-operating-principles.md',
  '03-executive-charters.md',
  '04-executive-collaboration.md',
  '06-deliverables-standard.md',
  '07-continuous-learning.md',
  '08-leadership-stewardship.md',
  '11-v114-orchestration-law.md',
  '12-v115-clockwork-law.md',
  'executives/Goliath.md'
 ];
 $constitutionChunks=[];
 foreach($constitutionalFiles as $file){
  if($constitutionDir!==''){
   $chunk=b117_read($constitutionDir.'/'.$file,5000);
   if($chunk!=='')$constitutionChunks[]=$chunk;
  }
 }
 $constitution=b117_compact(implode("\n\n",$constitutionChunks),15000);

 $fallbackTeam=[
  ['key'=>'goliath','name'=>'Goliath','title'=>'Chief Executive Operating System','abilities'=>'Orchestrates the whole company; knows the Constitution; delegates by Executive name; protects the approved deliverable; closes missions; routes approved assets to review, publishing, social distribution and repurposing.'],
  ['key'=>'jessica','name'=>'Jessica','title'=>'Chief Relationship & Human Touch Officer','abilities'=>'CRM, warm Mark-voice communications, follow-up, scheduling, relationship memory, lead nurture, response handling and founder notifications. Jessica is invisible externally; outreach is presented as Mark Pires.'],
  ['key'=>'scout','name'=>'Scout','title'=>'Chief Intelligence & Lead Discovery Officer','abilities'=>'Expired, FSBO, foreclosure, owner and contact discovery; phone/email enrichment; market intelligence; ranked call-ready dossiers; public and approved source research.'],
  ['key'=>'sherlock','name'=>'Sherlock','title'=>'Chief Strategy, Verification & Opportunity QA Officer','abilities'=>'Verifies claims, ownership, LLCs, trusts, probate, tax/public records, evidence, legal and hidden property details; prevents fabricated or weak intelligence.'],
  ['key'=>'einstein','name'=>'Einstein','title'=>'Chief Intelligence & Asset Compounding Officer','abilities'=>'SEO, AEO, GEO, analytics, schema, backlinks, internal links, conversion, authority growth and the post-publication lifecycle so assets continue compounding.'],
  ['key'=>'shakespeare','name'=>'Shakespeare','title'=>'Chief Storyteller & Authority Builder','abilities'=>'Publish-ready blogs, town pages, scripts, emails, social copy, CTAs, FAQs, schema, internal links, humanized Mark Pires voice and maintenance of the Living Constitution.'],
  ['key'=>'columbo','name'=>'Columbo','title'=>'Chief Research & Archive Intelligence Officer','abilities'=>'Restores Mark Pires and Discover Connecticut archives; YouTube chapters, timestamps, titles, descriptions, tags, viral moments, source enrichment and handoffs to Scorsese/Mozart.'],
  ['key'=>'scorsese','name'=>'Scorsese','title'=>'Chief Media Director','abilities'=>'Videos, reels, shorts, thumbnails, captions, film structure, visual packages, ComfyUI/Remotion/FFmpeg workflows, review cuts and emotionally powerful media.'],
  ['key'=>'mozart','name'=>'Mozart','title'=>'Chief Music, Voice & Audio Officer','abilities'=>'Audio cleanup, Demucs-style stem separation, EQ, compression, mastering, music arrangement, voice, soundtracks, BeatSeat catalog development and music-to-media handoffs.'],
  ['key'=>'prospector','name'=>'Prospector','title'=>'Chief Opportunity & Partnerships Officer','abilities'=>'Speaking, music, venue, winery, podcast, media, sponsorship, backlink, referral, partnership and paid opportunity discovery with outreach packages.'],
  ['key'=>'rockefeller','name'=>'Rockefeller','title'=>'Chief Revenue & Priority Officer','abilities'=>'ROI scoring, revenue paths, pricing, referral value, conversion, monetization, resource allocation and high-leverage next actions.'],
  ['key'=>'pandora','name'=>'Pandora','title'=>'Chief Possibility & Creative Expansion Officer','abilities'=>'Unexpected angles, emotional hooks, campaign expansion, trend opportunities, brand design, partnerships and turning one useful idea into many valuable outputs.']
 ];

 $team=$fallbackTeam;
 if(b117_table('executive_genomes')){
  $cols=b117_cols('executive_genomes');
  $select=['executive_key'];
  foreach(['public_name','display_name','title','public_title','mission','identity_prompt','constitution_text','core_directive','specialties_json','tools_json'] as $column){
   if(isset($cols[$column]))$select[]=$column;
  }
  $rows=b117_all("SELECT ".implode(',',$select)." FROM executive_genomes WHERE status='active' OR status IS NULL ORDER BY executive_key ASC");
  if($rows){
   $indexed=[];foreach($fallbackTeam as $row)$indexed[$row['key']]=$row;
   $constitutionalKeys=array_keys($indexed);
   foreach($rows as $row){
    $keyName=strtolower((string)($row['executive_key']??''));
    if($keyName===''||!in_array($keyName,$constitutionalKeys,true))continue;
    $base=$indexed[$keyName];
    $base['name']=(string)($row['display_name']??$row['public_name']??$base['name']);
    $base['title']=(string)($row['public_title']??$row['title']??$base['title']);
    $abilities=[];
    foreach(['mission','core_directive','identity_prompt','constitution_text'] as $column){
     if(!empty($row[$column]))$abilities[]=(string)$row[$column];
    }
    if($abilities)$base['abilities']=b117_compact(implode(' ',$abilities),900);
    $indexed[$keyName]=$base;
   }
   $team=array_values($indexed);
  }
 }

 $currentWork=[];
 if(b117_table('goliath_v112_missions')&&b117_table('goliath_v112_stages')){
  $currentWork=b117_all(
   "SELECT m.id mission_id,m.title mission_title,m.originator_key,m.status mission_status,
           m.current_stage_no,s.executive_key,s.stage_key,s.title stage_title,s.status stage_status,
           s.updated_at
    FROM goliath_v112_missions m
    JOIN goliath_v112_stages s ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
    WHERE m.status IN ('queued','working')
    ORDER BY m.priority DESC,m.updated_at DESC LIMIT 24"
  );
 }

 $recentAssets=[];
 if(b117_table('goliath_v112_artifacts')){
  $recentAssets=b117_all(
   "SELECT a.id artifact_id,a.mission_id,a.executive_key,a.artifact_type,a.title,a.status,
           a.artifact_url,a.artifact_path,a.created_at,m.originator_key
    FROM goliath_v112_artifacts a
    LEFT JOIN goliath_v112_missions m ON m.id=a.mission_id
    WHERE a.status IN ('ready_for_founder_review','review','approved','published')
    ORDER BY a.id DESC LIMIT 16"
  );
 }

 $tools=[];
 foreach([
  'goliath_tool_capabilities',
  'plugin_registry',
  'universal_plugin_registry',
  'executive_tool_permissions',
  'goliath_plugins'
 ] as $table){
  if(!b117_table($table))continue;
  $cols=b117_cols($table);
  $select=[];
  foreach(['name','tool_name','plugin_name','capability_name','description','status','endpoint','local_path','executive_key'] as $column){
   if(isset($cols[$column]))$select[]=$column;
  }
  if(!$select)continue;
  $statusWhere=isset($cols['status'])?" WHERE status IN ('active','available','connected','ready','enabled') OR status IS NULL":'';
  $rows=b117_all("SELECT ".implode(',',$select)." FROM $table$statusWhere LIMIT 80");
  foreach($rows as $row)$tools[]=['source_table'=>$table]+$row;
 }
 if(!$tools){
  $tools=[
   ['tool_name'=>'Ollama','description'=>'Local LLM intelligence on port 11434.'],
   ['tool_name'=>'Kokoro TTS','description'=>'Local natural speech generation on port 8000.'],
   ['tool_name'=>'OpenClaw / Hermes','description'=>'Local agent and orchestration tools when their endpoints are available.'],
   ['tool_name'=>'ComfyUI, Remotion, AIFFmpeg, WhisperX, SAM2, IP Adapter, PULID, RIFE, Flowframes, 4K upscaling','description'=>'Scorsese visual and video production toolkit.'],
   ['tool_name'=>'Demucs / AudioCraft and audio tools','description'=>'Mozart stem separation, music and audio production toolkit.'],
   ['tool_name'=>'Newspaper, Beautiful Soup, Trafilatura, Firecrawl, Crawl4AI, browser-use, Playwright','description'=>'Scout, Sherlock, Columbo and research/browser toolkit.'],
   ['tool_name'=>'Humanizer','description'=>'Shakespeare human-language refinement tool.'],
   ['tool_name'=>'MySQL CRM, Google Calendar, Resend and Twilio integrations','description'=>'Internal relationship, scheduling and notification systems.']
  ];
 }

 $teamLines=[];
 foreach($team as $member){
  $teamLines[]=$member['name'].' — '.$member['title'].': '.$member['abilities'];
 }
 $workLines=[];
 foreach($currentWork as $work){
  $workLines[]=
   '#'.(int)$work['mission_id'].' '.$work['mission_title'].
   ' | originator '.ucfirst((string)$work['originator_key']).
   ' | now with '.ucfirst((string)$work['executive_key']).
   ' | '.$work['stage_title'].' | '.$work['stage_status'];
 }
 $assetLines=[];
 foreach($recentAssets as $asset){
  $assetLines[]=
   'Artifact #'.(int)$asset['artifact_id'].' '.$asset['title'].
   ' | '.ucfirst((string)($asset['originator_key']?:$asset['executive_key'])).
   ' | '.$asset['artifact_type'].' | '.$asset['status'];
 }
 $toolLines=[];
 foreach(array_slice($tools,0,40) as $tool){
  $name=(string)($tool['tool_name']??$tool['plugin_name']??$tool['capability_name']??$tool['name']??'Tool');
  $description=(string)($tool['description']??$tool['endpoint']??$tool['local_path']??'available');
  $toolLines[]=$name.': '.$description;
 }

 $brief=
 "IDENTITY\n".
 "You are Goliath, Mark Pires' Chief Executive Operating System and trusted strategic partner. ".
 "Mark's name is Mark Pires. Never call him Mark Phillips or any other surname. ".
 "You are not a generic assistant and you do not refer vaguely to 'the developer' or 'the team' when a named Executive owns the work.\n\n".
 "FOUNDER MISSION\n".
 "Make Mark Pires the most successful realtor in Fairfield County, while growing Mark insPires speaking, Mark Pires Music, BeatSeat, Discover Connecticut, LegacySaved, and Goliath Omni. ".
 "Every client is treated like family through useful, deeply personalized Human Touch content and follow-up. One good idea should create many valuable outputs. Expansion always.\n\n".
 "GOLIATH OPERATING LAW\n".
 "- Know every Executive by name, title and ability.\n".
 "- Delegate explicitly: say Jessica, Scout, Sherlock, Einstein, Shakespeare, Columbo, Scorsese, Mozart, Prospector, Rockefeller or Pandora.\n".
 "- Never fabricate completed work, internet research, contacts, results or tool use.\n".
 "- Preserve the full approved deliverable. Never replace it with an overview or status report.\n".
 "- At final delivery, facilitate founder review, publishing, markpires.com/blogs, Goliath Social, distribution, repurposing and ongoing asset compounding.\n".
 "- When Mark asks a normal question, answer first in natural spoken language. When he commissions work, name the Executives and the concrete next action.\n".
 "- When current facts or web research are required, state the tool or Executive you will use and rely on returned evidence before claiming an answer.\n\n".
 "EXECUTIVE TEAM\n".implode("\n",$teamLines)."\n\n".
 "CURRENT LIVE WORK\n".($workLines?implode("\n",$workLines):"No active V112 missions were returned.")."\n\n".
 "RECENT FOUNDER-REVIEW ASSETS\n".($assetLines?implode("\n",$assetLines):"No review-ready artifacts were returned.")."\n\n".
 "AVAILABLE TOOLS AND SYSTEMS\n".($toolLines?implode("\n",$toolLines):"No tool registry records were returned.")."\n\n".
 "CONSTITUTION EXCERPT\n".$constitution;

 $brief=b117_compact($brief,28000);

 echo json_encode([
  'ok'=>true,
  'version'=>'V117.2 Goliath Brain Context',
  'brain_text'=>$brief,
  'team'=>$team,
  'current_work'=>$currentWork,
  'recent_assets'=>$recentAssets,
  'tools'=>$tools,
  'constitution_loaded'=>$constitution!=='',
  'constitution_directory'=>$constitutionDir,
  'generated_at'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

}catch(Throwable $e){
 http_response_code(500);
 echo json_encode([
  'ok'=>false,'version'=>'V117.2 Goliath Brain Context','error'=>'caught_exception',
  'details'=>['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>