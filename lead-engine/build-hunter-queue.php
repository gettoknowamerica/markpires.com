<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
if (!function_exists('str_contains')) {
  function str_contains($haystack,$needle){ return $needle !== '' && strpos((string)$haystack,(string)$needle) !== false; }
}
function sb10($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}
function phone10($p){ $d=preg_replace('/\D+/','',(string)$p); if(strlen($d)===11 && substr($d,0,1)==='1')$d=substr($d,1); return $d; }
function score10($h){
  $score=0; $reasons=[];
  $years=(float)($h['years_owned']??0); $equity=(float)($h['estimated_equity']??0);
  $town=strtolower((string)($h['town']??'')); $ptype=strtolower((string)($h['property_type']??''));
  $base=(int)($h['lead_score']??0); $adaptive=(int)($h['adaptive_score']??0);
  $last=strtolower((string)($h['last_outcome']??'')); $status=strtolower((string)($h['status']??''));
  if($years>=20){$score+=30;$reasons[]='+30 owned 20+ years';} elseif($years>=15){$score+=24;$reasons[]='+24 owned 15+ years';} elseif($years>=10){$score+=18;$reasons[]='+18 owned 10+ years';} elseif($years>=5){$score+=10;$reasons[]='+10 owned 5+ years';}
  if($equity>=1000000){$score+=25;$reasons[]='+25 $1M+ equity';} elseif($equity>=750000){$score+=20;$reasons[]='+20 $750k+ equity';} elseif($equity>=500000){$score+=15;$reasons[]='+15 $500k+ equity';} elseif($equity>=250000){$score+=8;$reasons[]='+8 $250k+ equity';}
  foreach(['greenwich','westport','darien','new canaan'] as $lux){ if(str_contains($town,$lux)){$score+=15;$reasons[]='+15 priority luxury town';break;} }
  foreach(['fairfield','wilton','weston','ridgefield','easton'] as $strong){ if(str_contains($town,$strong)){$score+=8;$reasons[]='+8 strong town';break;} }
  if(str_contains($ptype,'single')){$score+=8;$reasons[]='+8 single family';}
  if($adaptive>$base && $adaptive>=75){$score+=10;$reasons[]='+10 adaptive boost';}
  if($last==='future_seller'){$score+=40;$reasons[]='+40 previous future seller';}
  if($last==='interested'){$score+=45;$reasons[]='+45 previous interest';}
  if($status==='future_seller'){$score+=35;$reasons[]='+35 future seller status';}
  $score=min(125,$score);
  $priority=$score>=100?'hot':($score>=85?'high':($score>=70?'review':'nurture'));
  return [$score,$priority,implode('; ',$reasons)];
}
function queued10($homeownerId,$phone){
  if($homeownerId){$r=sb10('GET','hunter_queue?select=id&homeowner_id=eq.'.rawurlencode($homeownerId).'&status=in.(review,approved,queued)&limit=1'); if(!empty($r['data'])) return true;}
  if($phone){$r=sb10('GET','hunter_queue?select=id&phone=eq.'.rawurlencode($phone).'&status=in.(review,approved,queued)&limit=1'); if(!empty($r['data'])) return true;}
  return false;
}
$limit=max(25,min(500,(int)($_GET['limit']??250)));
$daily=max(5,min(50,(int)($_GET['daily']??25)));
$res=sb10('GET','homeowner_intelligence?select=*&order=lead_score.desc&limit='.$limit);
$homeowners=$res['data'];
$items=[];$skipped=[];
foreach($homeowners as $h){
  if(!is_array($h))continue;
  $id=(string)($h['id']??''); $phone=phone10($h['phone']??'');
  if(!$id||!$phone){$skipped[]=['name'=>$h['owner_name']??'','reason'=>'missing id or phone'];continue;}
  if(($h['dnc_status']??'')==='listed'){$skipped[]=['name'=>$h['owner_name']??'','reason'=>'dnc listed'];continue;}
  if(queued10($id,$phone)){$skipped[]=['name'=>$h['owner_name']??'','reason'=>'already queued'];continue;}
  [$score,$priority,$reason]=score10($h);
  if($score<70){$skipped[]=['name'=>$h['owner_name']??'','reason'=>'score below hunter threshold'];continue;}
  $items[]=[
    'homeowner_id'=>$id,'owner_name'=>$h['owner_name']??'','phone'=>$phone,'email'=>$h['email']??'','address'=>$h['address']??'','town'=>$h['town']??'',
    'source'=>'homeowner_hunter','hunter_score'=>$score,'base_score'=>(int)($h['lead_score']??0),'adaptive_score'=>(int)($h['adaptive_score']??0),
    'years_owned'=>$h['years_owned']??null,'estimated_equity'=>$h['estimated_equity']??null,'property_type'=>$h['property_type']??'',
    'priority'=>$priority,'status'=>'review','dnc_status'=>$h['dnc_status']??'unknown','reason'=>$reason,'suggested_script'=>'cold_homeowner',
    'call_after'=>date('c',strtotime('+1 hour')),'call_by'=>date('c',strtotime('+2 days 5pm')),'raw_payload'=>$h,'created_at'=>date('c'),'updated_at'=>date('c')
  ];
  if(count($items)>=$daily)break;
}
$inserted=[];$errors=[];
foreach(array_chunk($items,100) as $chunk){$r=sb10('POST','hunter_queue',$chunk); if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']]; else $errors[]=['http'=>$r['http'],'body'=>$r['body'],'error'=>$r['error']];}
echo json_encode(['success'=>empty($errors),'attempted'=>count($items),'inserted_batches'=>$inserted,'errors'=>$errors,'skipped'=>array_slice($skipped,0,100),'source'=>['homeowners_http'=>$res['http'],'homeowners_rows'=>count($homeowners)]],JSON_PRETTY_PRINT);
?>