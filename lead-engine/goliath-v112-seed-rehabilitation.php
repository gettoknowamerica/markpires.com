<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function s112_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function s112_uid(string $p):string{return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(s112_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$sourceUrl=(string)($_GET['url']??'https://www.markpires.com/blog/selling-an-absentee-owned-home-in-connecticut.html');
$title=(string)($_GET['title']??'Selling an Absentee-Owned Home in Connecticut');
$slug='selling-an-absentee-owned-home-in-connecticut';
$docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__)),'/\\');
$path=$docRoot.'/blog/'.$slug.'.html';
$currentHtml=is_file($path)?(string)file_get_contents($path):'';
$missionUid='rehab_absentee_owner_blog_v112';

$existing=gdb_one("SELECT id FROM goliath_v112_missions WHERE mission_uid=?",[$missionUid]);
if($existing){
 echo json_encode(['ok'=>true,'version'=>'V112.0 Rehabilitation Seed','created'=>false,'mission_id'=>$existing['id'],'message'=>'Mission already exists.'],JSON_PRETTY_PRINT);
 exit;
}

$missionId=gdb_insert('goliath_v112_missions',[
 'mission_uid'=>$missionUid,
 'mission_type'=>'blog_rehabilitation',
 'title'=>$title,
 'originator_key'=>'shakespeare',
 'status'=>'queued',
 'priority'=>100,
 'current_stage_no'=>1,
 'source_url'=>$sourceUrl,
 'source_payload_json'=>gdb_json([
   'slug'=>$slug,
   'existing_html'=>$currentHtml,
   'audience'=>'Connecticut absentee property owners',
   'campaign_gate'=>'Jessica must not send the campaign until Goliath delivers the rebuilt article.',
   'goal'=>'Create the most useful, trustworthy, visually strong Connecticut absentee-owner article possible.'
 ]),
 'created_at'=>gdb_now(),
 'updated_at'=>gdb_now()
]);

$sequence=[
 ['shakespeare','shakespeare_research_draft','Research and create a complete first draft',
  'Use OpenClaw first. Research the strongest current competing articles, Newspaper24k, configured scrapers, official Connecticut sources, respected local sources, and reader questions. Identify useful structure, facts, statistics, stories, gaps, objections, and calls to action. Do not copy wording. Produce a complete, detailed, publication-quality first draft, not an outline. Return strict JSON with keys: artifact_type,title,content_html,content_text,evidence,notes.'],
 ['scout','scout_competitive_intelligence','Add competitive and audience intelligence',
  'Review the complete draft. Use OpenClaw web research and available scrapers to find competing pages, missing reader questions, backlink opportunities, local examples, news angles, and search-language used by absentee owners. Improve the full article directly. Return the entire revised article in content_html plus evidence and notes.'],
 ['sherlock','sherlock_verification','Verify facts and Connecticut-specific claims',
  'Verify every material claim, statistic, Connecticut tax/legal reference, and market statement using authoritative sources. Remove or qualify unsupported claims. Add citations or source notes and a clear disclaimer for legal/tax matters. Return the entire corrected article, not a report.'],
 ['einstein','einstein_search_visibility','Optimize SEO, AEO and conversion',
  'Analyze search intent and top-ranking structure. Improve headings, title, meta description, FAQ targets, featured-snippet opportunities, entities, internal links, schema recommendations, readability, and conversion without keyword stuffing. Return the entire improved article.'],
 ['pandora','pandora_story_emotion','Add story, hooks and memorable framing',
  'Strengthen the opening story, emotional relevance, examples, transitions, memorable framing, and shareability while preserving accuracy. Return the entire improved article.'],
 ['scorsese','scorsese_visual_package','Create the visual production brief',
  'Create a concrete visual package for this exact article: featured-image prompt, section-image prompts, infographic concept, social image concepts, thumbnail concept, alt text, and placement instructions. If local media tools can create assets, include real output URLs/paths. Return strict JSON.'],
 ['mozart','mozart_audio_opportunity','Add optional audio opportunities',
  'Determine whether narration, a short audio summary, podcast segment, or music cue would materially improve this campaign. Produce tangible scripts/briefs only where useful. Return strict JSON.'],
 ['prospector','prospector_distribution','Add distribution and backlink opportunities',
  'Find specific outreach, local-media, backlink, partner, sponsor, newsletter, and social distribution opportunities for the finished article. Produce actionable targets and pitch angles. Return strict JSON.'],
 ['jessica','jessica_reader_campaign_review','Review reader value and campaign fit',
  'Review the full package as Mark Pires invisible assistant. Ensure it sounds like Mark, answers an absentee owner’s real concerns, supports a useful no-pressure email campaign, and contains clear next steps. Do not send anything yet. Return concrete revisions and campaign notes.'],
 ['rockefeller','rockefeller_roi_review','Score business value and priority',
  'Evaluate lead value, conversion potential, effort, risk, and best CTA. Add only concrete improvements that increase useful business value without making the article salesy. Return strict JSON.'],
 ['columbo','columbo_archive_enrichment','Search Mark’s archive for supporting gold',
  'Search Mark’s existing content archive for relevant Connecticut, real-estate, House Detective, Discover CT, motivational, comedic, or personal-story material that can enrich the article. Provide exact clips, timestamps, titles, or archive references where available.'],
 ['shakespeare','originator_final_review','Originator final editorial review',
  'You started this article. Review every contribution, preserve the original purpose, merge only the strongest additions, ensure the article is complete and beautiful, and return the final publication-ready HTML including title, meta description, sections, FAQs, CTA, image placeholders with alt text, and source notes. Return the entire final HTML.'],
 ['goliath','goliath_publish_deliver','Publish and deliver one finished asset',
  'Perform the final CEO gate. Approve only if the article is detailed, accurate, useful, visually planned, campaign-ready, and publication-ready. Return strict JSON: approved,artifact_type,title,content_html,meta_description,slug,delivery_notes. This stage produces exactly one finished delivered asset after publishing.']
];

foreach($sequence as $i=>$st){
 gdb_insert('goliath_v112_stages',[
  'mission_id'=>$missionId,
  'stage_no'=>$i+1,
  'executive_key'=>$st[0],
  'stage_key'=>$st[1],
  'title'=>$st[2],
  'instructions'=>$st[3],
  'status'=>$i===0?'ready':'waiting',
  'created_at'=>gdb_now(),
  'updated_at'=>gdb_now()
 ]);
}

gdb_insert('goliath_v112_events',[
 'mission_id'=>$missionId,'executive_key'=>'shakespeare','event_type'=>'mission_created',
 'title'=>'Absentee-owner blog entered the V112 production funnel',
 'details'=>'The weak article is blocked from campaign use until all stages finish and Goliath publishes one tangible asset.',
 'url'=>'/dashboard/shakespeare-authority-center.php?v112_mission='.$missionId,
 'created_at'=>gdb_now()
]);

echo json_encode([
 'ok'=>true,'version'=>'V112.0 Rehabilitation Seed','created'=>true,
 'mission_id'=>$missionId,'mission_uid'=>$missionUid,'stages'=>count($sequence),
 'status'=>'queued','finished_asset_count'=>0,
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>