<?php
/**
 * V11.1 Builder / Developer Radar Builder
 * Upload: /public_html/lead-engine/build-builder-radar.php
 *
 * Run:
 * /lead-engine/build-builder-radar.php?key=YOUR_KEY
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

function sb111($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

function acreage111($h){
  foreach(['acreage','acres','lot_acres','land_acres'] as $k){
    if(isset($h[$k]) && is_numeric($h[$k])) return (float)$h[$k];
  }
  $lot=strtolower((string)($h['lot_size']??''));
  if(preg_match('/([0-9]+(\\.[0-9]+)?)\\s*(acre|acres|ac)/',$lot,$m)) return (float)$m[1];
  if(preg_match('/([0-9,]+)\\s*(sqft|sq ft|sf)/',$lot,$m)){
    $sf=(float)str_replace(',','',$m[1]);
    if($sf>0) return round($sf/43560,3);
  }
  return 0;
}

function score_builder111($h){
  $score=0;$reasons=[];$type='unknown';

  $town=strtolower((string)($h['town']??''));
  $ptype=strtolower((string)($h['property_type']??''));
  $address=strtolower((string)($h['address']??''));
  $years=(float)($h['years_owned']??0);
  $equity=(float)($h['estimated_equity']??0);
  $value=(float)($h['estimated_value']??0);
  $acres=acreage111($h);

  if($acres>=5){$score+=35;$reasons[]='+35 large parcel 5+ acres';$type='land';}
  elseif($acres>=2){$score+=25;$reasons[]='+25 parcel 2+ acres';$type='land';}
  elseif($acres>=1){$score+=15;$reasons[]='+15 parcel 1+ acre';}

  if($years>=25){$score+=25;$reasons[]='+25 owned 25+ years';}
  elseif($years>=15){$score+=15;$reasons[]='+15 owned 15+ years';}
  elseif($years>=10){$score+=10;$reasons[]='+10 owned 10+ years';}

  if($equity>=1000000){$score+=25;$reasons[]='+25 $1M+ equity';}
  elseif($equity>=500000){$score+=15;$reasons[]='+15 $500k+ equity';}
  elseif($equity>=250000){$score+=8;$reasons[]='+8 $250k+ equity';}

  foreach(['greenwich','westport','darien','new canaan'] as $lux){
    if(str_contains($town,$lux)){$score+=18;$reasons[]='+18 builder-demand luxury town';break;}
  }
  foreach(['fairfield','wilton','weston','ridgefield','easton','redding'] as $strong){
    if(str_contains($town,$strong)){$score+=10;$reasons[]='+10 strong builder town';break;}
  }

  if(str_contains($ptype,'land')||str_contains($ptype,'vacant')){$score+=35;$reasons[]='+35 land/vacant property';$type='land';}
  if(str_contains($ptype,'single')){$score+=8;$reasons[]='+8 single family candidate';}
  if($value>0 && $equity>0 && $equity/$value>=0.65){$score+=10;$reasons[]='+10 high equity ratio';}

  if(str_contains($address,'road')||str_contains($address,'lane')||str_contains($address,'drive')){$score+=3;$reasons[]='+3 residential setting';}

  if($type==='unknown'){
    if($acres>=1.5)$type='subdivision';
    elseif($years>=20 && $equity>=500000)$type='teardown';
    elseif($years>=15)$type='renovation';
  }

  $score=min(130,$score);
  $priority=$score>=100?'hot':($score>=85?'high':($score>=70?'review':'nurture'));
  return [$score,$priority,$type,implode('; ',$reasons),$acres];
}

$limit=max(50,min(1000,(int)($_GET['limit']??500)));
$homes=sb111('GET','homeowner_intelligence?select=*&dnc_status=neq.listed&order=lead_score.desc&limit='.$limit)['data'];
$items=[];$skipped=[];

foreach($homes as $h){
  if(!is_array($h))continue;
  $id=(string)($h['id']??'');
  if(!$id){$skipped[]=['reason'=>'missing id'];continue;}

  [$score,$priority,$type,$reason,$acres]=score_builder111($h);
  if($score<70){$skipped[]=['name'=>$h['owner_name']??'','reason'=>'below builder radar threshold'];continue;}

  $items[]=[
    'related_type'=>'homeowner_intelligence',
    'related_id'=>$id,
    'homeowner_id'=>$id,
    'owner_name'=>$h['owner_name']??'',
    'phone'=>$h['phone']??'',
    'email'=>$h['email']??'',
    'address'=>$h['address']??'',
    'town'=>$h['town']??'',
    'state'=>$h['state']??'CT',
    'property_type'=>$h['property_type']??'',
    'lot_size'=>$h['lot_size']??'',
    'acreage'=>$acres,
    'years_owned'=>$h['years_owned']??null,
    'last_sale_price'=>$h['last_sale_price']??null,
    'estimated_value'=>$h['estimated_value']??null,
    'estimated_equity'=>$h['estimated_equity']??null,
    'opportunity_type'=>$type,
    'builder_score'=>$score,
    'priority'=>$priority,
    'status'=>'review',
    'reason'=>$reason,
    'suggested_action'=>$priority==='hot'?'Review personally and consider builder/developer outreach.':'Add to builder radar watchlist.',
    'raw_payload'=>$h,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ];
}

$inserted=[];$errors=[];
foreach(array_chunk($items,100) as $chunk){
  $r=sb111('POST','builder_developer_opportunities',$chunk);
  if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
  else $errors[]=['http'=>$r['http'],'body'=>$r['body'],'error'=>$r['error']];
}

echo json_encode([
  'success'=>empty($errors),
  'attempted'=>count($items),
  'inserted_batches'=>$inserted,
  'errors'=>$errors,
  'skipped'=>array_slice($skipped,0,100)
],JSON_PRETTY_PRINT);
?>