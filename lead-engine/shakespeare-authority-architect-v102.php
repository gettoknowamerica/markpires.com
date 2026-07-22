<?php
/**
 * V102.0 Shakespeare Authority Architect
 * Turns Shakespeare from blog writer into campaign + authority cluster builder.
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_GET['key']??'';
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

 function uid102($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function col102($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins102($t,$row){$safe=[];foreach($row as $k=>$v){if(col102($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function one102($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function slug102($s){$s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
 function detect_town102($title){foreach(['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Fairfield','Wilton','Weston','Ridgefield','Trumbull','Monroe','Shelton','Easton','Bridgeport'] as $t){if(stripos($title,$t)!==false)return $t;}return null;}
 function scenario102($title){$t=strtolower($title);if(strpos($t,'expired')!==false)return 'expired_seller';if(strpos($t,'absentee')!==false)return 'absentee_owner';if(strpos($t,'waterfront')!==false)return 'waterfront';if(strpos($t,'buyer')!==false)return 'buyer';if(strpos($t,'seller')!==false)return 'seller';if(strpos($t,'california')!==false||strpos($t,'relocation')!==false)return 'relocation';if(strpos($t,'modern')!==false)return 'modern_homes';return 'authority';}
 function audience102($scenario,$town){switch($scenario){case 'expired_seller':return 'Homeowners whose listing expired or was withdrawn';case 'absentee_owner':return 'Absentee owners who need a simple long-distance selling plan';case 'waterfront':return 'Luxury buyers and sellers interested in Connecticut waterfront living';case 'buyer':return 'Buyers preparing to tour or relocate to Connecticut';case 'seller':return 'Connecticut homeowners preparing to sell';case 'relocation':return 'NYC, California, and out-of-state relocation buyers';default:return $town?"People researching {$town}, Connecticut":'Connecticut real estate buyers and sellers';}}
 function article102($title,$town,$scenario,$audience){
   $place=$town?:'Connecticut';
   return "<h1>".htmlspecialchars($title)."</h1>\n<p><strong>Authority angle:</strong> This is not a generic blog. It is a campaign foundation for {$audience}.</p>\n<h2>The Story</h2>\n<p>In {$place}, real estate decisions are rarely just about bedrooms, bathrooms, and price. They are about timing, trust, lifestyle, family, commute, community, and whether the move truly feels right. After nearly 20 years helping Fairfield County clients, Mark Pires understands that the best advice usually starts with the story behind the move.</p>\n<h2>What Matters Most</h2>\n<p>For this audience, the strongest message is clarity. The goal is to help people understand their options, avoid expensive mistakes, and feel confident enough to take the next step.</p>\n<h2>Local Authority</h2>\n<p>This page should be expanded with verified local details: neighborhoods, parks, restaurants, commute notes, market stats, town lifestyle, schools where appropriate, and internal links to related MarkPires.com authority pages.</p>\n<h2>Mark's Take</h2>\n<p>The best strategy is not to chase generic advice. It is to understand the specific situation, prepare the right plan, and use local knowledge to create leverage.</p>\n<h2>Call to Action</h2>\n<p>Call or text Mark Pires at 203-247-2655 for a local strategy conversation before making your next move.</p>";
 }
 function score102($scenario,$town){$base=78;if($town)$base+=6;if(in_array($scenario,['expired_seller','absentee_owner','waterfront','relocation']))$base+=7;return min(96,$base);}

 $limit=max(1,min(40,(int)($_GET['limit']??12)));
 $seeds=[];
 $existing=one102("SELECT COUNT(*) c FROM shakespeare_campaign_packages");
 if(((int)($existing['c']??0))<5){
  $seeds=[
   'Selling an Absentee-Owned Home in Connecticut',
   'My House Expired — Am I Doomed or Can I Win the Relaunch?',
   'Connecticut Waterfront Living Guide',
   'Why California Buyers Are Moving to Connecticut',
   'Greenwich CT Complete Town Guide',
   'Stamford CT Complete Town Guide',
   'Darien CT Complete Town Guide',
   'New Canaan CT Complete Town Guide',
   'Westport CT Complete Town Guide',
   'Fairfield CT Complete Town Guide',
   'Top 5 Things Buyers Should Do Before Touring Homes',
   'Top 5 Things to Do Before Selling Your Connecticut Home'
  ];
 } else {
  $rows=gdb_all("SELECT title FROM executive_initiatives WHERE executive_key='shakespeare' AND status IN ('recommended','proposed') ORDER BY id DESC LIMIT {$limit}")?:[];
  foreach($rows as $r)$seeds[]=$r['title'];
 }
 $created=[];
 foreach(array_slice($seeds,0,$limit) as $title){
  $slug=slug102($title);
  if(one102("SELECT id FROM shakespeare_campaign_packages WHERE slug=? LIMIT 1",[$slug]))continue;
  $town=detect_town102($title);$scenario=scenario102($title);$aud=audience102($scenario,$town);$score=score102($scenario,$town);
  $visuals=[
   ['type'=>'hero','request'=>$town?"Beautiful cinematic {$town}, CT establishing shot":"Premium Connecticut real estate hero image"],
   ['type'=>'b_roll','request'=>'Local lifestyle, homes, parks, downtown, waterfront where appropriate'],
   ['type'=>'graphic','request'=>'Map/stat/market visual for authority and trust']
  ];
  $video=[
   'length'=>'15 seconds','workflow_suggestion'=>'wan27_cinematic for simple town/lifestyle; wan26_multishot for story campaigns',
   'hook'=>"Open with a cinematic visual that immediately communicates {$title}.",
   'scenes'=>['Establish location/topic','Show lifestyle/property/emotion','Show problem or opportunity','End with room for Mark Pires CTA']
  ];
  $email=['subject'=>"A quick thought on {$title}",'preview'=>'Helpful local guidance from Mark Pires','body'=>'Jessica should adapt this package into a warm relationship email with one helpful link and a simple CTA.'];
  $social=['facebook'=>'Story-driven local post','instagram'=>'Short hook + carousel/reel caption','linkedin'=>'Authority post for professional trust','youtube'=>'Description and tags for companion video'];
  $seo=['primary_keyword'=>$title,'slug'=>$slug,'internal_links'=>['/blog/','/home-valuation.html','/contact.html'],'schema'=>['Article','FAQPage','LocalBusiness']];
  $faq=[['q'=>'Who is this for?','a'=>$aud],['q'=>'What should I do first?','a'=>'Start with a local strategy conversation before making public moves.'],['q'=>'How can Mark help?','a'=>'Mark combines local market experience, Discover CT authority, and targeted marketing strategy.']];
  $next=['Create PDF guide','Create 15-second video','Create email sequence','Create town variation','Ask Sherlock for verification','Ask Einstein for SEO/schema','Ask Scorsese for visuals'];
  $id=ins102('shakespeare_campaign_packages',[
   'package_uid'=>uid102('shakepkg'),'source'=>'v102_authority_architect','title'=>$title,'slug'=>$slug,'audience'=>$aud,'scenario'=>$scenario,'town'=>$town,
   'status'=>$score>=92?'ready_for_review':'needs_enrichment','authority_score'=>$score,'research_score'=>70,'seo_score'=>82,'story_score'=>84,'visual_score'=>65,'conversion_score'=>78,'verification_score'=>50,
   'article_html'=>article102($title,$town,$scenario,$aud),'article_summary'=>"Authority campaign package for {$aud}.",
   'faq_json'=>json_encode($faq,JSON_UNESCAPED_SLASHES),'email_json'=>json_encode($email,JSON_UNESCAPED_SLASHES),'social_json'=>json_encode($social,JSON_UNESCAPED_SLASHES),
   'video_brief_json'=>json_encode($video,JSON_UNESCAPED_SLASHES),'visual_requests_json'=>json_encode($visuals,JSON_UNESCAPED_SLASHES),'seo_json'=>json_encode($seo,JSON_UNESCAPED_SLASHES),
   'schema_json'=>json_encode($seo['schema'],JSON_UNESCAPED_SLASHES),'internal_links_json'=>json_encode($seo['internal_links'],JSON_UNESCAPED_SLASHES),
   'sherlock_request_json'=>json_encode(['verify'=>['facts','market stats','town details','claims','sources']],JSON_UNESCAPED_SLASHES),
   'scorsese_request_json'=>json_encode($video,JSON_UNESCAPED_SLASHES),'jessica_request_json'=>json_encode($email,JSON_UNESCAPED_SLASHES),
   'einstein_request_json'=>json_encode(['optimize'=>['headline','schema','internal links','CTA','featured snippet']],JSON_UNESCAPED_SLASHES),
   'pandora_request_json'=>json_encode(['trend_check'=>['seasonal hook','social timing','viral angle']],JSON_UNESCAPED_SLASHES),
   'next_opportunities_json'=>json_encode($next,JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()
  ]);
  $created[]=['id'=>$id,'title'=>$title,'score'=>$score,'status'=>$score>=92?'ready_for_review':'needs_enrichment'];
 }

 $towns=['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Fairfield','Wilton','Weston','Ridgefield','Trumbull','Monroe','Shelton'];
 $clusters=[];
 foreach($towns as $town){
  if(one102("SELECT id FROM shakespeare_authority_clusters WHERE town=? AND cluster_type='town' LIMIT 1",[$town]))continue;
  $pages=[
   "{$town} CT Complete Town Guide","{$town} Schools Guide","{$town} Restaurants and Downtown Guide","{$town} Parks and Recreation Guide",
   "{$town} Luxury Homes Guide","{$town} Moving Guide","{$town} Market Report","Selling a Home in {$town}"
  ];
  $id=ins102('shakespeare_authority_clusters',[
   'cluster_uid'=>uid102('cluster'),'cluster_type'=>'town','cluster_name'=>"{$town} CT Authority Cluster",'town'=>$town,'topic'=>'town authority','audience'=>'buyers sellers relocation luxury',
   'status'=>'active','authority_score'=>55,'pages_json'=>json_encode($pages,JSON_UNESCAPED_SLASHES),'missing_pages_json'=>json_encode($pages,JSON_UNESCAPED_SLASHES),
   'internal_links_json'=>json_encode(['/blog/','/home-valuation.html','/contact.html'],JSON_UNESCAPED_SLASHES),
   'next_action'=>"Build and interlink the first three {$town} authority pages.",'created_at'=>gdb_now()
  ]);
  $clusters[]=['id'=>$id,'town'=>$town];
 }
 echo json_encode(['ok'=>true,'version'=>'V102.0 Shakespeare Authority Architect','created_packages'=>count($created),'packages'=>$created,'created_clusters'=>count($clusters),'clusters'=>$clusters,'next'=>'Open /dashboard/shakespeare-authority-architect.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V102.0 Shakespeare Authority Architect','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);}
?>