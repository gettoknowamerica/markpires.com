<?php
/**
 * V11.7 Builder Forecasting Engine
 * Upload: /public_html/lead-engine/build-builder-forecasts.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb117($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=merge-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function band117($score){
  if($score>=85)return 'urgent';
  if($score>=70)return 'high';
  if($score>=45)return 'moderate';
  return 'low';
}
function stage_score117($stage){
  $map=['new'=>5,'intro_sent'=>15,'interested'=>35,'reviewing'=>45,'site_visit'=>60,'offer_possible'=>75,'offer_made'=>85,'under_contract'=>95,'closed'=>100,'dead'=>0];
  return $map[$stage]??20;
}
function outcome117($score,$stage){
  if($stage==='under_contract')return 'Closing likely';
  if($stage==='offer_made')return 'Offer follow-up required';
  if($stage==='offer_possible')return 'Offer possible';
  if($stage==='site_visit')return 'Site visit feedback likely';
  if($score>=70)return 'Builder response likely';
  if($score>=45)return 'Needs nurture';
  return 'Low response probability';
}
function risk117($p){
  $stage=$p['pipeline_stage']??'new';
  $last=!empty($p['last_contact_at'])?strtotime($p['last_contact_at']):0;
  $follow=!empty($p['next_followup_at'])?strtotime($p['next_followup_at']):0;
  if($stage==='dead')return ['high','Pipeline marked dead'];
  if($follow && $follow<strtotime('-7 days'))return ['high','Follow-up overdue more than 7 days'];
  if($follow && $follow<time())return ['elevated','Follow-up is overdue'];
  if(in_array($stage,['offer_possible','offer_made']) && $last && $last<strtotime('-10 days'))return ['elevated','High-value stage has gone quiet'];
  return ['normal','No major risk detected'];
}

$profiles=sb117('GET','builder_performance_profiles?select=*&limit=1000')['data'];
$profileMap=[];
foreach($profiles as $p){if(!empty($p['builder_contact_id']))$profileMap[$p['builder_contact_id']]=$p;}

$pipeline=sb117('GET','builder_pipeline?select=*&pipeline_stage=not.in.(closed,dead)&order=deal_probability.desc&limit=1000')['data'];
$matches=sb117('GET','builder_opportunity_matches?select=*&status=in.(approved,introduced,contacted)&order=match_score.desc&limit=1000')['data'];

$today=date('Y-m-d');
$items=[];$summary=['total'=>0,'high'=>0,'urgent'=>0,'risk'=>0,'expected'=>0];

foreach($pipeline as $p){
  if(!is_array($p)||empty($p['id']))continue;
  $profile=$profileMap[$p['builder_contact_id']??'']??[];
  $stage=$p['pipeline_stage']??'new';
  $base=stage_score117($stage);
  $prob=(int)($p['deal_probability']??10);
  $response=(float)($profile['response_rate']??0);
  $conversion=(float)($profile['conversion_rate']??0);
  $close=(float)($profile['close_rate']??0);
  $tier=$profile['tier']??'Tier 3';

  $score=$base + min(20,(int)round($prob*.2)) + (int)round($response*15) + (int)round($conversion*10) + (int)round($close*10);
  if($tier==='Tier 1')$score+=12; elseif($tier==='Tier 2')$score+=6;
  $score=min(100,max(0,$score));

  [$risk,$riskReason]=risk117($p);
  $ref=(float)($p['referral_potential']??0);
  $expected=round($ref*($score/100),2);
  $band=band117($score);

  if($band==='high')$summary['high']++;
  if($band==='urgent')$summary['urgent']++;
  if(in_array($risk,['elevated','high'],true))$summary['risk']++;
  $summary['expected']+=$expected;

  $items[]=[
    'forecast_date'=>$today,
    'builder_contact_id'=>$p['builder_contact_id']??null,
    'builder_name'=>$p['builder_name']??'',
    'company'=>$p['company']??'',
    'opportunity_id'=>$p['opportunity_id']??null,
    'pipeline_id'=>$p['id'],
    'match_id'=>$p['match_id']??null,
    'opportunity_address'=>$p['opportunity_address']??'',
    'opportunity_town'=>$p['opportunity_town']??'',
    'opportunity_type'=>$p['opportunity_type']??'',
    'pipeline_stage'=>$stage,
    'deal_probability'=>$prob,
    'builder_tier'=>$tier,
    'builder_response_rate'=>$response,
    'builder_conversion_rate'=>$conversion,
    'builder_close_rate'=>$close,
    'forecast_score'=>$score,
    'forecast_band'=>$band,
    'expected_outcome'=>outcome117($score,$stage),
    'risk_level'=>$risk,
    'risk_reason'=>$riskReason,
    'expected_referral_value'=>$expected,
    'recommended_action'=>$risk==='high'?'Immediate Mark review.':($band==='urgent'?'Prioritize this builder opportunity today.':'Keep in normal follow-up flow.'),
    'status'=>'active',
    'raw_payload'=>['pipeline'=>$p,'profile'=>$profile],
    'updated_at'=>date('c')
  ];
}

/* Forecast approved matches not yet in pipeline */
foreach($matches as $m){
  if(!is_array($m)||empty($m['id']))continue;
  $profile=$profileMap[$m['builder_contact_id']??'']??[];
  $matchScore=(int)($m['match_score']??0);
  $response=(float)($profile['response_rate']??0);
  $tier=$profile['tier']??'Tier 3';
  $score=min(100,(int)round(($matchScore*.55)+($response*25)+($tier==='Tier 1'?15:($tier==='Tier 2'?8:0))));
  $band=band117($score);
  $expected=0;
  if($band==='high')$summary['high']++;
  if($band==='urgent')$summary['urgent']++;

  $items[]=[
    'forecast_date'=>$today,
    'builder_contact_id'=>$m['builder_contact_id']??null,
    'builder_name'=>$m['builder_name']??'',
    'company'=>$m['company']??'',
    'opportunity_id'=>$m['opportunity_id']??null,
    'pipeline_id'=>null,
    'match_id'=>$m['id'],
    'opportunity_address'=>$m['opportunity_address']??'',
    'opportunity_town'=>$m['opportunity_town']??'',
    'opportunity_type'=>$m['opportunity_type']??'',
    'pipeline_stage'=>'match_only',
    'deal_probability'=>0,
    'builder_tier'=>$tier,
    'builder_response_rate'=>$response,
    'builder_conversion_rate'=>(float)($profile['conversion_rate']??0),
    'builder_close_rate'=>(float)($profile['close_rate']??0),
    'forecast_score'=>$score,
    'forecast_band'=>$band,
    'expected_outcome'=>outcome117($score,'match_only'),
    'risk_level'=>'normal',
    'risk_reason'=>'Match has not entered pipeline yet.',
    'expected_referral_value'=>$expected,
    'recommended_action'=>$score>=70?'Consider intro or direct follow-up.':'Watchlist.',
    'status'=>'active',
    'raw_payload'=>['match'=>$m,'profile'=>$profile],
    'updated_at'=>date('c')
  ];
}

$inserted=[];$errors=[];
foreach(array_chunk($items,100) as $chunk){
  $r=sb117('POST','builder_forecasts',$chunk);
  if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
  else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
}
$summary['total']=count($items);

sb117('POST','builder_forecast_runs',[[
  'run_date'=>$today,
  'total_forecasts'=>$summary['total'],
  'high_forecasts'=>$summary['high'],
  'urgent_forecasts'=>$summary['urgent'],
  'high_risk'=>$summary['risk'],
  'expected_referral_value'=>$summary['expected'],
  'summary'=>$summary,
  'created_at'=>date('c')
]]);

echo json_encode(['success'=>empty($errors),'summary'=>$summary,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);
?>