<?php
/**
 * V12.14 Market Heat Map Builder
 * Upload: /public_html/lead-engine/build-market-heat-map.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb1214($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function rows1214($table,$query='select=*&limit=1000'){
  $r=sb1214('GET',$table.'?'.$query);
  return $r['ok']?$r['data']:[];
}
function ensure_town1214($v){
  $v=trim((string)$v);
  return $v ?: 'Fairfield County';
}
function heat_band1214($score){
  if($score>=90)return 'fire';
  if($score>=70)return 'hot';
  if($score>=40)return 'warm';
  return 'cold';
}
function action1214($town,$band,$seller,$buyer,$relocation,$builder,$campaign,$seo){
  if($band==='fire'){
    if($seller >= max($buyer,$relocation,$builder)) return "Launch or increase seller/home value campaign in {$town}. Create seller content and review imports.";
    if($relocation >= max($seller,$buyer,$builder)) return "Push relocation campaign and town guide for {$town}. Target NYC/Westchester movers.";
    if($builder >= max($seller,$buyer,$relocation)) return "Review builder/developer opportunities in {$town}. Prepare watchlist outreach.";
    return "Prioritize {$town} in ads, content, and Jessica follow-up.";
  }
  if($band==='hot') return "Prepare a focused campaign/content push for {$town}.";
  if($band==='warm') return "Watch {$town}; add content and monitor signals.";
  return "Low current heat. Keep in long-term organic/nurture lane.";
}
function budget1214($band){
  if($band==='fire')return 35;
  if($band==='hot')return 25;
  if($band==='warm')return 10;
  return 0;
}

$sets=[
  'discovery'=>rows1214('discovery_opportunity_queue','select=*&order=priority_score.desc,created_at.desc&limit=5000'),
  'hunter'=>rows1214('hunter_priority_rankings','select=*&status=eq.active&order=hunter_score.desc,created_at.desc&limit=5000'),
  'campaigns'=>rows1214('first_campaign_plan','select=*&order=priority_score.desc,created_at.desc&limit=2000'),
  'seo'=>rows1214('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=5000'),
  'imports'=>rows1214('compliant_lead_imports','select=*&order=lead_score.desc,created_at.desc&limit=5000'),
  'builders'=>rows1214('builder_forecasts','select=*&order=forecast_score.desc&limit=2000')
];

$towns=[];

function init_town1214(&$towns,$town,$market=''){
  if(!isset($towns[$town])){
    $towns[$town]=[
      'town'=>$town,'market'=>$market,'seller_heat'=>0,'buyer_heat'=>0,'relocation_heat'=>0,'builder_heat'=>0,'campaign_heat'=>0,'seo_heat'=>0,'signals'=>[]
    ];
  }
  if($market && empty($towns[$town]['market']))$towns[$town]['market']=$market;
}

foreach($sets['discovery'] as $r){
  $town=ensure_town1214($r['town']??'');
  init_town1214($towns,$town,$r['market']??'');
  $type=strtolower((string)($r['opportunity_type']??'').' '.($r['audience']??''));
  $score=(int)($r['priority_score']??50);
  if(str_contains($type,'seller'))$towns[$town]['seller_heat']+=round($score/10);
  elseif(str_contains($type,'relocation'))$towns[$town]['relocation_heat']+=round($score/10);
  elseif(str_contains($type,'buyer'))$towns[$town]['buyer_heat']+=round($score/10);
  elseif(str_contains($type,'builder')||str_contains($type,'developer'))$towns[$town]['builder_heat']+=round($score/10);
  $towns[$town]['signals'][]=['source'=>'discovery','label'=>$r['offer']??($r['opportunity_type']??''),'score'=>$score];
}

foreach($sets['hunter'] as $r){
  $town=ensure_town1214($r['town']??'');
  init_town1214($towns,$town,$r['market']??'');
  $type=$r['hunter_type']??'';
  $score=(int)($r['hunter_score']??50);
  if($type==='seller')$towns[$town]['seller_heat']+=round($score/8);
  elseif(in_array($type,['buyer','relocation'],true))$towns[$town]['relocation_heat']+=round($score/9);
  elseif(in_array($type,['builder','developer'],true))$towns[$town]['builder_heat']+=round($score/8);
  $towns[$town]['signals'][]=['source'=>'hunter','label'=>$r['name']?:$type,'score'=>$score];
}

foreach($sets['campaigns'] as $r){
  $town=ensure_town1214($r['town']??'');
  init_town1214($towns,$town,$r['market']??'');
  $score=(int)($r['priority_score']??50);
  $towns[$town]['campaign_heat']+=round($score/8);
  $name=strtolower((string)($r['campaign_name']??'').' '.($r['audience']??''));
  if(str_contains($name,'home value')||str_contains($name,'seller'))$towns[$town]['seller_heat']+=5;
  if(str_contains($name,'relocation')||str_contains($name,'nyc'))$towns[$town]['relocation_heat']+=5;
  $towns[$town]['signals'][]=['source'=>'campaign','label'=>$r['campaign_name']??'campaign','score'=>$score];
}

foreach($sets['seo'] as $r){
  $town=ensure_town1214($r['town']??'');
  init_town1214($towns,$town,$r['market']??'');
  $score=(int)($r['priority_score']??50);
  $towns[$town]['seo_heat']+=round($score/9);
  $type=$r['content_type']??'';
  if($type==='seller')$towns[$town]['seller_heat']+=3;
  elseif($type==='relocation')$towns[$town]['relocation_heat']+=3;
  elseif($type==='builder')$towns[$town]['builder_heat']+=3;
  $towns[$town]['signals'][]=['source'=>'seo','label'=>$r['title']??'content','score'=>$score];
}

foreach($sets['imports'] as $r){
  $town=ensure_town1214($r['town']??'');
  init_town1214($towns,$town,$r['market']??'');
  $score=(int)($r['lead_score']??50);
  $type=strtolower((string)($r['lead_type']??''));
  if(str_contains($type,'seller'))$towns[$town]['seller_heat']+=round($score/12);
  elseif(str_contains($type,'buyer')||str_contains($type,'relocation'))$towns[$town]['relocation_heat']+=round($score/12);
  elseif(str_contains($type,'builder')||str_contains($type,'developer'))$towns[$town]['builder_heat']+=round($score/12);
  if(!empty($r['call_eligible']))$towns[$town]['seller_heat']+=5;
}

foreach($sets['builders'] as $r){
  $town=ensure_town1214($r['opportunity_town']??($r['town']??''));
  init_town1214($towns,$town,'Builder Pipeline');
  $score=(int)($r['forecast_score']??50);
  $towns[$town]['builder_heat']+=round($score/6);
  $towns[$town]['signals'][]=['source'=>'builder_forecast','label'=>$r['opportunity_address']??'builder opportunity','score'=>$score];
}

$items=[];
foreach($towns as $town=>$t){
  $seller=(int)$t['seller_heat']; $buyer=(int)$t['buyer_heat']; $rel=(int)$t['relocation_heat']; $builder=(int)$t['builder_heat']; $campaign=(int)$t['campaign_heat']; $seo=(int)$t['seo_heat'];
  $total=min(100, round(($seller*1.25)+($buyer*.8)+($rel*1.15)+($builder*.9)+($campaign*.7)+($seo*.6)));
  $band=heat_band1214($total);
  usort($t['signals'],function($a,$b){return ($b['score']??0)<=>($a['score']??0);});
  $items[]=[
    'snapshot_date'=>date('Y-m-d'),
    'town'=>$town,
    'market'=>$t['market'] ?: 'Fairfield County',
    'seller_heat'=>$seller,
    'buyer_heat'=>$buyer,
    'relocation_heat'=>$rel,
    'builder_heat'=>$builder,
    'campaign_heat'=>$campaign,
    'seo_heat'=>$seo,
    'total_heat'=>$total,
    'heat_band'=>$band,
    'recommended_action'=>action1214($town,$band,$seller,$buyer,$rel,$builder,$campaign,$seo),
    'recommended_budget'=>budget1214($band),
    'top_signals'=>array_slice($t['signals'],0,8),
    'raw_payload'=>$t,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

usort($items,function($a,$b){return $b['total_heat']<=>$a['total_heat'];});

$inserted=[];$errors=[];
foreach(array_chunk($items,100) as $chunk){
  $r=sb1214('POST','market_heat_snapshots',$chunk);
  if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
  else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
}

$fire=0;$hot=0;$warm=0;
foreach($items as $i){ if($i['heat_band']==='fire')$fire++; elseif($i['heat_band']==='hot')$hot++; elseif($i['heat_band']==='warm')$warm++; }

$brief="Market Heat Map — ".date('Y-m-d')."\\n\\n";
$brief.="Top town: ".($items[0]['town']??'n/a')."\\n";
$brief.="Fire towns: {$fire}\\nHot towns: {$hot}\\nWarm towns: {$warm}\\n\\n";
$brief.="Top markets:\\n";
foreach(array_slice($items,0,10) as $n=>$i){
  $brief.=($n+1).". ".$i['town']." — ".$i['heat_band']." — Heat ".$i['total_heat']." — ".$i['recommended_action']."\\n";
}

$daily=[[
  'briefing_date'=>date('Y-m-d'),
  'top_town'=>$items[0]['town']??'',
  'fire_towns'=>$fire,
  'hot_towns'=>$hot,
  'warm_towns'=>$warm,
  'total_markets'=>count($items),
  'briefing_text'=>$brief,
  'top_markets'=>array_slice($items,0,15),
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$dr=sb1214('POST','market_heat_daily_briefings',$daily);
if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
  sb1214('PATCH','market_heat_daily_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
}

echo json_encode([
  'success'=>empty($errors),
  'markets_scored'=>count($items),
  'fire_towns'=>$fire,
  'hot_towns'=>$hot,
  'warm_towns'=>$warm,
  'top_town'=>$items[0]['town']??null,
  'inserted'=>$inserted,
  'briefing'=>$brief,
  'errors'=>$errors
], JSON_PRETTY_PRINT);
?>