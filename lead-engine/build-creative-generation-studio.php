<?php
/**
 * V16 Creative Generation Studio Builder
 * Upload: /public_html/lead-engine/build-creative-generation-studio.php
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

  function sb16($method,$endpoint,$payload=null){
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
  function rows16($t,$q){$r=sb16('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function scoreWords16($txt,$words,$pts){$s=0;$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)$s+=$pts;}return min(100,$s);}
  function enhance16($job,$preset=null){
    $base=trim(($job['prompt']??'').' '.($job['headline']??'').' '.($job['subheadline']??'').' '.($job['town']??''));
    $style=$preset['style_prompt']??'premium real estate marketing graphic, clean layout, high-trust, conversion focused';
    $brand=$job['brand_pillar']??'mark_pires';
    $platform=$job['platform']??'social';
    $headline=$job['headline']??'';
    $cta=$job['cta']??'';
    $town=$job['town']??'Fairfield County';

    $enhanced="Create a {$platform} creative for {$brand}. Style: {$style}. Concept: {$base}. Location focus: {$town}.";
    if($headline)$enhanced.=" Main headline idea: {$headline}.";
    if($cta)$enhanced.=" CTA: {$cta}.";
    $enhanced.=" Leave clean negative space for final typography. Make it premium, local, emotionally clear, and highly scroll-stopping without looking gimmicky.";
    return $enhanced;
  }

  $presets=rows16('creative_generation_presets','select=*&is_active=eq.true&limit=100');
  $presetMap=[];
  foreach($presets as $p){$presetMap[$p['preset_key']]=$p;}

  // Import generate_creative commands from V15.5
  $commands=rows16('campaign_command_center','select=*&status=eq.active&command_stage=eq.generate_creative&order=command_score.desc&limit=200');
  $existing=rows16('creative_generation_jobs','select=source_table,source_id,job_name&limit=5000');
  $seen=[]; foreach($existing as $e){$seen[strtolower(($e['source_table']??'').':'.($e['source_id']??'').':'.($e['job_name']??''))]=true;}

  $new=[];
  foreach($commands as $c){
    $sid=(string)($c['id']??'');
    $name='Creative: '.($c['campaign_name']??'Campaign');
    $k=strtolower('campaign_command_center:'.$sid.':'.$name);
    if(isset($seen[$k]))continue;

    $brand=$c['brand_pillar']??'mark_pires';
    $presetKey='seller_value_ad';
    if($brand==='house_detective')$presetKey='house_detective_poster';
    elseif($brand==='discover_ct')$presetKey='discover_ct_social';
    elseif($brand==='beatseat')$presetKey='beatseat_authority';

    $preset=$presetMap[$presetKey]??null;
    $prompt=$c['recommended_creative_request']??($c['campaign_name']??'Create campaign creative');
    $job=[
      'job_date'=>date('Y-m-d'),
      'job_name'=>$name,
      'job_type'=>'ad_graphic',
      'brand_pillar'=>$brand,
      'platform'=>'facebook_instagram',
      'source_table'=>'campaign_command_center',
      'source_id'=>$sid,
      'prompt'=>$prompt,
      'negative_prompt'=>$preset['negative_prompt']??'no clutter, no distorted faces, no unreadable text',
      'style_preset'=>$presetKey,
      'aspect_ratio'=>$preset['default_aspect_ratio']??'1:1',
      'headline'=>$c['campaign_name']??'',
      'cta'=>'Learn More',
      'town'=>$c['target_town']??'Fairfield County',
      'audience'=>'homeowners',
      'status'=>'queued',
      'priority_score'=>(int)($c['command_score']??70),
      'recommended_use'=>'ad creative',
      'notes'=>'Auto-created from V15.5 Campaign Command Center.',
      'raw_payload'=>$c,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    $job['enhanced_prompt']=enhance16($job,$preset);
    $job['generation_score']=min(100,(int)$job['priority_score']+10);
    $new[]=$job;
  }

  // Import ad launch image prompt assets
  $adAssets=rows16('ad_launch_assets','select=*&asset_type=eq.image_prompt&status=eq.draft&order=created_at.desc&limit=200');
  foreach($adAssets as $a){
    $sid=(string)($a['id']??'');
    $name='Image Prompt: '.($a['headline']??'Ad Creative');
    $k=strtolower('ad_launch_assets:'.$sid.':'.$name);
    if(isset($seen[$k]))continue;
    $prompt=$a['creative_prompt'] ?: ($a['body']??'Create ad image');
    $job=[
      'job_date'=>date('Y-m-d'),'job_name'=>$name,'job_type'=>'ad_graphic','brand_pillar'=>'seller_authority','platform'=>$a['platform']??'facebook_instagram',
      'source_table'=>'ad_launch_assets','source_id'=>$sid,'prompt'=>$prompt,'negative_prompt'=>'no clutter, no false claims, no distorted text',
      'style_preset'=>'seller_value_ad','aspect_ratio'=>'1:1','headline'=>$a['headline']??'','cta'=>$a['cta']??'Get My Home Value',
      'status'=>'queued','priority_score'=>75,'recommended_use'=>'ad creative','notes'=>'Auto-created from Ad Launch asset.',
      'raw_payload'=>$a,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    $job['enhanced_prompt']=enhance16($job,$presetMap['seller_value_ad']??null);
    $job['generation_score']=85;
    $new[]=$job;
  }

  $inserted=[];$errors=[];
  foreach(array_chunk($new,50) as $chunk){
    $r=sb16('POST','creative_generation_jobs',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  // Rescore existing jobs
  $jobs=rows16('creative_generation_jobs','select=*&status=in.(draft,queued,generated,approved)&order=priority_score.desc,created_at.desc&limit=1000');
  foreach($jobs as $j){
    $blob=implode(' ',[$j['job_name']??'',$j['prompt']??'',$j['headline']??'',$j['brand_pillar']??'',$j['town']??'']);
    $score=40+scoreWords16($blob,['seller','home value','valuation','house detective','discover ct','greenwich','westport','darien','new canaan','luxury','listing','blog','thumbnail'],5);
    $score=min(100,max((int)($j['priority_score']??50),$score));
    $preset=$presetMap[$j['style_preset']??'']??null;
    $enhanced=$j['enhanced_prompt'] ?: enhance16($j,$preset);
    sb16('PATCH','creative_generation_jobs?id=eq.'.rawurlencode($j['id']),[
      'generation_score'=>$score,
      'enhanced_prompt'=>$enhanced,
      'updated_at'=>date('c')
    ]);
  }

  $all=rows16('creative_generation_jobs','select=*&status=in.(draft,queued,generated,approved)&order=generation_score.desc,created_at.desc&limit=1000');
  $counts=['queued'=>0,'approved'=>0,'detective'=>0,'discover'=>0,'seller'=>0];
  foreach($all as $j){
    if(($j['status']??'')==='queued')$counts['queued']++;
    if(($j['status']??'')==='approved')$counts['approved']++;
    if(($j['brand_pillar']??'')==='house_detective')$counts['detective']++;
    if(($j['brand_pillar']??'')==='discover_ct')$counts['discover']++;
    if(($j['brand_pillar']??'')==='seller_authority')$counts['seller']++;
  }

  $recs=[
    'Generate queued jobs with the highest generation_score first.',
    'Upload headshots, logos, or listing photos on the dashboard before generating final creative.',
    'Approve only the strongest generated graphics before Blotato distribution.',
    'Use House Detective and Discover CT presets for differentiated brand content.'
  ];

  $brief="V16 CREATIVE GENERATION STUDIO\\n========================================\\n\\n";
  $brief.="Total Jobs:              ".count($all)."\\n";
  $brief.="Queued Jobs:             ".$counts['queued']."\\n";
  $brief.="Approved Jobs:           ".$counts['approved']."\\n";
  $brief.="House Detective Jobs:    ".$counts['detective']."\\n";
  $brief.="Discover CT Jobs:        ".$counts['discover']."\\n";
  $brief.="Seller Creative Jobs:    ".$counts['seller']."\\n";
  $brief.="New Jobs Created:        ".count($new)."\\n\\n";
  $brief.="TOP GENERATION JOBS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,20) as $i=>$j){
    $brief.=($i+1).". ".$j['job_name']." — ".$j['brand_pillar']." — ".$j['status']." — Score ".$j['generation_score']."\\n";
    $brief.="   Preset: ".$j['style_preset']." | Ratio: ".$j['aspect_ratio']."\\n";
    $brief.="   Prompt: ".substr($j['enhanced_prompt']??$j['prompt'],0,220)."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_jobs'=>count($all),'queued_jobs'=>$counts['queued'],'approved_jobs'=>$counts['approved'],
    'house_detective_jobs'=>$counts['detective'],'discover_ct_jobs'=>$counts['discover'],'seller_jobs'=>$counts['seller'],
    'top_jobs'=>array_slice($all,0,30),'briefing_text'=>$brief,'recommendations'=>$recs,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];

  $dr=sb16('POST','creative_generation_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb16('PATCH','creative_generation_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'new_jobs_created'=>count($new),
    'total_jobs'=>count($all),
    'queued_jobs'=>$counts['queued'],
    'briefing'=>$brief,
    'inserted'=>$inserted,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>