<?php
/**
 * V15.3 Content Mine Director
 * Upload: /public_html/lead-engine/build-content-mine-director.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb153($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>[
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
      ],
      CURLOPT_TIMEOUT=>45
    ]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows153($t,$q){$r=sb153('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function has153($txt,$words){$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)return true;}return false;}
  function points153($txt,$words,$p){$s=0;$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)$s+=$p;}return min(100,$s);}

  function scoreAsset153($a){
    $blob=implode(' ',[
      $a['original_title']??'', $a['topic']??'', $a['notes']??'', $a['transcript']??'', $a['brand_pillar']??'', $a['town']??''
    ]);

    $emotion=25+points153($blob,['mistake','secret','truth','dream','family','legacy','emotional','funny','surprising','never','love','why'],8);
    $lead=20+points153($blob,['sell','seller','home value','valuation','listing','market','downsizing','relocation','move','equity','price'],10);
    $local=25+points153($blob,['greenwich','westport','darien','new canaan','fairfield','stamford','norwalk','wilton','weston','fairfield county','ct','connecticut','local'],8);
    $ever=25+points153($blob,['guide','how to','mistakes','market','tips','advice','buyers','sellers','relocation','downsizing'],8);
    $rep=30+points153($blob,['discover ct','house detective','street interview','interview','listing video','market update','clip','short','behind the scenes','tour'],8);
    $eff=(int)($a['production_effort_score']??20);
    $total=round($emotion*.20+$lead*.22+$local*.20+$ever*.14+$rep*.20-$eff*.04);
    $total=max(0,min(100,$total));

    $use='review';
    if($total>=88 && $rep>=70)$use='short';
    elseif($total>=82 && $lead>=70)$use='ad';
    elseif($total>=76 && $ever>=65)$use='blog';
    elseif($total>=65)$use='repost';
    else $use='archive';

    $brand=$a['brand_pillar']??'mark_pires';
    $title=$a['original_title']??'Content Mine Asset';
    $hook=$a['emotional_moment'] ?: ($a['best_quote'] ?: 'A local moment worth bringing back.');
    $cta='Call or text Mark Pires at 203-247-2655 for local real estate guidance.';
    if($brand==='discover_ct')$cta='Follow Discover CT for real local stories across Connecticut.';
    if($brand==='house_detective')$cta='Call The House Detective, Mark Pires, when your next move needs solving.';
    if(($a['content_type']??'')==='music' || $brand==='beatseat')$cta='See more from Mark Pires, inventor of The BeatSeat and Fairfield County Realtor.';

    $plan='Repurpose into: 1 short video, 1 caption, 1 story post, and 1 blog/email angle.';
    if($use==='blog')$plan='Turn into a blog post, LinkedIn article, Facebook post, and SEO/AEO answer section.';
    if($use==='ad')$plan='Turn into seller ad creative, landing page hook, email subject, and short video.';
    if($use==='archive')$plan='Archive for now unless paired with a stronger hook or current market angle.';

    return [$emotion,$lead,$local,$ever,$rep,$total,$use,$title,$hook,$cta,$plan];
  }

  // Seed high-value content-mine categories if empty
  $existing=rows153('content_mine_assets','select=id&limit=1');
  $seeded=0;
  if(empty($existing)){
    $seed=[
      ['archive_source'=>'manual','brand_pillar'=>'discover_ct','original_title'=>'Discover CT Street Interview Archive','topic'=>'Local street interviews','content_type'=>'interview','notes'=>'Existing Discover CT footage can be mined for authentic town clips, local opinions, restaurants, businesses, and community moments.','town'=>'Fairfield County','emotional_moment'=>'Real people explaining why they love where they live.','best_quote'=>'This is what people actually love about Connecticut.','production_effort_score'=>15],
      ['archive_source'=>'manual','brand_pillar'=>'house_detective','original_title'=>'House Detective Noir Listing Archive','topic'=>'House Detective episodes and listing concepts','content_type'=>'video','notes'=>'Noir Realtor content can be repurposed into seller education, listing hooks, thumbnails, and short-form comedy authority.','town'=>'Fairfield County','emotional_moment'=>'The house played hard to get.','best_quote'=>'The case looked cold until the House Detective showed up.','production_effort_score'=>25],
      ['archive_source'=>'manual','brand_pillar'=>'seller_authority','original_title'=>'Seller Tips and Market Update Archive','topic'=>'Seller education','content_type'=>'market_update','notes'=>'Past seller advice and market updates should be mined into evergreen tips, valuation posts, and listing appointment content.','town'=>'Fairfield County','emotional_moment'=>'Most homeowners do not realize how much timing changes their outcome.','best_quote'=>'Online estimates miss the story buyers are actually paying for.','production_effort_score'=>20],
      ['archive_source'=>'manual','brand_pillar'=>'beatseat','original_title'=>'BeatSeat / Music Authority Archive','topic'=>'Creator credibility and authority','content_type'=>'music','notes'=>'209 original songs, BeatSeat invention, MTV artist story, and daily live creation show can be used as trust-building founder content.','town'=>'Fairfield County','emotional_moment'=>'Before real estate, Mark built something nobody had ever seen.','best_quote'=>'Creativity is not a side story — it is the differentiator.','production_effort_score'=>20],
      ['archive_source'=>'manual','brand_pillar'=>'buyer_authority','original_title'=>'Buyer Advice Archive','topic'=>'Buyer education','content_type'=>'video','notes'=>'Buyer tips can be repurposed for relocation buyers, first-time buyers, NYC movers, and town-specific search advice.','town'=>'Fairfield County','emotional_moment'=>'Buying here is easier when someone tells you what the internet does not.','best_quote'=>'Every town has a different rhythm.','production_effort_score'=>25]
    ];
    $payload=[];
    foreach($seed as $s){
      [$emotion,$lead,$local,$ever,$rep,$total,$use,$title,$hook,$cta,$plan]=scoreAsset153($s);
      $payload[]=array_merge($s,[
        'asset_date'=>date('Y-m-d'),
        'emotional_score'=>$emotion,'lead_gen_score'=>$lead,'local_authority_score'=>$local,'evergreen_score'=>$ever,'repurpose_score'=>$rep,
        'total_content_mine_score'=>$total,'recommended_use'=>$use,'recommended_title'=>$title,'recommended_hook'=>$hook,
        'recommended_caption'=>$hook.' '.$cta,'recommended_cta'=>$cta,'recommended_plan'=>$plan,
        'status'=>'active','raw_payload'=>$s,'created_at'=>date('c'),'updated_at'=>date('c')
      ]);
    }
    $r=sb153('POST','content_mine_assets',$payload);
    if($r['ok'])$seeded=count($payload);
  }

  // Rescore all active content mine assets
  $assets=rows153('content_mine_assets','select=*&status=eq.active&order=created_at.desc&limit=2000');
  foreach($assets as $a){
    [$emotion,$lead,$local,$ever,$rep,$total,$use,$title,$hook,$cta,$plan]=scoreAsset153($a);
    sb153('PATCH','content_mine_assets?id=eq.'.rawurlencode($a['id']),[
      'emotional_score'=>$emotion,'lead_gen_score'=>$lead,'local_authority_score'=>$local,'evergreen_score'=>$ever,'repurpose_score'=>$rep,
      'total_content_mine_score'=>$total,'recommended_use'=>$use,'recommended_title'=>$title,'recommended_hook'=>$hook,
      'recommended_caption'=>$hook.' '.$cta,'recommended_cta'=>$cta,'recommended_plan'=>$plan,'updated_at'=>date('c')
    ]);
  }

  // Push approved/high-score content mine items into Creative Intelligence
  $toPush=rows153('content_mine_assets','select=*&status=eq.active&pushed_to_creative_intelligence=eq.false&total_content_mine_score=gte.72&order=total_content_mine_score.desc&limit=100');
  $pushed=0;$pushErrors=[];
  foreach($toPush as $a){
    $assetType='idea';
    if(($a['recommended_use']??'')==='short')$assetType='short';
    if(($a['recommended_use']??'')==='blog')$assetType='blog';
    if(($a['recommended_use']??'')==='ad')$assetType='ad';
    if(($a['recommended_use']??'')==='repost')$assetType='post';

    $ci=[[
      'asset_date'=>date('Y-m-d'),
      'asset_type'=>$assetType,
      'brand_pillar'=>$a['brand_pillar']??'mark_pires',
      'title'=>$a['recommended_title'] ?: ($a['original_title']??'Content Mine Asset'),
      'hook'=>$a['recommended_hook']??'',
      'description'=>$a['notes']??'',
      'source_url'=>$a['source_url']??'',
      'source_platform'=>'content_mine',
      'source_file'=>$a['source_file']??'',
      'town'=>$a['town']??'',
      'audience'=>($a['lead_gen_score']??0)>=65?'seller':'local',
      'funnel_stage'=>'awareness',
      'emotional_score'=>(int)($a['emotional_score']??0),
      'authority_score'=>(int)($a['local_authority_score']??0),
      'lead_gen_score'=>(int)($a['lead_gen_score']??0),
      'local_relevance_score'=>(int)($a['local_authority_score']??0),
      'shareability_score'=>(int)($a['repurpose_score']??0),
      'evergreen_score'=>(int)($a['evergreen_score']??0),
      'repurpose_score'=>(int)($a['repurpose_score']??0),
      'production_effort_score'=>(int)($a['production_effort_score']??20),
      'creative_impact_score'=>(int)($a['total_content_mine_score']??0),
      'recommendation'=>in_array(($a['recommended_use']??''),['short','blog','ad','repost'],true)?'repurpose':'review',
      'recommended_channel'=>'TikTok / Instagram / Facebook / YouTube Shorts',
      'recommended_caption'=>$a['recommended_caption']??'',
      'recommended_cta'=>$a['recommended_cta']??'',
      'recommended_asset_plan'=>$a['recommended_plan']??'',
      'status'=>'active',
      'approved_for_distribution'=>false,
      'raw_payload'=>$a,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];

    $r=sb153('POST','creative_intelligence_assets',$ci);
    if($r['ok']){
      $pushed++;
      $cid=$r['data'][0]['id']??'';
      sb153('PATCH','content_mine_assets?id=eq.'.rawurlencode($a['id']),[
        'pushed_to_creative_intelligence'=>true,'creative_asset_id'=>(string)$cid,'updated_at'=>date('c')
      ]);
    } else {
      $pushErrors[]=['id'=>$a['id'],'body'=>$r['body']];
    }
  }

  $all=rows153('content_mine_assets','select=*&status=eq.active&order=total_content_mine_score.desc,created_at.desc&limit=2000');
  $counts=['ready'=>0,'discover'=>0,'detective'=>0,'seller'=>0,'beatseat'=>0];
  foreach($all as $a){
    if((int)($a['total_content_mine_score']??0)>=72)$counts['ready']++;
    if(($a['brand_pillar']??'')==='discover_ct')$counts['discover']++;
    if(($a['brand_pillar']??'')==='house_detective')$counts['detective']++;
    if(($a['brand_pillar']??'')==='seller_authority')$counts['seller']++;
    if(($a['brand_pillar']??'')==='beatseat')$counts['beatseat']++;
  }

  $recs=[
    'Mine existing Discover CT and House Detective content before filming new content.',
    'Push all 72+ score assets into Creative Intelligence for weekly planning.',
    'Use V16 Image Studio later to generate graphics for top content-mine winners.',
    'Use Blotato only after Jessica marks a mined asset as repurpose-ready.'
  ];

  $brief="V15.3 CONTENT MINE DIRECTOR\\n========================================\\n\\n";
  $brief.="Total Archive Assets:       ".count($all)."\\n";
  $brief.="Repurpose Ready:            ".$counts['ready']."\\n";
  $brief.="Discover CT Assets:         ".$counts['discover']."\\n";
  $brief.="House Detective Assets:     ".$counts['detective']."\\n";
  $brief.="Seller Authority Assets:    ".$counts['seller']."\\n";
  $brief.="BeatSeat Assets:            ".$counts['beatseat']."\\n";
  $brief.="Seed Assets Added:          ".$seeded."\\n";
  $brief.="Pushed To Creative Intel:   ".$pushed."\\n\\n";
  $brief.="TOP CONTENT MINE WINNERS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,25) as $i=>$a){
    $brief.=($i+1).". ".$a['original_title']." — ".$a['brand_pillar']." — ".$a['recommended_use']." — Score ".$a['total_content_mine_score']."\\n";
    $brief.="   Hook: ".$a['recommended_hook']."\\n";
    $brief.="   Plan: ".$a['recommended_plan']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_assets'=>count($all),'repurpose_ready'=>$counts['ready'],
    'discover_ct_count'=>$counts['discover'],'house_detective_count'=>$counts['detective'],'seller_authority_count'=>$counts['seller'],
    'beatseat_count'=>$counts['beatseat'],'top_asset'=>$all[0]['original_title']??'','top_assets'=>array_slice($all,0,30),
    'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb153('POST','content_mine_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb153('PATCH','content_mine_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($pushErrors),
    'total_assets'=>count($all),
    'repurpose_ready'=>$counts['ready'],
    'seeded'=>$seeded,
    'pushed_to_creative_intelligence'=>$pushed,
    'briefing'=>$brief,
    'push_errors'=>$pushErrors
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>