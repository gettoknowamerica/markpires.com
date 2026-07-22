<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function cp119_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function cp119_cols(string $table):array{
 $rows=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];
 $out=[];foreach($rows as $r)$out[(string)$r['column_name']]=true;return $out;
}
function cp119_insert(string $table,array $row):int{
 $cols=cp119_cols($table);$safe=[];foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
 if(!$safe)throw new RuntimeException("No compatible columns for $table");
 return (int)gdb_insert($table,$safe);
}
function cp119_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));}
function cp119_stage(string $exec,bool $originatorReview=false):array{
 if($originatorReview)return ['originator_final_review','Shakespeare final editorial review',
  'Inspect every preserved version. Select the strongest base, restore any superior earlier language, and return the complete polished publish-ready blog. Never return commentary alone.'];
 $map=[
  'shakespeare'=>['authority_content','Initial authority blog',
   'Create the full publish-ready HTML blog. Include title, compelling introduction, H2/H3 structure, Connecticut-specific guidance, FAQs, CTA, Mark Pires author section, image placeholders with descriptive alt text, internal-link suggestions and schema-ready content.'],
  'jessica'=>['relationship_campaign','Human Touch and audience pass',
   'Edit the complete blog directly. Make it warmer, more reassuring and useful to families dealing with inherited property. Add a natural response path, contact CTA and email/newsletter adaptation without removing the blog.'],
  'scout'=>['competitive_intelligence','Market intelligence pass',
   'Edit the complete blog directly. Add useful seller questions, local market considerations and clearly labeled facts or research needs. Do not fabricate live statistics.'],
  'sherlock'=>['verification','Connecticut verification pass',
   'Edit the complete blog directly. Remove unsupported legal or tax claims, add careful disclaimers where professional advice is required, and preserve only defensible Connecticut guidance.'],
  'einstein'=>['seo_aeo','SEO, AEO and schema pass',
   'Edit the complete HTML directly. Improve search intent, entity coverage, FAQs, featured-snippet structure, internal links, meta title/description suggestions and schema-ready organization.'],
  'columbo'=>['archive_enrichment','Archive and Discover CT enrichment',
   'Edit the complete blog directly. Add clearly marked placements for relevant Discover CT or Mark Pires archive clips, quotes or local references. Never invent an archive URL.'],
  'scorsese'=>['visual_media','Visual story and media pass',
   'Edit the complete blog directly. Add a cinematic hero-image brief, inline-image placements, social-card concepts, video/reel brief and production references. Keep the full blog visible and intact.'],
  'mozart'=>['audio_package','Audio and narration pass',
   'Edit the complete artifact directly. Add a concise podcast/narration adaptation and audio-production section while preserving the entire blog.'],
  'prospector'=>['distribution','Distribution and outreach pass',
   'Edit the complete artifact directly. Add concrete distribution, referral, partner, backlink and outreach opportunities tied to the blog. Preserve the full article.'],
  'rockefeller'=>['roi_conversion','Conversion and ROI pass',
   'Edit the complete artifact directly. Strengthen conversion paths, lead magnets, CTAs and measurable business outcomes without making the article salesy or reducing trust.'],
  'pandora'=>['creative_enrichment','Creative expansion pass',
   'Edit the complete artifact directly. Improve the emotional hook, memorable framing and derivative-content ideas while preserving accuracy and authority.'],
  'goliath'=>['goliath_publish_deliver','Goliath QA and Founder delivery',
   'Do not rewrite or summarize. Preserve the Shakespeare-approved complete artifact exactly. Verify that the asset is tangible, route it to Founder Review, and attach publishing, social, repurposing, archive and notification actions separately.']
 ];
 return $map[$exec];
}

$key=trim((string)($_GET['key']??$_POST['key']??''));
if(!hash_equals(cp119_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

try{
 $proofUid=cp119_uid('blog_proof');
 $missionUid=cp119_uid('mission');
 $title='2026 Guide to Selling an Inherited Home in Connecticut';
 $directive=
  "Create the definitive 2026 authority blog titled \"$title\" for Mark Pires. ".
  "The finished piece must be visually structured, compassionate, accurate, useful to Connecticut families, optimized for search and AI discovery, and ready for Founder review. ".
  "It must include image placements, FAQs, CTA, About Mark, publishing metadata and derivative-content plans. ".
  "Every Executive must edit the actual full artifact and return a complete evolved version.";

 $route=['shakespeare','jessica','scout','sherlock','einstein','columbo','scorsese','mozart','prospector','rockefeller','pandora','shakespeare','goliath'];

 gdb()->beginTransaction();
 try{
  $proofId=cp119_insert('goliath_v119_proof_tests',[
   'proof_uid'=>$proofUid,'proof_type'=>'full_blog_loop','status'=>'created',
   'expected_stages'=>count($route),'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  $missionId=cp119_insert('goliath_v112_missions',[
   'mission_uid'=>$missionUid,'mission_type'=>'v119_blog_proof','title'=>$title,
   'originator_key'=>'shakespeare','status'=>'queued','priority'=>9999,'current_stage_no'=>1,
   'proof_test_uid'=>$proofUid,
   'source_payload_json'=>gdb_json([
    'directive'=>$directive,'proof_test_uid'=>$proofUid,
    'artifact_contract'=>'v119-full-artifact','requested_by'=>'Mark Pires',
    'expected_artifact_type'=>'blog'
   ]),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
  foreach($route as $i=>$exec){
   [$stageKey,$stageTitle,$instructions]=cp119_stage($exec,$i===count($route)-2);
   cp119_insert('goliath_v112_stages',[
    'mission_id'=>$missionId,'stage_no'=>$i+1,'executive_key'=>$exec,'stage_key'=>$stageKey,
    'title'=>$stageTitle,'instructions'=>$instructions,'status'=>$i===0?'ready':'waiting',
    'local_task_id'=>null,'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
  }
  gdb_update('goliath_v119_proof_tests',['mission_id'=>$missionId,'status'=>'running'],'id=:id',['id'=>$proofId]);
  cp119_insert('goliath_v112_events',[
   'mission_id'=>$missionId,'executive_key'=>'shakespeare','event_type'=>'v119_proof_created',
   'title'=>$title,'details'=>'V119 full-blog evolving-artifact proof test created.',
   'url'=>'/dashboard/goliath-v119-blog-proof.php?mission_id='.$missionId,'created_at'=>gdb_now()
  ]);
  gdb()->commit();
 }catch(Throwable $tx){if(gdb()->inTransaction())gdb()->rollBack();throw $tx;}

 echo json_encode([
  'ok'=>true,'version'=>'V119 Blog Proof Creator',
  'proof_uid'=>$proofUid,'mission_id'=>$missionId,'title'=>$title,
  'expected_stages'=>count($route),'route'=>$route,
  'status_url'=>'/lead-engine/goliath-v119-proof-status.php?key='.rawurlencode($key).'&mission_id='.$missionId,
  'review_url'=>'/dashboard/goliath-v119-blog-proof.php?mission_id='.$missionId,
  'next'=>'Keep the production runtime running. The proof dashboard will show each tangible version as it is created.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 if(gdb()->inTransaction())gdb()->rollBack();
 http_response_code(500);
 echo json_encode(['ok'=>false,'version'=>'V119 Blog Proof Creator','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>