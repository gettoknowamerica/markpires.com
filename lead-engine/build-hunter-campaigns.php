<?php
/**
 * V10.2 Hunter Campaign Builder
 * Upload: /public_html/lead-engine/build-hunter-campaigns.php
 *
 * Run:
 * /lead-engine/build-hunter-campaigns.php?key=YOUR_KEY
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
if(!function_exists('str_contains')){
  function str_contains($haystack,$needle){return $needle!==''&&strpos((string)$haystack,(string)$needle)!==false;}
}
function sb102($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function phone102($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)===11&&substr($d,0,1)==='1')$d=substr($d,1);return$d;}
function score102($h,$campaign){
  $score=0;$why=[];
  $years=(float)($h['years_owned']??0);$equity=(float)($h['estimated_equity']??0);$town=strtolower((string)($h['town']??''));$ptype=strtolower((string)($h['property_type']??''));$base=(int)($h['lead_score']??0);$adaptive=(int)($h['adaptive_score']??0);
  if($years>=20){$score+=30;$why[]='20+ years owned';}elseif($years>=15){$score+=24;$why[]='15+ years owned';}elseif($years>=10){$score+=18;$why[]='10+ years owned';}elseif($years>=5){$score+=10;$why[]='5+ years owned';}
  if($equity>=1000000){$score+=25;$why[]='$1M+ equity';}elseif($equity>=750000){$score+=20;$why[]='$750k+ equity';}elseif($equity>=500000){$score+=15;$why[]='$500k+ equity';}elseif($equity>=250000){$score+=8;$why[]='$250k+ equity';}
  foreach(['greenwich','westport','darien','new canaan'] as $lux){if(str_contains($town,$lux)){$score+=15;$why[]='luxury town';break;}}
  foreach(['fairfield','wilton','weston','ridgefield','easton'] as $strong){if(str_contains($town,$strong)){$score+=8;$why[]='strong town';break;}}
  if(str_contains($ptype,'single')){$score+=8;$why[]='single family';}
  if($adaptive>$base&&$adaptive>=75){$score+=10;$why[]='adaptive boost';}
  $seg=$campaign['campaign_segment']??'general';
  if($seg==='luxury_equity'&&$equity>=750000){$score+=15;$why[]='luxury campaign match';}
  if($seg==='downsizer'&&$years>=15){$score+=15;$why[]='downsizer campaign match';}
  if($seg==='10_year_owner'&&$years>=10){$score+=10;$why[]='10-year campaign match';}
  $score=min(130,$score);
  $priority=$score>=100?'hot':($score>=85?'high':($score>=70?'review':'nurture'));
  return[$score,$priority,implode('; ',$why)];
}
function queued102($homeownerId,$campaignId){
  if(!$homeownerId)return false;
  $r=sb102('GET','hunter_queue?select=id&homeowner_id=eq.'.rawurlencode($homeownerId).'&campaign_id=eq.'.rawurlencode($campaignId).'&status=in.(review,approved,queued)&limit=1');
  return !empty($r['data']);
}

$campaigns=sb102('GET','hunter_campaigns?select=*&status=eq.active&order=created_at.asc&limit=50')['data'];
$created=[];$errors=[];$skipped=[];

foreach($campaigns as $c){
  if(!is_array($c))continue;
  $campaignId=(string)($c['id']??'');
  $daily=max(1,min(50,(int)($c['max_daily_calls']??25)));
  $minYears=(float)($c['min_years_owned']??5);
  $minEquity=(float)($c['min_equity']??0);
  $minScore=(int)($c['min_hunter_score']??70);
  $town=trim((string)($c['town']??''));

  $filters='homeowner_intelligence?select=*&dnc_status=neq.listed&years_owned=gte.'.rawurlencode($minYears).'&estimated_equity=gte.'.rawurlencode($minEquity);
  if($town!=='')$filters.='&town=ilike.'.rawurlencode('*'.$town.'*');
  $filters.='&order=lead_score.desc&limit=500';

  $homes=sb102('GET',$filters)['data'];
  $items=[];

  foreach($homes as $h){
    if(!is_array($h))continue;
    $homeId=(string)($h['id']??'');$phone=phone102($h['phone']??'');
    if(!$homeId||!$phone){$skipped[]=['campaign'=>$c['name']??'','reason'=>'missing id or phone'];continue;}
    if(queued102($homeId,$campaignId)){$skipped[]=['campaign'=>$c['name']??'','name'=>$h['owner_name']??'','reason'=>'already in campaign'];continue;}
    [$score,$priority,$reason]=score102($h,$c);
    if($score<$minScore){$skipped[]=['campaign'=>$c['name']??'','name'=>$h['owner_name']??'','reason'=>'below campaign score'];continue;}

    $items[]=[
      'campaign_id'=>$campaignId,'campaign_name'=>$c['name']??'','campaign_segment'=>$c['campaign_segment']??'general',
      'homeowner_id'=>$homeId,'owner_name'=>$h['owner_name']??'','phone'=>$phone,'email'=>$h['email']??'','address'=>$h['address']??'','town'=>$h['town']??'',
      'source'=>'hunter_campaign','hunter_score'=>$score,'base_score'=>(int)($h['lead_score']??0),'adaptive_score'=>(int)($h['adaptive_score']??0),
      'years_owned'=>$h['years_owned']??null,'estimated_equity'=>$h['estimated_equity']??null,'property_type'=>$h['property_type']??'',
      'priority'=>$priority,'status'=>'review','dnc_status'=>$h['dnc_status']??'unknown','reason'=>$reason,'suggested_script'=>$c['script_key']??'cold_homeowner','script_key'=>$c['script_key']??'cold_homeowner',
      'call_after'=>date('c',strtotime('+1 hour')),'call_by'=>date('c',strtotime('+2 days 5pm')),'raw_payload'=>$h,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    if(count($items)>=$daily)break;
  }

  if($items){
    $r=sb102('POST','hunter_queue',$items);
    if($r['ok']){$created[]=['campaign'=>$c['name']??'','count'=>count($items),'http'=>$r['http']]; sb102('PATCH','hunter_campaigns?id=eq.'.rawurlencode($campaignId),['last_built_at'=>date('c'),'targets_created'=>count($items),'updated_at'=>date('c')]);}
    else $errors[]=['campaign'=>$c['name']??'','http'=>$r['http'],'body'=>$r['body']];
  } else {
    $created[]=['campaign'=>$c['name']??'','count'=>0,'http'=>200];
  }
}

echo json_encode(['success'=>empty($errors),'campaigns_checked'=>count($campaigns),'created'=>$created,'errors'=>$errors,'skipped'=>array_slice($skipped,0,100)],JSON_PRETTY_PRINT);
?>