<?php
/**
 * V12.21 Revenue Forecasting
 * Upload: /public_html/lead-engine/build-revenue-forecast.php
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

  function sb211($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows211($table,$query){ $r=sb211('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function arr211($v){ if(is_string($v)){ $d=json_decode($v,true); return is_array($d)?$d:[]; } return is_array($v)?$v:[]; }

  $today=date('Y-m-d');

  $contacts=rows211('approved_contact_pool','select=*&status=eq.active&order=contact_score.desc,created_at.desc&limit=1000');
  $queue=rows211('daily_action_queue','select=*&status=eq.open&order=priority_score.desc,created_at.desc&limit=1000');
  $appts=rows211('appointment_intelligence_queue','select=*&appointment_status=eq.pending&order=appointment_priority.desc,created_at.desc&limit=500');
  $ads=rows211('live_ad_launch_checklists','select=*&launch_status=eq.ready&order=created_at.desc&limit=500');
  $cmd=rows211('daily_command_center_snapshots','select=*&order=created_at.desc&limit=1');
  $learn=rows211('conversation_learning_briefings_v2','select=*&order=created_at.desc&limit=1');

  $callEligible=0; $emailEligible=0; $leadValue=0; $towns=[]; $sources=[];
  foreach($contacts as $c){
    if(!empty($c['call_eligible'])) $callEligible++;
    if(!empty($c['email_eligible'])) $emailEligible++;
    $value=(float)($c['estimated_value']??0);
    if($value<=0){
      $town=$c['town']??'';
      if(in_array($town,['Greenwich','Darien','New Canaan','Westport'],true)) $value=1200000;
      elseif(in_array($town,['Stamford','Norwalk','Wilton','Fairfield'],true)) $value=750000;
      else $value=650000;
    }
    $score=(int)($c['contact_score']??50);
    $weight=max(.05,min(.30,$score/400));
    $leadValue += $value*$weight;
    $town=$c['town'] ?: 'Unknown';
    $towns[$town]=($towns[$town]??0)+1;
    $src=$c['source_type'] ?: 'unknown';
    $sources[$src]=($sources[$src]??0)+1;
  }

  $queueActions=count($queue);
  $adReady=count($ads);
  $appointmentsPending=count($appts);
  $appointmentsDetected=(int)($learn[0]['appointments_detected']??0);

  // Forecast math: conservative enough for planning, not accounting.
  $avgDeal=850000;
  $commissionRate=.025;
  $referralRate=.35;

  $appointmentPipeline=$appointmentsPending * $avgDeal * .55;
  $callPipeline=$callEligible * $avgDeal * .12;
  $emailPipeline=$emailEligible * $avgDeal * .03;
  $adPipeline=$adReady * $avgDeal * .08;
  $queuePipeline=$queueActions * $avgDeal * .01;

  $estimatedPipeline=$leadValue + $appointmentPipeline + $callPipeline + $emailPipeline + $adPipeline + $queuePipeline;
  $estimatedCommission=$estimatedPipeline*$commissionRate;
  $estimatedReferral=$estimatedCommission*$referralRate;

  $conservative=$estimatedCommission*.05;
  $expected=$estimatedCommission*.12;
  $aggressive=$estimatedCommission*.22;

  arsort($towns); arsort($sources);
  $topTowns=[]; foreach(array_slice($towns,0,10,true) as $k=>$v){ $topTowns[]=['town'=>$k,'count'=>$v]; }
  $topSources=[]; foreach(array_slice($sources,0,10,true) as $k=>$v){ $topSources[]=['source'=>$k,'count'=>$v]; }

  $recs=[];
  if($callEligible===0) $recs[]='Primary revenue bottleneck: no call-eligible contacts yet. Focus on approved/contact-permission sources and inbound ads.';
  if($adReady>0) $recs[]="Launch one small-budget ad test from {$adReady} ready campaigns and track lead cost.";
  if($appointmentsPending>0) $recs[]="Prioritize {$appointmentsPending} pending appointment/follow-up opportunities before new discovery.";
  if(!empty($topTowns[0])) $recs[]='Highest current revenue town signal: '.$topTowns[0]['town'].'.';
  if($queueActions>0) $recs[]="Complete the top 10 queue actions today; there are {$queueActions} open actions.";

  $brief="Revenue Forecast — {$today}\\n\\n";
  $brief.="Total active contacts: ".count($contacts)."\\n";
  $brief.="Call eligible contacts: {$callEligible}\\n";
  $brief.="Pending appointments: {$appointmentsPending}\\n";
  $brief.="Live ads ready: {$adReady}\\n";
  $brief.="Open queue actions: {$queueActions}\\n\\n";
  $brief.="Estimated pipeline value: $".number_format($estimatedPipeline,0)."\\n";
  $brief.="Estimated commission value: $".number_format($estimatedCommission,0)."\\n";
  $brief.="Potential referral value: $".number_format($estimatedReferral,0)."\\n";
  $brief.="Conservative close forecast: $".number_format($conservative,0)."\\n";
  $brief.="Expected close forecast: $".number_format($expected,0)."\\n";
  $brief.="Aggressive close forecast: $".number_format($aggressive,0)."\\n\\n";
  $brief.="Recommendations:\\n";
  foreach($recs as $i=>$r){ $brief.=($i+1).". {$r}\\n"; }

  $payload=[[
    'forecast_date'=>$today,
    'total_leads'=>count($contacts),
    'call_eligible_contacts'=>$callEligible,
    'appointments_pending'=>$appointmentsPending,
    'appointments_detected'=>$appointmentsDetected,
    'live_ads_ready'=>$adReady,
    'active_queue_actions'=>$queueActions,
    'estimated_pipeline_value'=>round($estimatedPipeline,2),
    'estimated_commission_value'=>round($estimatedCommission,2),
    'estimated_referral_value'=>round($estimatedReferral,2),
    'conservative_close_forecast'=>round($conservative,2),
    'expected_close_forecast'=>round($expected,2),
    'aggressive_close_forecast'=>round($aggressive,2),
    'top_towns'=>$topTowns,
    'top_revenue_sources'=>$topSources,
    'recommendations'=>$recs,
    'forecast_brief'=>$brief,
    'raw_payload'=>[
      'command'=>$cmd[0]??null,
      'conversation_learning'=>$learn[0]??null,
      'sample_contacts'=>array_slice($contacts,0,10),
      'sample_queue'=>array_slice($queue,0,10),
      'sample_appointments'=>array_slice($appts,0,10)
    ],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb211('POST','revenue_forecast_snapshots',$payload);

  echo json_encode([
    'success'=>$res['ok'],
    'forecast'=>$payload[0],
    'supabase_http'=>$res['http'],
    'body'=>$res['ok']?'':$res['body']
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>