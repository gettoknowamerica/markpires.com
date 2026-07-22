<?php
/**
 * V10.7 Hunter Daily Briefing
 * Upload: /public_html/lead-engine/build-hunter-briefing.php
 *
 * Run:
 * /lead-engine/build-hunter-briefing.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb107($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=merge-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function send_resend107($subject,$html){
  if(!defined('RESEND_API_KEY')||!RESEND_API_KEY||!defined('ADMIN_EMAIL')||!ADMIN_EMAIL)return['ok'=>false,'error'=>'Resend/Admin email not configured'];
  $from=(defined('RESEND_FROM_EMAIL')&&RESEND_FROM_EMAIL)?RESEND_FROM_EMAIL:'noreply@markpires.com';
  $payload=['from'=>'Jessica <'.$from.'>','to'=>[ADMIN_EMAIL],'subject'=>$subject,'html'=>$html];
  $ch=curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.RESEND_API_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}
function norm107($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)==10)return'+1'.$d;if(strlen($d)==11&&substr($d,0,1)==='1')return'+'.$d;return$p;}
function send_sms107($body){
  if(!defined('TWILIO_ACCOUNT_SID')||!defined('TWILIO_AUTH_TOKEN')||!defined('TWILIO_SMS_FROM')||!defined('ADMIN_PHONE')||!ADMIN_PHONE)return['ok'=>false,'error'=>'Twilio/Admin phone not configured'];
  $url='https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'/Messages.json';
  $post=http_build_query(['From'=>norm107(TWILIO_SMS_FROM),'To'=>norm107(ADMIN_PHONE),'Body'=>$body]);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_USERPWD=>TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$resp,'error'=>$err];
}

$today=date('Y-m-d');
$start=$today.'T00:00:00';

$queue=sb107('GET','hunter_queue?select=*&order=hunter_score.desc&limit=1000')['data'];
$top=sb107('GET','hunter_queue?select=*&status=in.(review,approved,queued)&order=hunter_score.desc&limit=10')['data'];
$outcomes=sb107('GET','hunter_outcomes?select=*&created_at=gte.'.rawurlencode($start).'&limit=1000')['data'];
$runs=sb107('GET','hunter_call_runs?select=*&created_at=gte.'.rawurlencode($start).'&limit=1000')['data'];
$campaigns=sb107('GET','hunter_campaigns?select=*&order=conversion_rate.desc&limit=100')['data'];

$total=count($queue);$approved=0;$calledToday=0;$future=0;$appts=0;$towns=[];
foreach($queue as $q){
  if(!empty($q['approved_by_mark'])||in_array(($q['status']??''),['approved','queued'],true))$approved++;
  $t=$q['town']?:'Unknown';$towns[$t]=($towns[$t]??0)+1;
}
foreach($runs as $r){$calledToday+=(int)($r['called']??0);}
foreach($outcomes as $o){if(!empty($o['future_seller']))$future++;if(!empty($o['appointment_requested']))$appts++;}
arsort($towns);
$topTowns=[];foreach(array_slice($towns,0,8,true) as $town=>$count){$topTowns[]=['town'=>$town,'targets'=>$count];}

$topTargets=[];
foreach($top as $t){
  $topTargets[]=[
    'name'=>$t['owner_name']??'',
    'town'=>$t['town']??'',
    'phone'=>$t['phone']??'',
    'score'=>(int)($t['hunter_score']??0),
    'reason'=>$t['reason']??'',
    'status'=>$t['status']??''
  ];
}

$campaignSummary=[];
foreach($campaigns as $c){
  $campaignSummary[]=[
    'name'=>$c['name']??'',
    'status'=>$c['status']??'',
    'conversion_rate'=>(float)($c['conversion_rate']??0),
    'attempts'=>(int)($c['calls_attempted']??0),
    'future_sellers'=>(int)($c['future_sellers_found']??0),
    'appointments'=>(int)($c['appointments_found']??0)
  ];
}

$brief="Jessica Hunter Daily Briefing — {$today}\n\n";
$brief.="Total hunter targets: {$total}\n";
$brief.="Approved targets: {$approved}\n";
$brief.="Called today: {$calledToday}\n";
$brief.="Future sellers found today: {$future}\n";
$brief.="Appointments found today: {$appts}\n\n";
$brief.="Top targets:\n";
foreach($topTargets as $i=>$t){$n=$i+1;$brief.="{$n}. {$t['name']} — {$t['town']} — Score {$t['score']} — {$t['status']}\n";}
$brief.="\nTop towns:\n";
foreach($topTowns as $t){$brief.="- {$t['town']}: {$t['targets']} targets\n";}

$html='<h2>Jessica Hunter Daily Briefing</h2><p><strong>'.$today.'</strong></p>';
$html.='<ul><li>Total hunter targets: '.$total.'</li><li>Approved targets: '.$approved.'</li><li>Called today: '.$calledToday.'</li><li>Future sellers today: '.$future.'</li><li>Appointments today: '.$appts.'</li></ul>';
$html.='<h3>Top Targets</h3><ol>';
foreach($topTargets as $t){$html.='<li><strong>'.htmlspecialchars($t['name']).'</strong> — '.htmlspecialchars($t['town']).' — Score '.$t['score'].'<br><small>'.htmlspecialchars($t['reason']).'</small></li>';}
$html.='</ol><h3>Top Towns</h3><ul>';
foreach($topTowns as $t){$html.='<li>'.htmlspecialchars($t['town']).': '.$t['targets'].' targets</li>';}
$html.='</ul>';

$send=($_GET['send']??'')==='1';
$email=['ok'=>false,'skipped'=>true];$sms=['ok'=>false,'skipped'=>true];
if($send){
  $email=send_resend107('Jessica Hunter Daily Briefing — '.$today,$html);
  $smsText="Jessica Hunter Briefing {$today}: {$approved} approved, {$calledToday} called today, {$future} future sellers, {$appts} appts. Top target: ".($topTargets[0]['name']??'none');
  $sms=send_sms107($smsText);
}

$payload=[[
  'briefing_date'=>$today,
  'status'=>'created',
  'total_hunter_targets'=>$total,
  'approved_targets'=>$approved,
  'called_today'=>$calledToday,
  'future_sellers_found'=>$future,
  'appointments_found'=>$appts,
  'top_towns'=>$topTowns,
  'top_targets'=>$topTargets,
  'campaign_summary'=>$campaignSummary,
  'briefing_text'=>$brief,
  'email_sent'=>!empty($email['ok']),
  'sms_sent'=>!empty($sms['ok']),
  'provider_response'=>['email'=>$email,'sms'=>$sms],
  'updated_at'=>date('c')
]];
$res=sb107('POST','hunter_daily_briefings',$payload);

echo json_encode([
  'success'=>$res['ok'],
  'briefing'=>$payload[0],
  'supabase_http'=>$res['http'],
  'body'=>$res['ok']?'':$res['body']
],JSON_PRETTY_PRINT);
?>