<?php
/**
 * V13.3 Creative Review Center Builder
 * Upload: /public_html/lead-engine/build-creative-review.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb133($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows133($table,$query){ $r=sb133('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function creative133($o){
    $base=[
      'creative_date'=>date('Y-m-d'),'source_table'=>'','source_id'=>'','creative_type'=>'ad','campaign_name'=>'','town'=>'','market'=>'Lower Fairfield County',
      'audience'=>'','objective'=>'lead_generation','headline'=>'','primary_text'=>'','description'=>'','cta'=>'Get My Home Value','landing_page'=>'',
      'image_prompt'=>'','video_prompt'=>'','design_notes'=>'','compliance_notes'=>'Review for fair housing, claims, accuracy, and brand fit before launch.',
      'priority_score'=>0,'status'=>'review','launch_ready'=>false,'raw_payload'=>[],'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }
  function town_image_prompt133($town,$audience){
    return "Premium Fairfield County real estate ad creative for {$town}, Connecticut. Elegant Connecticut neighborhood feel, warm natural light, tasteful luxury home exterior, trustworthy local real estate branding, clean modern layout with space for headline, no fake logos, no people close-up, sophisticated seller-focused tone. Audience: {$audience}.";
  }
  function video_prompt133($town,$angle){
    return "15-second vertical real estate ad video concept for {$town}, CT. Cinematic local homes, tree-lined streets, subtle motion, elegant captions, seller equity / market timing angle: {$angle}. End with CTA: Get Your Private Home Value Review.";
  }

  $today=date('Y-m-d');
  $items=[]; $seen=[];

  // 1. Live ad launch checklists -> reviewable ad creatives
  $ads=rows133('live_ad_launch_checklists','select=*&order=created_at.desc&limit=100');
  foreach($ads as $a){
    $name=$a['campaign_name']??'Ad Campaign';
    $town='';
    foreach(['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Wilton','Fairfield'] as $t){ if(str_contains($name,$t)){$town=$t;break;} }
    $key='livead:'.($a['id']??md5(json_encode($a)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $items[]=creative133([
      'source_table'=>'live_ad_launch_checklists','source_id'=>(string)($a['id']??''),'creative_type'=>'ad','campaign_name'=>$name,'town'=>$town,
      'audience'=>'Potential sellers / home value leads','objective'=>'seller_lead_generation',
      'headline'=>$name,
      'primary_text'=>'Many local homeowners are sitting on more equity than they realize. Before the market shifts, get a private read on your home’s current position.',
      'description'=>'Private home value review for '.$town.' homeowners.',
      'cta'=>'Get My Home Value','landing_page'=>$a['final_url']??'/home-valuation.html',
      'image_prompt'=>town_image_prompt133($town?:'Fairfield County','potential sellers'),
      'video_prompt'=>video_prompt133($town?:'Fairfield County','homeowners may be sitting on record equity'),
      'design_notes'=>'Use refined black/gold Mark Pires style. Avoid hype. Make it feel local, premium, and trustworthy.',
      'priority_score'=>90,'raw_payload'=>$a
    ]);
  }

  // 2. First campaign plan -> ad concepts
  $plans=rows133('first_campaign_plan','select=*&order=priority_score.desc,created_at.desc&limit=100');
  foreach($plans as $p){
    $key='plan:'.($p['id']??md5(json_encode($p)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $town=$p['town']??'';
    $items[]=creative133([
      'source_table'=>'first_campaign_plan','source_id'=>(string)($p['id']??''),'creative_type'=>'ad','campaign_name'=>$p['campaign_name']??'Campaign Plan','town'=>$town,
      'market'=>$p['market']??'Lower Fairfield County','audience'=>$p['audience']??'Potential sellers','objective'=>$p['objective']??'lead_generation',
      'headline'=>$p['facebook_headline']??($p['ad_headline']??($p['campaign_name']??'')),
      'primary_text'=>$p['facebook_primary_text']??($p['ad_body']??''),
      'description'=>$p['retargeting_angle']??'',
      'cta'=>$p['cta']??'Get My Home Value',
      'landing_page'=>$p['landing_page']??'/home-valuation.html',
      'image_prompt'=>$p['creative_prompt']??town_image_prompt133($town?:'Fairfield County',$p['audience']??'sellers'),
      'video_prompt'=>video_prompt133($town?:'Fairfield County',$p['campaign_name']??'seller market timing'),
      'design_notes'=>'Review copy and creative direction before launch.',
      'priority_score'=>(int)($p['priority_score']??80),'raw_payload'=>$p
    ]);
  }

  // 3. SEO/AEO -> content creatives
  $seo=rows133('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=150');
  foreach($seo as $s){
    $key='seo:'.($s['id']??md5(json_encode($s)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $town=$s['town']??'';
    $items[]=creative133([
      'source_table'=>'seo_aeo_content_opportunities','source_id'=>(string)($s['id']??''),'creative_type'=>'content','campaign_name'=>$s['title']??'SEO Content','town'=>$town,
      'market'=>$s['market']??'Lower Fairfield County','audience'=>$s['audience']??'Sellers / buyers','objective'=>'organic_lead_generation',
      'headline'=>$s['h1']??($s['title']??''),'primary_text'=>$s['meta_description']??'','description'=>$s['search_intent']??'',
      'cta'=>$s['cta']??'Get My Home Value','landing_page'=>'/blog/'.($s['slug']??''),
      'image_prompt'=>town_image_prompt133($town?:'Fairfield County',$s['audience']??'local homeowners'),
      'video_prompt'=>video_prompt133($town?:'Fairfield County',$s['title']??'local seller signals'),
      'design_notes'=>'Create blog hero image, social post, and short-form video angle from this content.',
      'priority_score'=>(int)($s['priority_score']??75),'raw_payload'=>$s
    ]);
  }

  // 4. Source Hunter -> FSBO/expired style content/ad creative
  $targets=rows133('source_hunter_targets','select=*&order=intent_score.desc,created_at.desc&limit=100');
  foreach($targets as $t){
    $key='source:'.($t['id']??md5(json_encode($t)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $town=$t['town']??'';
    $type=$t['source_type']??'source';
    $headline='Thinking of Selling in '.($town?:'Fairfield County').'? Know Your Options First';
    if(str_contains($type,'fsbo')) $headline='Selling By Owner in '.($town?:'Fairfield County').'? Get a Second Set of Eyes';
    if(str_contains($type,'expired')) $headline='Did Your Listing Expire? The Market May Not Be The Problem';
    $items[]=creative133([
      'source_table'=>'source_hunter_targets','source_id'=>(string)($t['id']??''),'creative_type'=>'source_hunter','campaign_name'=>$headline,'town'=>$town,
      'audience'=>'FSBO / expired / owner-direct seller opportunities','objective'=>'seller_conversation',
      'headline'=>$headline,
      'primary_text'=>'Before you make your next move, Mark can help you understand pricing, timing, presentation, and the real buyer demand in your town.',
      'description'=>$t['opportunity_reason']??'High-intent seller source signal',
      'cta'=>'Ask Mark For A Private Review','landing_page'=>'/home-valuation.html',
      'image_prompt'=>town_image_prompt133($town?:'Fairfield County','owner-direct sellers'),
      'video_prompt'=>video_prompt133($town?:'Fairfield County',$headline),
      'design_notes'=>'Tone should be helpful and consultative, never predatory toward expired/FSBO owners.',
      'priority_score'=>(int)($t['intent_score']??75),'raw_payload'=>$t
    ]);
  }

  usort($items,function($a,$b){ return $b['priority_score']<=>$a['priority_score']; });

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($items,0,500),100) as $chunk){
    $r=sb133('POST','creative_review_items',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $counts=['ads'=>0,'content'=>0,'seo'=>0,'video'=>0,'approved'=>0,'review'=>0];
  foreach($items as $i){
    if(($i['creative_type']??'')==='ad')$counts['ads']++;
    if(in_array(($i['creative_type']??''),['content','source_hunter'],true))$counts['content']++;
    if(($i['creative_type']??'')==='seo')$counts['seo']++;
    if(!empty($i['video_prompt']))$counts['video']++;
    if(($i['status']??'')==='approved')$counts['approved']++;
    if(($i['status']??'')==='review')$counts['review']++;
  }

  $recs=[
    'Approve only the strongest 1–3 creatives per day. Do not launch everything.',
    'Prioritize seller/home-value creatives in towns where Source Hunter and Market Heat agree.',
    'Use image prompts as creative direction for Canva/Higgsfield/image generation, then upload final assets manually.'
  ];

  $brief="V13.3 CREATIVE REVIEW CENTER\\n";
  $brief.="========================================\\n\\n";
  $brief.="Total Creative Items: ".count($items)."\\n";
  $brief.="Ads:                  ".$counts['ads']."\\n";
  $brief.="Content:              ".$counts['content']."\\n";
  $brief.="Video Prompts:        ".$counts['video']."\\n";
  $brief.="Review Needed:        ".$counts['review']."\\n\\n";
  $brief.="TOP CREATIVE ITEMS\\n----------------------------------------\\n";
  foreach(array_slice($items,0,15) as $n=>$it){
    $brief.=($n+1).". ".$it['headline']."\\n";
    $brief.="     Town:     ".$it['town']."\\n";
    $brief.="     Type:     ".$it['creative_type']."\\n";
    $brief.="     Score:    ".$it['priority_score']."\\n";
    $brief.="     CTA:      ".$it['cta']."\\n\\n";
  }
  $brief.="JESSICA RECOMMENDS\\n----------------------------------------\\n";
  foreach($recs as $n=>$r){ $brief.=($n+1).". {$r}\\n\\n"; }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_items'=>count($items),'ads'=>$counts['ads'],'content'=>$counts['content'],'seo'=>$counts['seo'],'video'=>$counts['video'],
    'approved'=>$counts['approved'],'review_needed'=>$counts['review'],'top_creatives'=>array_slice($items,0,25),'recommendations'=>$recs,
    'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];

  $dr=sb133('POST','creative_review_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb133('PATCH','creative_review_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'creative_items_created'=>count($items),
    'ads'=>$counts['ads'],
    'content'=>$counts['content'],
    'video_prompts'=>$counts['video'],
    'review_needed'=>$counts['review'],
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>