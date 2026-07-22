<?php
/**
 * V15 Creative Intelligence Director
 * Upload: /public_html/lead-engine/build-creative-intelligence-director.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb15($method,$endpoint,$payload=null){
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
  function rows15($t,$q){$r=sb15('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function score_text15($text,$words,$points){$s=0;$t=strtolower($text);foreach($words as $w){if(strpos($t,strtolower($w))!==false)$s+=$points;}return min(100,$s);}
  function asset15($o){
    $base=[
      'asset_date'=>date('Y-m-d'),'asset_type'=>'idea','brand_pillar'=>'mark_pires','title'=>'','hook'=>'','description'=>'','source_url'=>'',
      'source_platform'=>'','source_file'=>'','town'=>'','audience'=>'seller','funnel_stage'=>'awareness','emotional_score'=>0,
      'authority_score'=>0,'lead_gen_score'=>0,'local_relevance_score'=>0,'shareability_score'=>0,'evergreen_score'=>0,
      'repurpose_score'=>0,'production_effort_score'=>50,'creative_impact_score'=>0,'recommendation'=>'review','recommended_channel'=>'',
      'recommended_caption'=>'','recommended_cta'=>'','recommended_asset_plan'=>'','status'=>'active','approved_for_distribution'=>false,
      'pushed_to_asset_vault'=>false,'pushed_to_content_calendar'=>false,'raw_payload'=>[],'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }
  function evaluate15($a){
    $blob=($a['title']??'').' '.($a['hook']??'').' '.($a['description']??'').' '.($a['brand_pillar']??'').' '.($a['asset_type']??'').' '.($a['town']??'');
    $em=30+score_text15($blob,['mistake','secret','truth','never','hidden','behind the scenes','emotional','family','legacy','dream','fear','winning'],7);
    $auth=30+score_text15($blob,['20 years','market','data','guide','expert','realtor','valuation','seller','buyer','fairfield county','connecticut'],7);
    $lead=20+score_text15($blob,['home value','valuation','sell','seller','listing','call mark','text mark','appointment','cma','move'],10);
    $local=20+score_text15($blob,['greenwich','westport','darien','new canaan','fairfield','wilton','weston','stamford','norwalk','fairfield county','ct'],10);
    $share=25+score_text15($blob,['top 5','best','worst','before','after','secret','tour','street interview','house detective','discover ct'],8);
    $ever=30+score_text15($blob,['guide','how to','checklist','mistakes','valuation','market','relocation','downsizing'],8);
    $rep=30+score_text15($blob,['discover ct','street interview','house detective','listing video','market update','clip','short','archive','repurpose'],10);
    $eff=isset($a['production_effort_score'])?(int)$a['production_effort_score']:50;
    $impact=round($em*.16+$auth*.18+$lead*.22+$local*.16+$share*.12+$ever*.10+$rep*.10-$eff*.04);
    $impact=max(0,min(100,$impact));
    $rec='review';
    if($impact>=88)$rec='publish';
    elseif($rep>=75 && $impact>=72)$rec='repurpose';
    elseif($impact>=72)$rec='create';
    elseif($impact>=58)$rec='rewrite';
    else $rec='archive';
    $channel='Facebook / Instagram / LinkedIn';
    if(($a['asset_type']??'')==='short'||str_contains(strtolower($a['description']??''),'short'))$channel='TikTok / Instagram Reels / YouTube Shorts';
    if(($a['asset_type']??'')==='blog')$channel='Blog + LinkedIn + Facebook';
    $cta='Call or text Mark Pires at 203-247-2655 for a private strategy conversation.';
    if(($a['audience']??'')==='buyer')$cta='Message Mark for the Fairfield County buyer game plan.';
    $plan='Create one primary asset, then repurpose into 3 short posts, 1 email, and 1 blog/social caption.';
    return [$em,$auth,$lead,$local,$share,$ever,$rep,$eff,$impact,$rec,$channel,$cta,$plan];
  }

  // Seed core Mark content pillars if empty.
  $existing=rows15('creative_intelligence_assets','select=id&limit=1');
  $createdSeed=0;
  if(empty($existing)){
    $seed=[
      ['asset_type'=>'video','brand_pillar'=>'discover_ct','title'=>'Discover CT Street Interview: Why People Love Their Town','hook'=>'The most honest Fairfield County content comes from people on the street.','description'=>'Repurpose existing Discover CT street interviews into daily town clips for local authority.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>20],
      ['asset_type'=>'video','brand_pillar'=>'house_detective','title'=>'House Detective Seller Series','hook'=>'The case of the seller who waited too long.','description'=>'Noir-style seller education using House Detective persona to teach timing, pricing, and market strategy.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>45],
      ['asset_type'=>'blog','brand_pillar'=>'seller_authority','title'=>'Fairfield County Home Value Guide','hook'=>'Most homeowners are sitting on more equity than they realize.','description'=>'SEO/AEO seller guide explaining valuation, equity, timing, and why online estimates miss local nuance.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>35],
      ['asset_type'=>'short','brand_pillar'=>'seller_authority','title'=>'Seller Market Window Short','hook'=>'This seller market will not last forever.','description'=>'Short-form seller tip about record equity, downsizing, moving out of state, and acting during the current window.','audience'=>'seller','town'=>'Greenwich','production_effort_score'=>25],
      ['asset_type'=>'ad','brand_pillar'=>'mark_pires','title'=>'Private Home Value Review Ad','hook'=>'Online estimates miss what buyers are really paying for in your town.','description'=>'High-intent seller ad driving to home valuation funnel and Jessica follow-up.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>30],
      ['asset_type'=>'door_knocking','brand_pillar'=>'seller_authority','title'=>'Town Seller Letter Campaign','hook'=>'Your home may be worth more than you think in this market.','description'=>'Repurpose town-specific door knocking letters into blog posts, emails, and Facebook content.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>40],
      ['asset_type'=>'lead_magnet','brand_pillar'=>'seller_authority','title'=>'Downsizing From Fairfield County Guide','hook'=>'The smart downsizer’s guide to selling at the right time.','description'=>'Lead magnet for longtime homeowners considering Florida, Carolinas, or smaller local options.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>55],
      ['asset_type'=>'video','brand_pillar'=>'beatseat','title'=>'BeatSeat Origin Story For Authority','hook'=>'Before real estate, Mark built something nobody had ever seen.','description'=>'Use inventor/creator credibility to deepen trust and differentiate Mark from generic agents.','audience'=>'seller','town'=>'Fairfield County','production_effort_score'=>25]
    ];
    $payload=[];
    foreach($seed as $s){[$em,$auth,$lead,$local,$share,$ever,$rep,$eff,$impact,$rec,$chan,$cta,$plan]=evaluate15($s);$payload[]=asset15(array_merge($s,['emotional_score'=>$em,'authority_score'=>$auth,'lead_gen_score'=>$lead,'local_relevance_score'=>$local,'shareability_score'=>$share,'evergreen_score'=>$ever,'repurpose_score'=>$rep,'production_effort_score'=>$eff,'creative_impact_score'=>$impact,'recommendation'=>$rec,'recommended_channel'=>$chan,'recommended_cta'=>$cta,'recommended_asset_plan'=>$plan,'recommended_caption'=>$s['hook'].' '.$cta,'raw_payload'=>$s]));}
    $sr=sb15('POST','creative_intelligence_assets',$payload); if($sr['ok'])$createdSeed=count($payload);
  }

  // Pull from existing creative review / asset vault if available.
  $sources=[];
  $sources['creative_review_items']=rows15('creative_review_items','select=*&order=created_at.desc&limit=300');
  $sources['asset_vault_items']=rows15('asset_vault_items','select=*&order=created_at.desc&limit=300');
  $sources['seller_acquisition_director']=rows15('seller_acquisition_director','select=*&status=eq.active&order=acquisition_score.desc&limit=100');

  $existingAll=rows15('creative_intelligence_assets','select=source_platform,source_url,title&limit=3000');
  $seen=[]; foreach($existingAll as $e){$seen[strtolower(($e['source_platform']??'').':'.($e['source_url']??'').':'.($e['title']??''))]=true;}

  $new=[];
  foreach($sources as $table=>$rows){
    foreach($rows as $r){
      $title=$r['title']??$r['asset_title']??$r['campaign_name']??$r['name']??$r['address']??'Jessica Creative Item';
      $url=$r['source_url']??$r['url']??'';
      $k=strtolower($table.':'.$url.':'.$title);
      if(isset($seen[$k]))continue;
      $type=$r['asset_type']??$r['creative_type']??($table==='seller_acquisition_director'?'seller_content':'idea');
      $brand='mark_pires';
      $desc=$r['description']??$r['notes']??$r['recommended_action']??$r['brief']??'';
      if(stripos($title.' '.$desc,'discover')!==false)$brand='discover_ct';
      if(stripos($title.' '.$desc,'detective')!==false)$brand='house_detective';
      if(stripos($title.' '.$desc,'seller')!==false)$brand='seller_authority';
      $a=['asset_type'=>$type,'brand_pillar'=>$brand,'title'=>$title,'hook'=>$r['hook']??'','description'=>$desc,'source_url'=>$url,'source_platform'=>$table,'town'=>$r['town']??'','audience'=>$r['audience']??'seller','raw_payload'=>$r];
      [$em,$auth,$lead,$local,$share,$ever,$rep,$eff,$impact,$rec,$chan,$cta,$plan]=evaluate15($a);
      $new[]=asset15(array_merge($a,['emotional_score'=>$em,'authority_score'=>$auth,'lead_gen_score'=>$lead,'local_relevance_score'=>$local,'shareability_score'=>$share,'evergreen_score'=>$ever,'repurpose_score'=>$rep,'production_effort_score'=>$eff,'creative_impact_score'=>$impact,'recommendation'=>$rec,'recommended_channel'=>$chan,'recommended_cta'=>$cta,'recommended_asset_plan'=>$plan,'recommended_caption'=>($a['hook']?:$title).' '.$cta]));
    }
  }

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,600),100) as $chunk){$r=sb15('POST','creative_intelligence_assets',$chunk);if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];else $errors[]=['http'=>$r['http'],'body'=>$r['body']];}

  // Re-score all active assets.
  $assets=rows15('creative_intelligence_assets','select=*&status=eq.active&order=created_at.desc&limit=1500');
  foreach($assets as $a){
    [$em,$auth,$lead,$local,$share,$ever,$rep,$eff,$impact,$rec,$chan,$cta,$plan]=evaluate15($a);
    sb15('PATCH','creative_intelligence_assets?id=eq.'.rawurlencode($a['id']),[
      'emotional_score'=>$em,'authority_score'=>$auth,'lead_gen_score'=>$lead,'local_relevance_score'=>$local,'shareability_score'=>$share,
      'evergreen_score'=>$ever,'repurpose_score'=>$rep,'production_effort_score'=>$eff,'creative_impact_score'=>$impact,'recommendation'=>$rec,
      'recommended_channel'=>$chan,'recommended_cta'=>$cta,'recommended_asset_plan'=>$plan,'updated_at'=>date('c')
    ]);
  }

  $all=rows15('creative_intelligence_assets','select=*&status=eq.active&order=creative_impact_score.desc,created_at.desc&limit=1500');
  $counts=['publish'=>0,'create'=>0,'repurpose'=>0,'rewrite'=>0,'seller'=>0,'discover'=>0,'detective'=>0];
  foreach($all as $a){$rec=$a['recommendation']??''; if(isset($counts[$rec]))$counts[$rec]++; if(($a['audience']??'')==='seller')$counts['seller']++; if(($a['brand_pillar']??'')==='discover_ct')$counts['discover']++; if(($a['brand_pillar']??'')==='house_detective')$counts['detective']++;}

  $recs=[
    'Repurpose high-score existing Discover CT and House Detective content before filming from scratch.',
    'Publish seller-authority assets tied to the hottest acquisition towns.',
    'Every approved asset should create one primary post, three short captions, one email, and one blog/social recap.',
    'Blotato should only receive assets after Jessica marks them publish or repurpose.'
  ];

  $brief="V15 CREATIVE INTELLIGENCE DIRECTOR\\n========================================\\n\\n";
  $brief.="Total Assets:       ".count($all)."\\n";
  $brief.="Publish Now:        ".$counts['publish']."\\n";
  $brief.="Create Next:        ".$counts['create']."\\n";
  $brief.="Repurpose:          ".$counts['repurpose']."\\n";
  $brief.="Rewrite/Improve:    ".$counts['rewrite']."\\n";
  $brief.="Seller Assets:      ".$counts['seller']."\\n";
  $brief.="Discover CT:        ".$counts['discover']."\\n";
  $brief.="House Detective:    ".$counts['detective']."\\n";
  $brief.="Seed Assets Added:  ".$createdSeed."\\n";
  $brief.="Imported Assets:    ".count($new)."\\n\\n";
  $brief.="TOP CREATIVE PRIORITIES\\n----------------------------------------\\n";
  foreach(array_slice($all,0,25) as $i=>$a){
    $brief.=($i+1).". ".$a['title']." — ".$a['brand_pillar']." — ".$a['recommendation']." — Score ".$a['creative_impact_score']."\\n";
    $brief.="     Channel: ".$a['recommended_channel']."\\n";
    $brief.="     Plan: ".$a['recommended_asset_plan']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_assets'=>count($all),'publish_count'=>$counts['publish'],'create_count'=>$counts['create'],
    'repurpose_count'=>$counts['repurpose'],'rewrite_count'=>$counts['rewrite'],'seller_assets'=>$counts['seller'],
    'discover_ct_assets'=>$counts['discover'],'house_detective_assets'=>$counts['detective'],'top_asset'=>$all[0]['title']??'',
    'top_assets'=>array_slice($all,0,30),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb15('POST','creative_intelligence_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key'))sb15('PATCH','creative_intelligence_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);

  $work="V15 WEEKLY CREATIVE WORKPLAN\\n========================================\\n\\nFILM THIS WEEK\\n";
  foreach(array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='create')),0,5) as $i=>$a){$work.=($i+1).". ".$a['title']."\\n";}
  $work.="\\nPUBLISH THIS WEEK\\n";
  foreach(array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='publish')),0,5) as $i=>$a){$work.=($i+1).". ".$a['title']."\\n";}
  $work.="\\nREPURPOSE THIS WEEK\\n";
  foreach(array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='repurpose')),0,5) as $i=>$a){$work.=($i+1).". ".$a['title']."\\n";}

  $wp=[[
    'workplan_date'=>date('Y-m-d'),'film_this_week'=>array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='create')),0,5),
    'publish_this_week'=>array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='publish')),0,5),
    'repurpose_this_week'=>array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='repurpose')),0,5),
    'improve_this_week'=>array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='rewrite')),0,5),
    'ignore_this_week'=>array_slice(array_values(array_filter($all,fn($x)=>($x['recommendation']??'')==='archive')),0,5),
    'workplan_text'=>$work,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $wr=sb15('POST','creative_weekly_workplan',$wp);
  if(!$wr['ok'] && str_contains($wr['body'],'duplicate key'))sb15('PATCH','creative_weekly_workplan?workplan_date=eq.'.rawurlencode(date('Y-m-d')),$wp[0]);

  echo json_encode(['success'=>empty($errors),'total_assets'=>count($all),'publish'=>$counts['publish'],'create'=>$counts['create'],'repurpose'=>$counts['repurpose'],'seed_added'=>$createdSeed,'imported'=>count($new),'briefing'=>$brief,'workplan'=>$work,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>