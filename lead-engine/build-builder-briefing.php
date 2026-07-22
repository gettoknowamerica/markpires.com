<?php
/**
 * V11.5 Builder Executive Briefing
 * Upload: /public_html/lead-engine/build-builder-briefing.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb115($method,$endpoint,$payload=null){
  $url = rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[] = $method === 'POST'
    ? 'Prefer: resolution=merge-duplicates,return=representation'
    : 'Prefer: return=representation';

  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function money115($n){ return '$'.number_format((float)$n,0); }

function send_email115($subject,$html){
  if(!defined('RESEND_API_KEY') || !RESEND_API_KEY || !defined('ADMIN_EMAIL') || !ADMIN_EMAIL){
    return ['ok'=>false,'error'=>'Resend/Admin email not configured'];
  }
  $from = (defined('RESEND_FROM_EMAIL') && RESEND_FROM_EMAIL) ? RESEND_FROM_EMAIL : 'noreply@markpires.com';
  $payload = [
    'from'=>'Jessica <'.$from.'>',
    'to'=>[ADMIN_EMAIL],
    'subject'=>$subject,
    'html'=>$html
  ];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],
    CURLOPT_TIMEOUT=>20
  ]);
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

function norm115($p){
  $d=preg_replace('/\D+/','',(string)$p);
  if(strlen($d)==10) return '+1'.$d;
  if(strlen($d)==11 && substr($d,0,1)==='1') return '+'.$d;
  return $p;
}

function send_sms115($body){
  if(!defined('TWILIO_ACCOUNT_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_SMS_FROM') || !defined('ADMIN_PHONE') || !ADMIN_PHONE){
    return ['ok'=>false,'error'=>'Twilio/Admin phone not configured'];
  }
  $url='https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'/Messages.json';
  $post=http_build_query(['From'=>norm115(TWILIO_SMS_FROM),'To'=>norm115(ADMIN_PHONE),'Body'=>$body]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$post,
    CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN,
    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT=>15
  ]);
  $resp=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}

$today = date('Y-m-d');
$weekAhead = date('c', strtotime('+7 days'));

$opps = sb115('GET','builder_developer_opportunities?select=*&order=builder_score.desc&limit=1000')['data'];
$matches = sb115('GET','builder_opportunity_matches?select=*&order=match_score.desc&limit=1000')['data'];
$outreach = sb115('GET','builder_intro_outreach?select=*&order=created_at.desc&limit=1000')['data'];
$pipeline = sb115('GET','builder_pipeline?select=*&order=deal_probability.desc&limit=1000')['data'];

$totalOpp=count($opps);
$hotOpp=0; $towns=[]; $topOpp=[]; $alerts=[];

foreach($opps as $o){
  $score=(int)($o['builder_score']??0);
  if($score>=100 || ($o['priority']??'')==='hot') $hotOpp++;
  $town=$o['town'] ?: 'Unknown';
  if(!isset($towns[$town])) $towns[$town]=['town'=>$town,'opportunities'=>0,'hot'=>0,'pipeline_value'=>0,'referral_potential'=>0];
  $towns[$town]['opportunities']++;
  if($score>=100) $towns[$town]['hot']++;

  if(count($topOpp)<10){
    $topOpp[]=[
      'address'=>$o['address']??'',
      'town'=>$o['town']??'',
      'type'=>$o['opportunity_type']??'',
      'score'=>$score,
      'priority'=>$o['priority']??'',
      'reason'=>$o['reason']??'',
      'top_builder_match'=>$o['top_builder_match']??''
    ];
  }

  if($score>=110 || in_array(($o['opportunity_type']??''),['land','subdivision'],true)){
    $alerts[]=[
      'type'=>'opportunity',
      'priority'=>$score>=110?'urgent':'high',
      'title'=>trim(($o['opportunity_type']??'Opportunity').' in '.($o['town']??'')),
      'detail'=>($o['address']??'').' — Score '.$score.' — '.($o['reason']??'')
    ];
  }
}

$builderMatches=count($matches);
$drafts=0; $sent=0;
foreach($outreach as $r){
  if(($r['status']??'')==='draft') $drafts++;
  if(($r['status']??'')==='sent' || !empty($r['email_sent']) || !empty($r['sms_sent'])) $sent++;
}

$activePipeline=0; $followupsDue=0; $interested=0; $siteVisits=0; $offersPossible=0; $offersMade=0; $underContract=0; $closed=0;
$pipelineValue=0; $referralPotential=0; $expectedReferral=0; $topPipeline=[];

foreach($pipeline as $p){
  $stage=$p['pipeline_stage']??'new';
  if(!in_array($stage,['closed','dead'],true)) $activePipeline++;
  if(!empty($p['next_followup_at']) && strtotime($p['next_followup_at']) <= strtotime($weekAhead)) $followupsDue++;
  if(in_array($stage,['interested','reviewing'],true)) $interested++;
  if($stage==='site_visit') $siteVisits++;
  if($stage==='offer_possible') $offersPossible++;
  if($stage==='offer_made') $offersMade++;
  if($stage==='under_contract') $underContract++;
  if($stage==='closed') $closed++;

  $deal=(float)($p['estimated_deal_value']??0);
  $ref=(float)($p['referral_potential']??0);
  $prob=(int)($p['deal_probability']??0);
  $pipelineValue += $deal;
  $referralPotential += $ref;
  $expectedReferral += ($ref * ($prob/100));

  $town=$p['opportunity_town'] ?: 'Unknown';
  if(!isset($towns[$town])) $towns[$town]=['town'=>$town,'opportunities'=>0,'hot'=>0,'pipeline_value'=>0,'referral_potential'=>0];
  $towns[$town]['pipeline_value'] += $deal;
  $towns[$town]['referral_potential'] += $ref;

  if(count($topPipeline)<10){
    $topPipeline[]=[
      'builder'=>$p['builder_name']??'',
      'company'=>$p['company']??'',
      'address'=>$p['opportunity_address']??'',
      'town'=>$p['opportunity_town']??'',
      'stage'=>$stage,
      'probability'=>$prob,
      'deal_value'=>$deal,
      'referral_potential'=>$ref,
      'next_step'=>$p['next_step']??'',
      'next_followup_at'=>$p['next_followup_at']??''
    ];
  }

  if($prob>=60 || in_array($stage,['offer_possible','offer_made','under_contract'],true)){
    $alerts[]=[
      'type'=>'pipeline',
      'priority'=>$prob>=75?'urgent':'high',
      'title'=>($p['builder_name']??'Builder').' — '.$stage,
      'detail'=>($p['opportunity_address']??'').' — '.$prob.'% — Referral potential '.money115($ref)
    ];
  }
}

usort($towns,function($a,$b){
  $as=($a['hot']*5)+$a['opportunities']+(($a['referral_potential']??0)/10000);
  $bs=($b['hot']*5)+$b['opportunities']+(($b['referral_potential']??0)/10000);
  return $bs<=>$as;
});
$townSummary=array_slice(array_values($towns),0,12);

$brief = "Builder Executive Briefing — {$today}\n\n";
$brief .= "Total opportunities: {$totalOpp}\n";
$brief .= "Hot opportunities: {$hotOpp}\n";
$brief .= "Builder matches: {$builderMatches}\n";
$brief .= "Intro drafts: {$drafts}\n";
$brief .= "Intros sent: {$sent}\n";
$brief .= "Active pipeline: {$activePipeline}\n";
$brief .= "Followups due in 7 days: {$followupsDue}\n";
$brief .= "Pipeline value: ".money115($pipelineValue)."\n";
$brief .= "Referral potential: ".money115($referralPotential)."\n";
$brief .= "Expected referral value: ".money115($expectedReferral)."\n\n";
$brief .= "Top opportunities:\n";
foreach($topOpp as $i=>$o){
  $brief .= ($i+1).". {$o['address']} — {$o['town']} — {$o['type']} — Score {$o['score']}\n";
}
$brief .= "\nPriority alerts:\n";
foreach(array_slice($alerts,0,10) as $a){
  $brief .= "- {$a['title']}: {$a['detail']}\n";
}

$html='<h2>Builder Executive Briefing</h2><p><strong>'.$today.'</strong></p>';
$html.='<ul>';
$html.='<li>Total opportunities: '.$totalOpp.'</li>';
$html.='<li>Hot opportunities: '.$hotOpp.'</li>';
$html.='<li>Builder matches: '.$builderMatches.'</li>';
$html.='<li>Intros drafted: '.$drafts.'</li>';
$html.='<li>Intros sent: '.$sent.'</li>';
$html.='<li>Active pipeline: '.$activePipeline.'</li>';
$html.='<li>Followups due: '.$followupsDue.'</li>';
$html.='<li>Pipeline value: '.money115($pipelineValue).'</li>';
$html.='<li>Referral potential: '.money115($referralPotential).'</li>';
$html.='<li>Expected referral value: '.money115($expectedReferral).'</li>';
$html.='</ul><h3>Top Opportunities</h3><ol>';
foreach($topOpp as $o){
  $html.='<li><strong>'.htmlspecialchars($o['address']).'</strong> — '.htmlspecialchars($o['town']).' — '.htmlspecialchars($o['type']).' — Score '.$o['score'].'<br><small>'.htmlspecialchars($o['reason']).'</small></li>';
}
$html.='</ol><h3>Priority Alerts</h3><ul>';
foreach(array_slice($alerts,0,10) as $a){
  $html.='<li><strong>'.htmlspecialchars($a['title']).'</strong><br>'.htmlspecialchars($a['detail']).'</li>';
}
$html.='</ul>';

$send = ($_GET['send']??'')==='1';
$email=['ok'=>false,'skipped'=>true];
$sms=['ok'=>false,'skipped'=>true];

if($send){
  $email=send_email115('Builder Executive Briefing — '.$today,$html);
  $smsText="Builder Briefing {$today}: {$hotOpp} hot opps, {$activePipeline} active pipeline, {$followupsDue} followups, expected referral ".money115($expectedReferral).".";
  $sms=send_sms115($smsText);
}

$payload=[[
  'briefing_date'=>$today,
  'status'=>'created',
  'total_opportunities'=>$totalOpp,
  'hot_opportunities'=>$hotOpp,
  'builder_matches'=>$builderMatches,
  'intros_drafted'=>$drafts,
  'intros_sent'=>$sent,
  'active_pipeline'=>$activePipeline,
  'followups_due'=>$followupsDue,
  'interested_builders'=>$interested,
  'site_visits'=>$siteVisits,
  'offers_possible'=>$offersPossible,
  'offers_made'=>$offersMade,
  'under_contract'=>$underContract,
  'closed_deals'=>$closed,
  'pipeline_value'=>$pipelineValue,
  'referral_potential'=>$referralPotential,
  'expected_referral_value'=>$expectedReferral,
  'top_opportunities'=>$topOpp,
  'top_pipeline'=>$topPipeline,
  'town_summary'=>$townSummary,
  'mark_priority_alerts'=>array_slice($alerts,0,25),
  'briefing_text'=>$brief,
  'email_sent'=>!empty($email['ok']),
  'sms_sent'=>!empty($sms['ok']),
  'provider_response'=>['email'=>$email,'sms'=>$sms],
  'updated_at'=>date('c')
]];

$res=sb115('POST','builder_daily_briefings',$payload);

echo json_encode([
  'success'=>$res['ok'],
  'briefing'=>$payload[0],
  'supabase_http'=>$res['http'],
  'body'=>$res['ok']?'':$res['body']
],JSON_PRETTY_PRINT);
?>