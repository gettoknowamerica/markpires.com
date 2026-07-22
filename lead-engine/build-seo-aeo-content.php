<?php
/**
 * V12.12 SEO / AEO Content Engine
 * Upload: /public_html/lead-engine/build-seo-aeo-content.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb1212($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function slug1212($s){
  $s=strtolower(trim((string)$s));
  $s=preg_replace('/[^a-z0-9]+/','-',$s);
  return trim($s,'-');
}
function faq1212($town,$type){
  if($type==='seller'){
    return [
      ['q'=>"What is my {$town} home worth right now?",'a'=>"A true local value depends on recent sales, condition, updates, lot, street, buyer demand, and timing. Jessica can help start a smarter valuation review with Mark Pires."],
      ['q'=>"Is now a good time to sell in {$town}?",'a'=>"It depends on your price range, property condition, competition, and buyer demand. A local review gives a clearer answer than online estimates."],
      ['q'=>"How should I prepare my {$town} home before selling?",'a'=>"Start with pricing strategy, small repairs, staging, photography, and timing. Mark can help prioritize what matters most."]
    ];
  }
  if($type==='relocation'){
    return [
      ['q'=>"Is {$town} good for NYC buyers?",'a'=>"Many NYC buyers compare commute, schools, space, walkability, taxes, and lifestyle before choosing a Fairfield County town."],
      ['q'=>"How do I compare Fairfield County towns?",'a'=>"Start with commute, budget, schools, property type, and lifestyle. Jessica can help narrow the search."],
      ['q'=>"Should I sell in New York before buying in Connecticut?",'a'=>"That depends on financing, timing, and inventory. Mark can help coordinate a practical plan."]
    ];
  }
  return [
    ['q'=>"Are there builder opportunities in {$town}?",'a'=>"Builder opportunities may include land, teardowns, renovation candidates, and properties with expansion or subdivision potential."],
    ['q'=>"How are builder opportunities evaluated?",'a'=>"Jessica looks at town, property type, likely demand, project fit, and potential referral value."],
    ['q'=>"Can builders join a Fairfield County opportunity watchlist?",'a'=>"Yes. Builder and developer contacts can be tracked for land, teardown, and acquisition opportunities."]
  ];
}
function schema1212($town,$type,$title,$desc,$url){
  return [
    '@context'=>'https://schema.org',
    '@type'=>'RealEstateAgent',
    'name'=>'Mark Pires',
    'url'=>'https://markpires.com',
    'areaServed'=>$town,
    'description'=>$desc,
    'makesOffer'=>[
      '@type'=>'Offer',
      'name'=>$title,
      'url'=>$url
    ],
    'sameAs'=>[
      'https://markpires.com'
    ]
  ];
}
function outline1212($town,$type){
  if($type==='seller'){
    return [
      "Why {$town} sellers need a local valuation",
      "What online estimates miss",
      "How condition, updates, and street influence price",
      "When to sell in {$town}",
      "How Jessica and Mark help you decide"
    ];
  }
  if($type==='relocation'){
    return [
      "Why buyers are moving from NYC/Westchester to Fairfield County",
      "How {$town} compares for lifestyle and commute",
      "Budget, home types, and tradeoffs",
      "How to narrow your town list",
      "How Jessica helps with the next step"
    ];
  }
  return [
    "Why {$town} matters to builders and developers",
    "Land, teardown, and renovation opportunity signals",
    "How to evaluate project fit",
    "How Mark connects opportunities with builders",
    "How to join the opportunity watchlist"
  ];
}

$discovery = sb1212('GET','discovery_opportunity_queue?select=*&order=priority_score.desc,created_at.desc&limit=300')['data'];
$hunter = sb1212('GET','hunter_priority_rankings?select=*&order=hunter_score.desc,created_at.desc&limit=300')['data'];

$items=[];

foreach($discovery as $o){
  if(!is_array($o)) continue;
  $type=$o['opportunity_type'] ?? 'seller';
  $town=$o['town'] ?: 'Fairfield County';
  $market=$o['market'] ?? '';
  $aud=$o['audience'] ?? '';
  $score=(int)($o['priority_score'] ?? 50);

  if($type==='sellers' || $type==='seller'){
    $contentType='seller';
    $kw="{$town} home value";
    $title="What Is My {$town} Home Worth? Local Seller Guide";
    $desc="Get a smarter local look at {$town} home values, seller timing, pricing strategy, and what online estimates miss.";
    $slug=slug1212("{$town} home value seller guide");
    $cta='Get My Home Value';
  } elseif($type==='buyers' || $type==='relocation'){
    $contentType='relocation';
    $kw="moving to {$town} CT";
    $title="Moving To {$town} CT: Buyer And Relocation Guide";
    $desc="Compare {$town} lifestyle, commute, housing, and buyer strategy before moving from NYC or Westchester to Connecticut.";
    $slug=slug1212("moving to {$town} ct buyer guide");
    $cta='Get My Town Match';
  } else {
    $contentType='builder';
    $kw="{$town} builder opportunities";
    $title="{$town} Builder And Developer Opportunity Watchlist";
    $desc="Track land, teardown, renovation, and development signals for builders and investors in {$town}.";
    $slug=slug1212("{$town} builder developer opportunities");
    $cta='Join Builder Watchlist';
  }

  $url='https://markpires.com/'.$slug;
  $items[]=[
    'opportunity_date'=>date('Y-m-d'),
    'content_type'=>$contentType,
    'market'=>$market,
    'town'=>$town,
    'audience'=>$aud,
    'keyword_primary'=>$kw,
    'keyword_secondary'=>$o['offer'] ?? '',
    'search_intent'=>$aud,
    'title'=>$title,
    'meta_description'=>$desc,
    'slug'=>$slug,
    'h1'=>$title,
    'outline'=>outline1212($town,$contentType),
    'faq'=>faq1212($town,$contentType),
    'schema_json'=>schema1212($town,$contentType,$title,$desc,$url),
    'cta'=>$cta,
    'priority_score'=>$score,
    'status'=>'draft',
    'raw_payload'=>$o,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

foreach(array_slice($hunter,0,100) as $h){
  if(!is_array($h)) continue;
  $type=$h['hunter_type'] ?? 'seller';
  $town=$h['town'] ?: 'Fairfield County';
  $score=(int)($h['hunter_score'] ?? 50);
  if($type==='seller'){
    $title="{$town} Seller Signals: What Local Homeowners Should Know";
    $slug=slug1212("{$town} seller signals");
    $desc="Jessica is tracking seller demand, home value questions, and timing signals for {$town} homeowners.";
    $cta='Check My Selling Position';
  } elseif(in_array($type,['buyer','relocation'],true)){
    $title="{$town} Buyer Demand: NYC And Westchester Relocation Signals";
    $slug=slug1212("{$town} buyer relocation demand");
    $desc="A local look at buyer and relocation demand around {$town}, including NYC and Westchester movement.";
    $cta='Get My Buyer Plan';
  } else {
    $title="{$town} Builder Signals And Opportunity Watch";
    $slug=slug1212("{$town} builder signals");
    $desc="Jessica tracks builder and developer opportunity signals for {$town} and Fairfield County.";
    $cta='Join Builder Watchlist';
  }
  $url='https://markpires.com/'.$slug;
  $items[]=[
    'opportunity_date'=>date('Y-m-d'),
    'content_type'=>$type==='seller'?'seller':(in_array($type,['buyer','relocation'],true)?'relocation':'builder'),
    'market'=>$h['market'] ?? '',
    'town'=>$town,
    'audience'=>$h['audience'] ?? '',
    'keyword_primary'=>$title,
    'keyword_secondary'=>$h['reason'] ?? '',
    'search_intent'=>'AEO answer page and organic opportunity capture',
    'title'=>$title,
    'meta_description'=>$desc,
    'slug'=>$slug,
    'h1'=>$title,
    'outline'=>outline1212($town,$type==='seller'?'seller':(in_array($type,['buyer','relocation'],true)?'relocation':'builder')),
    'faq'=>faq1212($town,$type==='seller'?'seller':(in_array($type,['buyer','relocation'],true)?'relocation':'builder')),
    'schema_json'=>schema1212($town,$type,$title,$desc,$url),
    'cta'=>$cta,
    'priority_score'=>$score,
    'status'=>'draft',
    'raw_payload'=>$h,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

usort($items,function($a,$b){ return ($b['priority_score']<=>$a['priority_score']); });

$inserted=[];$errors=[];
foreach(array_chunk(array_slice($items,0,500),100) as $chunk){
  $r=sb1212('POST','seo_aeo_content_opportunities',$chunk);
  if($r['ok']) $inserted[]=['count'=>count($chunk),'http'=>$r['http']];
  else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
}

$top=array_slice($items,0,15);
$seller=0;$buyer=0;$builder=0;
foreach($items as $i){
  if($i['content_type']==='seller')$seller++;
  elseif($i['content_type']==='relocation')$buyer++;
  else $builder++;
}
$brief="SEO/AEO Content Briefing — ".date('Y-m-d')."\\n\\n";
$brief.="Total opportunities: ".count($items)."\\n";
$brief.="Seller pages: {$seller}\\n";
$brief.="Buyer/relocation pages: {$buyer}\\n";
$brief.="Builder pages: {$builder}\\n\\n";
$brief.="Top content opportunities:\\n";
foreach($top as $n=>$i){$brief.=($n+1).". ".$i['title']." — ".$i['slug']." — Score ".$i['priority_score']."\\n";}

$daily=[[
  'briefing_date'=>date('Y-m-d'),
  'total_opportunities'=>count($items),
  'seller_pages'=>$seller,
  'buyer_pages'=>$buyer,
  'relocation_pages'=>$buyer,
  'builder_pages'=>$builder,
  'top_opportunities'=>$top,
  'briefing_text'=>$brief,
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$dr=sb1212('POST','seo_aeo_daily_briefings',$daily);
if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
  sb1212('PATCH','seo_aeo_daily_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
}

echo json_encode([
  'success'=>empty($errors),
  'generated'=>count($items),
  'inserted'=>$inserted,
  'seller_pages'=>$seller,
  'buyer_relocation_pages'=>$buyer,
  'builder_pages'=>$builder,
  'briefing'=>$brief,
  'errors'=>$errors
], JSON_PRETTY_PRINT);
?>