<?php
/**
 * V11.2 Builder Contact Matchmaker
 * Upload: /public_html/lead-engine/build-builder-matches.php
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
function sb112($method,$endpoint,$payload=null){
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
function match112($opp,$builder){
  $score=0;$why=[];
  $town=strtolower((string)($opp['town']??''));
  $type=strtolower((string)($opp['opportunity_type']??''));
  $builderTowns=strtolower((string)($builder['towns']??''));
  $profile=strtolower((string)(($builder['buyer_profile']??'').' '.($builder['property_preferences']??'').' '.($builder['notes']??'')));
  $oppScore=(int)($opp['builder_score']??0);
  $acres=(float)($opp['acreage']??0);

  if($town && str_contains($builderTowns,$town)){$score+=35;$why[]='town match';}
  elseif($builderTowns===''||str_contains($builderTowns,'fairfield county')||str_contains($builderTowns,'all')){$score+=15;$why[]='broad area match';}

  if($type && str_contains($profile,$type)){$score+=25;$why[]='opportunity type match';}
  if($type==='land' && (str_contains($profile,'land')||str_contains($profile,'acre')||str_contains($profile,'subdivision'))){$score+=25;$why[]='land profile match';}
  if($type==='teardown' && (str_contains($profile,'teardown')||str_contains($profile,'new construction')||str_contains($profile,'build'))){$score+=25;$why[]='teardown/build profile match';}
  if($type==='renovation' && (str_contains($profile,'renovation')||str_contains($profile,'flip')||str_contains($profile,'investor'))){$score+=20;$why[]='renovation/investor profile match';}

  if($oppScore>=100){$score+=15;$why[]='hot opportunity score';}
  elseif($oppScore>=85){$score+=10;$why[]='high opportunity score';}

  if($acres>=2 && (str_contains($profile,'land')||str_contains($profile,'acre')||str_contains($profile,'developer'))){$score+=15;$why[]='large parcel developer fit';}

  $score=min(100,$score);
  return [$score,implode('; ',$why)];
}

$opps=sb112('GET','builder_developer_opportunities?select=*&status=in.(review,approved,hot)&order=builder_score.desc&limit=300')['data'];
$builders=sb112('GET','builder_contacts?select=*&status=eq.active&limit=500')['data'];

$created=[];$skipped=[];$errors=[];
foreach($opps as $opp){
  if(!is_array($opp) || empty($opp['id']))continue;
  $items=[];
  foreach($builders as $b){
    if(!is_array($b)||empty($b['id']))continue;
    [$score,$reason]=match112($opp,$b);
    if($score<35){continue;}
    $items[]=[
      'opportunity_id'=>$opp['id'],
      'builder_contact_id'=>$b['id'],
      'builder_name'=>$b['name']??'',
      'company'=>$b['company']??'',
      'phone'=>$b['phone']??'',
      'email'=>$b['email']??'',
      'opportunity_address'=>$opp['address']??'',
      'opportunity_town'=>$opp['town']??'',
      'opportunity_type'=>$opp['opportunity_type']??'',
      'match_score'=>$score,
      'reason'=>$reason,
      'status'=>'review',
      'raw_payload'=>['opportunity'=>$opp,'builder'=>$b],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
  }
  usort($items,function($a,$b){return $b['match_score']<=>$a['match_score'];});
  $items=array_slice($items,0,5);
  if(!$items){$skipped[]=['opportunity'=>$opp['address']??'','reason'=>'no matches'];continue;}
  $r=sb112('POST','builder_opportunity_matches',$items);
  if($r['ok']){
    $created[]=['opportunity'=>$opp['address']??'','matches'=>count($items)];
    sb112('PATCH','builder_developer_opportunities?id=eq.'.rawurlencode($opp['id']),[
      'matches_built'=>true,
      'top_builder_match'=>($items[0]['builder_name']?:$items[0]['company']),
      'updated_at'=>date('c')
    ]);
  } else $errors[]=['opportunity'=>$opp['address']??'','http'=>$r['http'],'body'=>$r['body']];
}

echo json_encode(['success'=>empty($errors),'opportunities_checked'=>count($opps),'builders_checked'=>count($builders),'created'=>$created,'skipped'=>array_slice($skipped,0,100),'errors'=>$errors],JSON_PRETTY_PRINT);
?>