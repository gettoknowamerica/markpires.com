<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function u1192_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function u1192_cols(string $table):array{
 $rows=gdb_all("SELECT column_name,column_type,is_nullable,column_default,extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=$r;return $out;
}
function u1192_default(string $column,string $type){
 $n=strtolower($column);$t=strtolower($type);
 if(str_contains($n,'uid'))return 'auto_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(12));
 if(str_contains($n,'status'))return 'created';
 if(str_contains($n,'type'))return 'artifact';
 if(str_contains($n,'key'))return 'goliath';
 if(str_contains($t,'int')||str_contains($t,'decimal'))return 0;
 if(str_contains($t,'date')||str_contains($t,'time'))return gdb_now();
 return '';
}
function u1192_insert(string $table,array $row):int{
 $cols=u1192_cols($table);$safe=[];
 foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 foreach($cols as $c=>$d){
  if(array_key_exists($c,$safe)||strtolower((string)$d['is_nullable'])==='yes'||$d['column_default']!==null||str_contains(strtolower((string)$d['extra']),'auto_increment'))continue;
  $safe[$c]=u1192_default($c,(string)$d['column_type']);
 }
 return (int)gdb_insert($table,$safe);
}
function u1192_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));}
function u1192_fetch(string $url):string{
 if(!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('Invalid URL.');
 $parts=parse_url($url);
 if(!in_array(strtolower((string)($parts['scheme']??'')),['https','http'],true))throw new RuntimeException('Only HTTP/HTTPS URLs are supported.');
 $ch=curl_init($url);
 curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,
  CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>45,
  CURLOPT_USERAGENT=>'GoliathOmni/119.2 MarkPires Internal Editorial Importer'
 ]);
 $html=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
 if($html===''||$code>=400)throw new RuntimeException("Could not fetch source URL. HTTP $code $error");
 return $html;
}
function u1192_article(string $html,string &$title):string{
 libxml_use_internal_errors(true);
 $dom=new DOMDocument();
 $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NOWARNING|LIBXML_NOERROR);
 $xp=new DOMXPath($dom);
 $titleNode=$xp->query('//h1')->item(0)?:$xp->query('//title')->item(0);
 $title=trim($titleNode?$titleNode->textContent:'Imported Editorial Asset');

 $candidates=[
  '//article',
  '//*[@role="main"]',
  '//main',
  '//*[contains(concat(" ",normalize-space(@class)," ")," blog-post ")]',
  '//*[contains(concat(" ",normalize-space(@class)," ")," article ")]'
 ];
 $node=null;
 foreach($candidates as $query){
  $list=$xp->query($query);
  if($list&&$list->length){$node=$list->item(0);break;}
 }
 if(!$node)$node=$xp->query('//body')->item(0);
 if(!$node)throw new RuntimeException('Could not identify article content.');

 foreach(iterator_to_array($xp->query('.//script|.//style|.//nav|.//footer|.//form',$node)) as $remove){
  $remove->parentNode?->removeChild($remove);
 }
 $inner='';
 foreach($node->childNodes as $child)$inner.=$dom->saveHTML($child);
 $plain=trim(strip_tags($inner));
 if(mb_strlen($plain)<200)throw new RuntimeException('Fetched page did not contain enough article content.');
 return $inner;
}
function u1192_stage(string $exec,bool $review=false):array{
 if($review)return ['originator_final_review','Shakespeare final editorial review',
  'Open the full evolved HTML. Perform the final edit directly in the article. Restore any stronger earlier wording when appropriate. Return the complete publish-ready HTML, never a review or notes.'];
 $map=[
  'shakespeare'=>['authority_content','Full editorial rebuild','Rewrite the actual HTML into a complete high-authority absentee-owner article. Replace generic filler with specific, compassionate and useful Connecticut guidance. Keep the complete article visible.'],
  'jessica'=>['relationship_campaign','Human Touch edit','Edit the actual HTML. Strengthen empathy, clarity, response options and the email/contact path for out-of-state owners.'],
  'scout'=>['competitive_intelligence','Owner-needs edit','Edit the actual HTML. Add the real questions absentee owners face: access, property condition, tenants, inherited ownership, repairs, vendors, timing and remote coordination. Never invent live data.'],
  'sherlock'=>['verification','Accuracy edit','Edit the actual HTML. Remove unsupported legal/tax claims and add careful Connecticut attorney, probate, tax and professional-advice disclaimers only where needed.'],
  'einstein'=>['seo_aeo','Search and answer-engine edit','Edit the actual HTML. Improve title/meta guidance, H2/H3 structure, FAQs, internal links, entities, schema-ready sections and concise answer blocks.'],
  'columbo'=>['archive_enrichment','Archive placement edit','Edit the actual HTML. Add visible, clearly labeled placements for real Discover CT/Mark archive embeds; use NEEDS_TOOL_ACCESS rather than invented links.'],
  'scorsese'=>['visual_media','Visual production edit','Edit the actual HTML. Add a hero-image block, inline visual placements, alt text, social-card brief and reel/video brief while preserving the complete article.'],
  'mozart'=>['audio_package','Audio adaptation edit','Edit the actual artifact. Preserve the full blog and add a compact narration/podcast adaptation block at the end.'],
  'prospector'=>['distribution','Distribution edit','Edit the actual artifact. Preserve the full blog and add a practical distribution/backlink/outreach plan in a clearly separated production section.'],
  'rockefeller'=>['roi_conversion','Conversion edit','Edit the actual HTML. Strengthen trust, property-specific CTA, valuation path and measurable lead conversion without making it feel generic or aggressive.'],
  'pandora'=>['creative_enrichment','Creative edit','Edit the actual HTML. Improve the opening, memorable framing, emotional resonance and derivative ideas without weakening accuracy.'],
  'goliath'=>['goliath_publish_deliver','Goliath delivery','Do not rewrite. The engine will clone the approved artifact for Founder review and attach publishing actions.']
 ];
 return $map[$exec];
}

$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))$input=array_merge($_POST,$_GET);
$key=trim((string)($input['key']??''));
if(!hash_equals(u1192_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $url=trim((string)($input['url']??''));
 if($url==='')throw new RuntimeException('Missing URL.');
 $html=u1192_fetch($url);$title='';$article=u1192_article($html,$title);

 $missionUid=u1192_uid('url_mission');
 $route=['shakespeare','jessica','scout','sherlock','einstein','columbo','scorsese','mozart','prospector','rockefeller','pandora','shakespeare','goliath'];

 gdb()->beginTransaction();
 try{
  $missionId=u1192_insert('goliath_v112_missions',[
   'mission_uid'=>$missionUid,'mission_type'=>'existing_url_editorial',
   'title'=>'Editorial Rebuild — '.$title,'originator_key'=>'shakespeare',
   'status'=>'queued','priority'=>10000,'current_stage_no'=>1,
   'source_payload_json'=>gdb_json([
    'directive'=>'Edit the exact imported live article through the full Executive workflow.',
    'source_url'=>$url,'artifact_contract'=>'v119.2-work-only',
    'expected_artifact_type'=>'blog','requested_by'=>'Mark Pires'
   ]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);

  $sourceVersionId=u1192_insert('goliath_v118_asset_versions',[
   'version_uid'=>u1192_uid('source'),'mission_id'=>$missionId,'stage_id'=>null,'stage_no'=>0,
   'executive_key'=>'source','artifact_type'=>'blog','title'=>$title,
   'content_html'=>$article,'content_text'=>'','artifact_url'=>$url,'artifact_path'=>null,
   'change_note'=>'Imported exact live page before Executive editing.',
   'source_version_id'=>null,'is_tangible'=>1,'qa_passed'=>1,'status'=>'source_import',
   'metadata_json'=>gdb_json(['source_url'=>$url,'contract'=>'v119.2']),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);

  foreach($route as $i=>$exec){
   [$stageKey,$stageTitle,$instructions]=u1192_stage($exec,$i===count($route)-2);
   u1192_insert('goliath_v112_stages',[
    'mission_id'=>$missionId,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$stageKey,
    'title'=>$stageTitle,'instructions'=>$instructions,
    'status'=>$i===0?'ready':'waiting',
    'input_artifact_id'=>$i===0?$sourceVersionId:null,
    'local_task_id'=>null,'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }

  u1192_insert('goliath_v112_events',[
   'event_uid'=>u1192_uid('event'),'mission_id'=>$missionId,'executive_key'=>'shakespeare',
   'event_type'=>'source_article_imported','title'=>'Live article imported for direct editing',
   'details'=>'The exact article is Version 0. Every following Executive must return a complete edited version.',
   'url'=>'/dashboard/goliath-workflow-review-v119-2.php?mission_id='.$missionId.'&stage=0&embed=1',
   'created_at'=>gdb_now()
  ]);
  gdb()->commit();
 }catch(Throwable $tx){if(gdb()->inTransaction())gdb()->rollBack();throw $tx;}

 echo json_encode([
  'ok'=>true,'version'=>'V119.2 Existing URL Commission',
  'mission_id'=>$missionId,'source_version_id'=>$sourceVersionId,
  'source_url'=>$url,'source_title'=>$title,'route'=>$route,
  'review_url'=>'/dashboard/goliath-workflow-review-v119-2.php?mission_id='.$missionId.'&stage=0',
  'message'=>'The exact live article is now Version 0. All later stages must display the complete edited work.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 if(gdb()->inTransaction())gdb()->rollBack();
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119.2 Existing URL Commission','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>