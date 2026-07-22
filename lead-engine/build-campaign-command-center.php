<?php
/**
 * V15.5 Campaign Command Center
 * Upload: /public_html/lead-engine/build-campaign-command-center.php
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

  function sb155($method,$endpoint,$payload=null){
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
  function rows155($t,$q){$r=sb155('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function stage155($c){
    $launch=(int)($c['launch_score']??0);
    $traffic=(int)($c['traffic_score']??50);
    $creative=(int)($c['creative_score']??0);
    $compliance=(int)($c['compliance_score']??80);
    $approved=!empty($c['approved_for_launch']) || (($c['status']??'')==='approved');
    $score=round($launch*.45+$traffic*.20+$creative*.20+$compliance*.15);
    $imageNeeded=true;
    $copyReady=!empty($c['headline']) && !empty($c['primary_text']) && !empty($c['cta']);
    $landingReady=!empty($c['landing_page_url']);
    $distributionReady=$approved && $copyReady && $landingReady && $score>=78;
    $blotatoReady=$distributionReady && in_array(($c['campaign_type']??''),['discover_ct','house_detective','seller_lead','valuation'],true);

    if($approved && $score>=84 && $copyReady && $landingReady) $stage='launch_today';
    elseif($score>=78 && $copyReady) $stage='generate_creative';
    elseif($score>=70) $stage='improve_first';
    elseif(($c['status']??'')==='launched') $stage='watch';
    else $stage='review';

    if(($c['launch_recommendation']??'')==='hold') $stage='improve_first';

    $channels=['Facebook','Instagram'];
    if(($c['campaign_type']??'')==='valuation') $channels=['Facebook','Instagram','Google Search','Retargeting'];
    if(($c['brand_pillar']??'')==='discover_ct') $channels=['Instagram','TikTok','Facebook','YouTube Shorts'];
    if(($c['brand_pillar']??'')==='house_detective') $channels=['Instagram','TikTok','YouTube Shorts','Facebook'];
    if(($c['launch_recommendation']??'')==='launch') $channels[]='Email';

    return [$stage,$score,$imageNeeded,$copyReady,$landingReady,$distributionReady,$blotatoReady,array_values(array_unique($channels))];
  }

  $campaigns=rows155('ad_launch_campaigns','select=*&status=in.(draft,approved,launched)&order=launch_score.desc,created_at.desc&limit=500');
  $traffic=rows155('traffic_performance','select=*&order=traffic_date.desc,traffic_score.desc&limit=20');
  $trafficTop=$traffic[0]['source_name']??'No traffic source yet';
  $trafficScore=(int)($traffic[0]['traffic_score']??50);

  // Clear today's active commands to avoid duplicates
  sb155('PATCH','campaign_command_center?command_date=eq.'.rawurlencode(date('Y-m-d')).'&status=eq.active',['status'=>'archived','updated_at'=>date('c')]);

  $commands=[];
  foreach($campaigns as $c){
    [$stage,$score,$imageNeeded,$copyReady,$landingReady,$distributionReady,$blotatoReady,$channels]=stage155($c);
    $budget=(float)($c['recommended_budget']??25);
    if($stage==='launch_today') $budget=max($budget,25);
    if($score>=90) $budget+=25;
    if($trafficScore>=80) $budget+=15;

    $daily='Review and improve before launch.';
    if($stage==='launch_today') $daily='Launch or test today after final Mark approval.';
    if($stage==='generate_creative') $daily='Send this campaign to V16 Creative Generation Studio for image/ad creative.';
    if($stage==='distribute') $daily='Queue for Blotato/social distribution.';
    if($stage==='watch') $daily='Watch traffic and conversion signals before increasing budget.';
    if($stage==='improve_first') $daily='Improve hook, CTA, landing fit, or compliance language before launch.';

    $creativeRequest='Generate a premium ad creative for: '.($c['headline']??$c['campaign_name']).'. '.$c['image_direction'].' Prompt: '.($c['creative_prompt']??'');
    $distribution='Post primary ad to '.implode(', ',$channels).'. Repurpose into 1 short caption, 1 story, 1 feed post, and 1 retargeting variation.';

    $commands[]=[
      'command_date'=>date('Y-m-d'),
      'campaign_id'=>$c['id']??null,
      'campaign_name'=>$c['campaign_name']??'',
      'campaign_type'=>$c['campaign_type']??'',
      'brand_pillar'=>$c['brand_pillar']??'',
      'target_town'=>$c['target_town']??'',
      'target_market'=>$c['target_market']??'Fairfield County',
      'command_stage'=>$stage,
      'command_score'=>$score,
      'launch_score'=>(int)($c['launch_score']??0),
      'traffic_score'=>$trafficScore,
      'creative_score'=>(int)($c['creative_score']??0),
      'content_mine_score'=>0,
      'seller_signal_score'=>(int)($c['seller_score']??0),
      'image_needed'=>$imageNeeded,
      'copy_ready'=>$copyReady,
      'landing_ready'=>$landingReady,
      'distribution_ready'=>$distributionReady,
      'blotato_ready'=>$blotatoReady,
      'recommended_budget'=>$budget,
      'recommended_channels'=>$channels,
      'recommended_daily_action'=>$daily,
      'recommended_creative_request'=>$creativeRequest,
      'recommended_distribution_plan'=>$distribution,
      'status'=>'active',
      'raw_payload'=>['campaign'=>$c,'top_traffic_source'=>$trafficTop],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
  }

  usort($commands,function($a,$b){return $b['command_score']<=>$a['command_score'];});
  $inserted=[];$errors=[];
  foreach(array_chunk($commands,50) as $chunk){
    $r=sb155('POST','campaign_command_center',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows155('campaign_command_center','select=*&command_date=eq.'.rawurlencode(date('Y-m-d')).'&status=eq.active&order=command_score.desc&limit=500');
  $counts=['launch_today'=>0,'generate_creative'=>0,'distribute'=>0,'improve_first'=>0,'watch'=>0,'budget'=>0];
  foreach($all as $x){
    $st=$x['command_stage']??'review';
    if(isset($counts[$st]))$counts[$st]++;
    if(in_array($st,['launch_today','generate_creative'],true))$counts['budget']+=(float)($x['recommended_budget']??0);
  }

  $recs=[
    'Do not spend until campaign is approved and command_stage is launch_today.',
    'Use V16 Creative Generation Studio for generate_creative items.',
    'Use Blotato later only for blotato_ready items.',
    'Scale budget only after V15.2 shows calls, appointments, or ready-to-contact signal.'
  ];

  $brief="V15.5 CAMPAIGN COMMAND CENTER\\n========================================\\n\\n";
  $brief.="Total Campaign Commands:  ".count($all)."\\n";
  $brief.="Launch Today:             ".$counts['launch_today']."\\n";
  $brief.="Generate Creative:        ".$counts['generate_creative']."\\n";
  $brief.="Improve First:            ".$counts['improve_first']."\\n";
  $brief.="Watch:                    ".$counts['watch']."\\n";
  $brief.="Suggested Test Budget:    $".number_format($counts['budget'],0)."/day\\n";
  $brief.="Top Traffic Source:       ".$trafficTop."\\n\\n";
  $brief.="TODAY'S CAMPAIGN COMMANDS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,20) as $i=>$c){
    $brief.=($i+1).". ".$c['campaign_name']." — ".$c['command_stage']." — Score ".$c['command_score']." — $".number_format((float)$c['recommended_budget'],0)."/day\\n";
    $brief.="   Action: ".$c['recommended_daily_action']."\\n";
    $brief.="   Channels: ".implode(', ', is_array($c['recommended_channels'])?$c['recommended_channels']:[])."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),
    'total_commands'=>count($all),
    'launch_today'=>$counts['launch_today'],
    'generate_creative'=>$counts['generate_creative'],
    'distribute'=>$counts['distribute'],
    'improve_first'=>$counts['improve_first'],
    'watch_count'=>$counts['watch'],
    'recommended_budget'=>$counts['budget'],
    'top_campaign'=>$all[0]['campaign_name']??'',
    'top_commands'=>array_slice($all,0,30),
    'briefing_text'=>$brief,
    'recommendations'=>$recs,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $dr=sb155('POST','campaign_command_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb155('PATCH','campaign_command_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'total_commands'=>count($all),
    'launch_today'=>$counts['launch_today'],
    'generate_creative'=>$counts['generate_creative'],
    'recommended_budget'=>$counts['budget'],
    'briefing'=>$brief,
    'inserted'=>$inserted,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>