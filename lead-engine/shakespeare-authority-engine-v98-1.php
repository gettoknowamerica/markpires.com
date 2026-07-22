<?php
/**
 * V98.1 Shakespeare Authority Engine
 * Creates review-ready content packages for scenario articles and town authority pages.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 if(file_exists(__DIR__.'/executive-kernel-v96.php')) require_once __DIR__.'/executive-kernel-v96.php';
 if(file_exists(__DIR__.'/goliath-normalize.php')) require_once __DIR__.'/goliath-normalize.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function uid981($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function slug981($s){$s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
 function townslug981($t){return slug981($t.' CT');}
 function esc981($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
 function scenario_html981($title,$scenario,$audience,$prompt,&$meta){
   $keyword=[
    'expired_seller'=>'expired listing Connecticut',
    'buyer_top_5'=>'Connecticut home buyer checklist',
    'seller_top_5'=>'prepare home for sale Connecticut',
    'california_to_ct'=>'California buyers moving to Connecticut',
    'modern_ct_homes'=>'modern homes Connecticut',
    'waterfront_living'=>'Connecticut waterfront homes',
    'absentee_owner'=>'absentee owner selling Connecticut'
   ][$scenario] ?? $title;
   $meta=['primary_keyword'=>$keyword,'secondary'=>'Fairfield County real estate, Mark Pires Realtor, Connecticut homes, buyer guide, seller guide'];
   return '<article class="mp-article"><header><p class="eyebrow">Mark Pires Guide</p><h1>'.esc981($title).'</h1><p class="lede">A practical, human-first guide from Mark Pires for Connecticut buyers, sellers, and relocating families.</p></header>
   <section><h2>Why this matters now</h2><p>'.esc981($prompt).'</p><p>The strongest real estate decisions come from clarity before pressure. This guide is designed to help you slow down, organize your thinking, and move forward with confidence.</p></section>
   <section><h2>The Mark Pires Framework</h2><ol><li>Know your true goal before reacting to the market.</li><li>Write down your top five must-haves and top five deal breakers.</li><li>Compare notes with your spouse, partner, or trusted advisor.</li><li>Use the list to focus only on homes or strategies you would actually pursue.</li><li>Work with an agent who can translate emotion, timing, and market data into a plan.</li></ol></section>
   <section><h2>What most people miss</h2><p>Many buyers and sellers lose time by chasing the wrong information. The goal is not more noise. The goal is better context, better timing, and better execution.</p></section>
   <section><h2>How Mark can help</h2><p>Mark combines local experience, valuation strategy, Discover CT storytelling, and modern listing media to help clients make informed real estate moves across Fairfield County and Connecticut.</p></section>
   <section class="cta"><h2>Want a local strategy?</h2><p>Call or text Mark Pires at 203-247-2655 or email mark@markpires.com.</p></section></article>';
 }
 function town_html981($town,&$meta){
   $slug=townslug981($town);
   $meta=['primary_keyword'=>$town.' CT real estate','secondary'=>$town.' restaurants, '.$town.' parks, '.$town.' homes for sale, '.$town.' luxury homes, living in '.$town.' Connecticut'];
   return '<article class="town-hub"><header class="town-hero"><p class="eyebrow">Discover CT Town Guide</p><h1>'.esc981($town).' CT Living, Real Estate & Local Guide</h1><p class="lede">A living local authority hub for buyers, sellers, relocation families, and homeowners considering their next move in '.esc981($town).'.</p></header>
   <section><h2>Living in '.esc981($town).'</h2><p>'.esc981($town).' offers a distinct Connecticut lifestyle shaped by neighborhoods, schools, commuting patterns, parks, restaurants, and long-term property value. This page is designed to become a living guide that Mark Pires and Discover CT can continuously enrich with local video, interviews, market updates, and community insights.</p></section>
   <section><h2>Local Highlights</h2><div class="guide-grid"><div><h3>Restaurants & Coffee</h3><p>Shakespeare will refresh this section with vetted local hotspots, reviews, and Discover CT mentions.</p></div><div><h3>Parks & Outdoor Life</h3><p>Feature parks, trails, beaches, fields, playgrounds, and lifestyle assets that matter to buyers.</p></div><div><h3>History & Character</h3><p>Add town history, architectural identity, notable districts, and local stories.</p></div><div><h3>Market Snapshot</h3><p>Einstein will attach median price, inventory, days on market, absorption, and luxury trend data.</p></div></div></section>
   <section><h2>For Buyers</h2><p>Before touring homes in '.esc981($town).', write your top five must-haves and top five deal breakers. This protects time and focuses your search on homes you would actually pursue.</p></section>
   <section><h2>For Sellers</h2><p>The right strategy combines pricing, presentation, timing, targeted digital exposure, and local storytelling. Mark’s listing approach can pair valuation strategy with Scorsese-level media and Discover CT community context.</p></section>
   <section><h2>Discover CT Video</h2><p>Embed Mark’s local Discover CT video or town interview here.</p><div class="video-placeholder">DISCOVER CT VIDEO EMBED AREA</div></section>
   <section><h2>FAQs About '.esc981($town).' Real Estate</h2><details><summary>Is '.esc981($town).' a good town for buyers?</summary><p>That depends on budget, lifestyle, commute, schools, inventory, and home style. Mark can help compare it against nearby towns.</p></details><details><summary>How should sellers prepare?</summary><p>Start with a valuation, condition review, presentation plan, and marketing strategy before choosing a list price.</p></details></section>
   <section class="cta"><h2>Thinking about '.esc981($town).'?</h2><p>Call or text Mark Pires at 203-247-2655 for a local strategy session.</p></section></article>';
 }
 $limit=max(1,min(50,(int)($_GET['limit']??10)));
 if(function_exists('gx96_boot')) $boot=gx96_boot('shakespeare',['mission_type'=>'authority_engine','title'=>'Create authority content packages for Jessica and Mark review']);
 $rows=gdb_all("SELECT * FROM shakespeare_content_queue WHERE status='queued' ORDER BY priority DESC,id ASC LIMIT {$limit}")?:[];
 $created=[];
 foreach($rows as $q){
   $meta=[]; $type=$q['request_type']; $title=$q['title'];
   if($type==='town_authority_page'){
     $html=town_html981($q['town'],$meta);
     $slug=slug981($q['town'].'-ct');
     $contentType='town_authority_page';
     $scenario=null;
     $recommendedFor='town_authority,relocation,buyer,seller,luxury';
   } else {
     $html=scenario_html981($title,$q['scenario'],$q['audience'],$q['prompt'],$meta);
     $slug=slug981($title);
     $contentType='scenario_article';
     $scenario=$q['scenario'];
     $recommendedFor=$scenario;
   }
   $schema=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$title,'author'=>['@type'=>'Person','name'=>'Mark Pires'],'publisher'=>['@type'=>'Organization','name'=>'Mark Pires Real Estate']];
   $social=['facebook'=>'New local guide from Mark Pires: '.$title,'linkedin'=>'Helpful Connecticut real estate resource: '.$title,'instagram'=>'New guide is ready: '.$title];
   $email='Mark asked me to send this short resource because it may be relevant to your situation: '.$title;
   $id=gdb_insert('shakespeare_content_packages',[
    'package_uid'=>uid981('spkg'),'content_type'=>$contentType,'title'=>$title,'slug'=>$slug,'town'=>$q['town'],'scenario'=>$scenario,'audience'=>$q['audience'],
    'primary_keyword'=>$meta['primary_keyword']??$title,'secondary_keywords'=>$meta['secondary']??'','recommended_for'=>$recommendedFor,'status'=>'draft','priority'=>$q['priority'],
    'hero_image_prompt'=>'Create a premium Connecticut real estate editorial hero image for: '.$title,
    'summary'=>'Review-ready Shakespeare authority package for '.$title,
    'html_content'=>$html,'text_content'=>strip_tags($html),'meta_title'=>substr($title.' | Mark Pires CT Real Estate',0,250),
    'meta_description'=>'A practical Connecticut real estate guide from Mark Pires: '.$title,
    'schema_json'=>json_encode($schema,JSON_UNESCAPED_SLASHES),'social_captions_json'=>json_encode($social,JSON_UNESCAPED_SLASHES),
    'email_blurb'=>$email,'jessica_use_case'=>'Jessica can send this to leads matching: '.$recommendedFor,'einstein_status'=>'pending','approval_status'=>'needs_review','created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   gdb_update('shakespeare_content_queue',['status'=>'complete','package_id'=>$id,'updated_at'=>gdb_now()],'id=:id',['id'=>(int)$q['id']]);
   gdb_insert('shakespeare_content_library',['library_uid'=>uid981('slib'),'package_id'=>$id,'label'=>$title,'scenario'=>$scenario?:'town_authority','audience'=>$q['audience'],'recommended_blog'=>'/blog/'.$slug.'.html','email_blurb'=>$email,'status'=>'active','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $created[]=['package_id'=>$id,'title'=>$title,'type'=>$contentType,'slug'=>$slug];
 }
 echo json_encode(['ok'=>true,'version'=>'V98.1 Shakespeare Authority Engine','created_count'=>count($created),'created'=>$created,'next'=>'Open /dashboard/shakespeare-authority-center.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.1 Shakespeare Authority Engine','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>