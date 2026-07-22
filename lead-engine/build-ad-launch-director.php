<?php
/**
 * V15.4 Ad Launch Director
 * Upload: /public_html/lead-engine/build-ad-launch-director.php
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

  function sb154($method,$endpoint,$payload=null){
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
  function rows154($t,$q){$r=sb154('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function has154($txt,$words){$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)return true;}return false;}
  function scoreWords154($txt,$words,$pts){$s=0;$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)$s+=$pts;}return min(100,$s);}
  function townBudget154($town,$score){
    $base=25;
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true))$base=45;
    elseif(in_array($town,['Fairfield','Wilton','Weston','Stamford','Norwalk'],true))$base=35;
    if($score>=90)$base+=25; elseif($score>=80)$base+=15;
    return $base;
  }
  function evalCampaign154($c){
    $blob=implode(' ',[$c['campaign_name']??'',$c['hook']??'',$c['headline']??'',$c['primary_text']??'',$c['brand_pillar']??'',$c['target_town']??'',$c['primary_offer']??'']);
    $creative=35+scoreWords154($blob,['secret','mistake','truth','hidden','home value','seller','equity','timing','house detective','discover ct','local'],7);
    $seller=25+scoreWords154($blob,['seller','sell','home value','valuation','listing','equity','downsizing','expired','fsbo','price'],10);
    $urgency=20+scoreWords154($blob,['now','today','window','before','mistake','market shifted','do not wait','2026','private review'],8);
    $compliance=90;
    if(has154($blob,['guaranteed','guarantee','best agent','number one','must sell','buyer waiting']))$compliance=55;
    $traffic=(int)($c['traffic_score']??50);
    $score=round($creative*.25+$seller*.25+$urgency*.15+$traffic*.15+$compliance*.20);
    $score=max(0,min(100,$score));
    $reco='review';
    if($score>=84 && $compliance>=75)$reco='launch';
    elseif($score>=68 && $compliance>=75)$reco='improve';
    elseif($compliance<75)$reco='hold';
    else $reco='reject';
    return [$creative,$seller,$urgency,$compliance,$score,$reco];
  }

  $existing=rows154('ad_launch_campaigns','select=source_table,source_asset_id,campaign_name&limit=5000');
  $seen=[]; foreach($existing as $e){$seen[strtolower(($e['source_table']??'').':'.($e['source_asset_id']??'').':'.($e['campaign_name']??''))]=true;}

  $creative=rows154('creative_intelligence_assets','select=*&status=eq.active&recommendation=in.(publish,repurpose,create)&order=creative_impact_score.desc&limit=300');
  $mine=rows154('content_mine_assets','select=*&status=eq.active&total_content_mine_score=gte.72&order=total_content_mine_score.desc&limit=200');
  $traffic=rows154('traffic_performance','select=*&order=traffic_date.desc,traffic_score.desc&limit=50');
  $seller=rows154('seller_acquisition_director','select=*&status=eq.active&mark_priority=eq.true&order=acquisition_score.desc&limit=50');

  $trafficScore=50;
  if(!empty($traffic[0]['traffic_score']))$trafficScore=(int)$traffic[0]['traffic_score'];

  $new=[];

  foreach($creative as $a){
    $sid=(string)($a['id']??'');
    $name='Ad: '.($a['title']??'Creative Asset');
    $k=strtolower('creative_intelligence_assets:'.$sid.':'.$name);
    if(isset($seen[$k]))continue;
    $town=$a['town']??'Fairfield County';
    $brand=$a['brand_pillar']??'seller_authority';
    $type='seller_lead';
    if($brand==='discover_ct')$type='discover_ct';
    if($brand==='house_detective')$type='house_detective';
    if(($a['asset_type']??'')==='landing_page')$type='valuation';
    $hook=$a['hook'] ?: ($a['recommended_caption']??'');
    $headline=$hook ?: ($a['title']??'Private Home Value Review');
    $primary='Fairfield County homeowners: online estimates miss the local details buyers actually pay for. Mark Pires can give you a private, local read on timing, value, and strategy.';
    if($brand==='discover_ct')$primary='Discover CT highlights the real people, places, and stories that make Connecticut home. Follow along and discover what locals already know.';
    if($brand==='house_detective')$primary='The House Detective is on the case. A smarter, more memorable way to talk about homes, pricing, timing, and the story behind every listing.';
    $c=[
      'campaign_date'=>date('Y-m-d'),'campaign_name'=>$name,'campaign_type'=>$type,'brand_pillar'=>$brand,'target_audience'=>($a['audience']??'seller'),
      'target_town'=>$town,'target_market'=>'Fairfield County','primary_offer'=>'Private Home Value Review','landing_page_url'=>'https://markpires.com/home-valuation.html',
      'source_asset_id'=>$sid,'source_table'=>'creative_intelligence_assets','hook'=>$hook,'headline'=>$headline,'primary_text'=>$primary,'description'=>$a['description']??'',
      'cta'=>'Get My Home Value','creative_prompt'=>'Create a premium Fairfield County real estate ad graphic for: '.$headline,
      'image_direction'=>'Premium local Fairfield County real estate visual. Warm, high-trust, modern, clean. Include Mark Pires / Discover CT brand feel.',
      'video_direction'=>'Short 15-30 second social video with strong hook, local proof, clear CTA.',
      'traffic_score'=>$trafficScore,'raw_payload'=>$a,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    [$creativeScore,$sellerScore,$urgency,$compliance,$score,$reco]=evalCampaign154($c);
    $c['creative_score']=$creativeScore;$c['seller_score']=$sellerScore;$c['urgency_score']=$urgency;$c['compliance_score']=$compliance;$c['launch_score']=$score;$c['launch_recommendation']=$reco;
    $c['recommended_budget']=townBudget154($town,$score);
    $c['checklist']=['landing_page'=>true,'clear_cta'=>true,'no_guarantees'=>$compliance>=75,'brand_fit'=>true,'needs_image'=>true,'needs_approval'=>true];
    $new[]=$c;
  }

  foreach($mine as $a){
    $sid=(string)($a['id']??'');
    $name='Repurpose Ad: '.($a['recommended_title'] ?: ($a['original_title']??'Content Mine Winner'));
    $k=strtolower('content_mine_assets:'.$sid.':'.$name);
    if(isset($seen[$k]))continue;
    $town=$a['town']??'Fairfield County'; $brand=$a['brand_pillar']??'mark_pires';
    $type=$brand==='discover_ct'?'discover_ct':($brand==='house_detective'?'house_detective':'seller_lead');
    $c=[
      'campaign_date'=>date('Y-m-d'),'campaign_name'=>$name,'campaign_type'=>$type,'brand_pillar'=>$brand,'target_audience'=>'local homeowners',
      'target_town'=>$town,'target_market'=>'Fairfield County','primary_offer'=>'Local Real Estate Insight','landing_page_url'=>'https://markpires.com/',
      'source_asset_id'=>$sid,'source_table'=>'content_mine_assets','hook'=>$a['recommended_hook']??'','headline'=>$a['recommended_title']??$name,
      'primary_text'=>$a['recommended_caption']??'A local moment worth bringing back from Mark Pires and Discover CT.',
      'description'=>$a['notes']??'','cta'=>'Learn More','creative_prompt'=>'Create a social graphic based on this repurposed content: '.($a['recommended_hook']??$name),
      'image_direction'=>'Authentic local Connecticut content visual, social-first, bold headline, warm community tone.',
      'video_direction'=>'Repurpose original archive into a short clip with captions, local hook, and CTA.',
      'traffic_score'=>$trafficScore,'raw_payload'=>$a,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    [$creativeScore,$sellerScore,$urgency,$compliance,$score,$reco]=evalCampaign154($c);
    $c['creative_score']=$creativeScore;$c['seller_score']=$sellerScore;$c['urgency_score']=$urgency;$c['compliance_score']=$compliance;$c['launch_score']=$score;$c['launch_recommendation']=$reco;
    $c['recommended_budget']=townBudget154($town,$score);
    $c['checklist']=['source_content'=>true,'clear_cta'=>true,'no_guarantees'=>$compliance>=75,'brand_fit'=>true,'needs_image'=>true,'needs_approval'=>true];
    $new[]=$c;
  }

  // Add one direct seller valuation campaign if seller acquisition has opportunities.
  if(!empty($seller)){
    $top=$seller[0];
    $town=$top['town']??'Fairfield County';
    $name=$town.' Home Value Campaign';
    $k=strtolower('seller_acquisition_director:daily:'.$name);
    if(!isset($seen[$k])){
      $c=[
        'campaign_date'=>date('Y-m-d'),'campaign_name'=>$name,'campaign_type'=>'valuation','brand_pillar'=>'seller_authority','target_audience'=>'homeowners',
        'target_town'=>$town,'target_market'=>'Fairfield County','primary_offer'=>'Private Home Value Review','landing_page_url'=>'https://markpires.com/home-valuation.html',
        'source_asset_id'=>'daily','source_table'=>'seller_acquisition_director','hook'=>'Most online estimates miss what buyers are really paying for in '.$town.'.',
        'headline'=>$town.' Homeowners: Get A Private Value Review','primary_text'=>'If you own a home in '.$town.', your online estimate may not reflect today’s buyer demand, condition, timing, or neighborhood nuance. Request a private home value review from Mark Pires.',
        'description'=>'Seller lead campaign generated from V14.5 seller acquisition signals.','cta'=>'Get My Home Value',
        'creative_prompt'=>'Create a premium '.$town.' homeowner valuation ad. Elegant Fairfield County home, confident local expert tone, no hype.',
        'image_direction'=>'Luxury local home exterior, warm trust-building design, gold/black/white Mark Pires feel.',
        'video_direction'=>'15-second seller ad: online estimates miss local detail, Mark gives private review, CTA.',
        'traffic_score'=>$trafficScore,'raw_payload'=>$top,'created_at'=>date('c'),'updated_at'=>date('c')
      ];
      [$creativeScore,$sellerScore,$urgency,$compliance,$score,$reco]=evalCampaign154($c);
      $c['creative_score']=$creativeScore;$c['seller_score']=$sellerScore;$c['urgency_score']=$urgency;$c['compliance_score']=$compliance;$c['launch_score']=$score;$c['launch_recommendation']=$reco;
      $c['recommended_budget']=townBudget154($town,$score);
      $c['checklist']=['valuation_page'=>true,'seller_signal'=>true,'clear_cta'=>true,'no_guarantees'=>$compliance>=75,'needs_image'=>true,'needs_approval'=>true];
      $new[]=$c;
    }
  }

  usort($new,function($a,$b){return $b['launch_score']<=>$a['launch_score'];});
  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,200),50) as $chunk){
    $r=sb154('POST','ad_launch_campaigns',$chunk);
    if($r['ok']){
      $inserted[]=['count'=>count($chunk),'http'=>$r['http']];
      foreach($r['data'] as $campaign){
        $cid=$campaign['id'];
        $assets=[
          ['asset_type'=>'meta_ad','platform'=>'Meta','headline'=>$campaign['headline'],'body'=>$campaign['primary_text'],'description'=>$campaign['description'],'cta'=>$campaign['cta'],'creative_prompt'=>$campaign['creative_prompt']],
          ['asset_type'=>'instagram_caption','platform'=>'Instagram','headline'=>$campaign['hook'],'body'=>$campaign['primary_text'].' '.$campaign['cta'],'description'=>'Caption version','cta'=>$campaign['cta'],'creative_prompt'=>$campaign['creative_prompt']],
          ['asset_type'=>'image_prompt','platform'=>'V16 Image Studio','headline'=>$campaign['headline'],'body'=>$campaign['image_direction'],'description'=>'Image generation prompt','cta'=>$campaign['cta'],'creative_prompt'=>$campaign['creative_prompt']],
          ['asset_type'=>'landing_copy','platform'=>'markpires.com','headline'=>$campaign['headline'],'body'=>$campaign['primary_text'],'description'=>'Landing page copy block','cta'=>$campaign['cta'],'creative_prompt'=>'']
        ];
        $assetRows=[];
        foreach($assets as $a){$assetRows[]=array_merge($a,['campaign_id'=>$cid,'status'=>'draft','raw_payload'=>$campaign,'created_at'=>date('c'),'updated_at'=>date('c')]);}
        sb154('POST','ad_launch_assets',$assetRows);
      }
    } else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows154('ad_launch_campaigns','select=*&status=eq.draft&order=launch_score.desc,created_at.desc&limit=500');
  $counts=['launch'=>0,'improve'=>0,'hold'=>0,'budget'=>0];
  foreach($all as $c){ if(($c['launch_recommendation']??'')==='launch')$counts['launch']++; if(($c['launch_recommendation']??'')==='improve')$counts['improve']++; if(($c['launch_recommendation']??'')==='hold')$counts['hold']++; if(($c['launch_recommendation']??'')==='launch')$counts['budget']+=(float)($c['recommended_budget']??0); }

  $recs=[
    'Launch only campaigns marked LAUNCH after Mark approval.',
    'Use V16 Image Studio to generate creative for the image_prompt assets.',
    'Start with $25-$75/day tests and scale only when V15.2 shows appointment or ready-to-contact signal.',
    'Avoid guarantees, pressure language, and unsupported claims.'
  ];

  $brief="V15.4 AD LAUNCH DIRECTOR\\n========================================\\n\\n";
  $brief.="Draft Campaigns:         ".count($all)."\\n";
  $brief.="Launch Ready:            ".$counts['launch']."\\n";
  $brief.="Needs Improvement:       ".$counts['improve']."\\n";
  $brief.="Compliance Hold:         ".$counts['hold']."\\n";
  $brief.="Recommended Test Budget: $".number_format($counts['budget'],0)."/day\\n";
  $brief.="Created This Run:        ".count($new)."\\n\\n";
  $brief.="TOP CAMPAIGNS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,20) as $i=>$c){
    $brief.=($i+1).". ".$c['campaign_name']." — ".$c['target_town']." — ".$c['launch_recommendation']." — Score ".$c['launch_score']." — $".number_format((float)$c['recommended_budget'],0)."/day\\n";
    $brief.="   Hook: ".$c['hook']."\\n";
    $brief.="   CTA: ".$c['cta']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_campaigns'=>count($all),'launch_ready'=>$counts['launch'],'needs_improvement'=>$counts['improve'],
    'hold_count'=>$counts['hold'],'top_campaign'=>$all[0]['campaign_name']??'','total_recommended_budget'=>$counts['budget'],
    'campaigns'=>array_slice($all,0,30),'briefing_text'=>$brief,'recommendations'=>$recs,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb154('POST','ad_launch_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb154('PATCH','ad_launch_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'created_candidates'=>count($new),
    'draft_campaigns'=>count($all),
    'launch_ready'=>$counts['launch'],
    'recommended_daily_budget'=>$counts['budget'],
    'briefing'=>$brief,
    'inserted'=>$inserted,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>