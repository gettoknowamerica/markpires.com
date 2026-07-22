<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function gget($ep,$range=null){
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: count=exact'];
  if($range)$headers[]='Range: '.$range;
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60]);
  $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); $err=curl_error($ch); curl_close($ch);
  $head=substr($raw,0,$hs); $body=substr($raw,$hs); $count=null;
  if(preg_match('/content-range:\s*\d+-\d+\/(\d+)/i',$head,$m))$count=(int)$m[1];
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'error'=>$err,'count'=>$count,'data'=>is_array($data)?$data:[],'body'=>$body];
}
function simple($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>30]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$offset=max(0,(int)($_GET['offset']??0)); $limit=max(1,min(1000,(int)($_GET['limit']??500)));
$r=gget('jessica_opportunity_engine?select=id,source_id,address,town,revenue_score,urgency_score,why_now,recommended_action,created_at&opportunity_type=eq.failed_never_sold&order=revenue_score.desc,created_at.desc',$offset.'-'.($offset+$limit-1));
$rows=$r['data'];
foreach($rows as &$row){
  $addr=strtolower(trim((string)($row['address']??'')));
  if(($addr===''||$addr==='not applicable'||$addr==='unknown address'||$addr==='n/a') && !empty($row['source_id'])){
    $raw=simple('mls_failed_records?select=*&id=eq.'.rawurlencode($row['source_id']).'&limit=1');
    if($raw){
      $fr=$raw[0]; $street=trim(($fr['street_number']??'').' '.($fr['street_name']??''));
      $row['address']=$fr['address']??($fr['Address']??($street?:$row['address']));
      $row['town']=$fr['town']??($fr['city']??($fr['City']??($row['town']??'')));
      if(!empty($fr['owner_name']) && stripos($row['why_now']??'','Owner:')===false) $row['why_now']=trim(($row['why_now']??'').' Owner: '.$fr['owner_name'].'.');
      if(!empty($fr['list_price']) && stripos($row['why_now']??'','Last known list price')===false) $row['why_now']=trim(($row['why_now']??'').' Last known list price: $'.number_format((float)$fr['list_price']).'.');
    }
  }
}
echo json_encode(['success'=>$r['ok'],'http'=>$r['http'],'offset'=>$offset,'limit'=>$limit,'total'=>$r['count'],'rows'=>$rows,'count'=>count($rows),'error'=>$r['error'],'details'=>$r['ok']?'':$r['body']],JSON_PRETTY_PRINT);
?>