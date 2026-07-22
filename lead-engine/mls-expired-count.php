<?php
/**
 * V20.9.4 Paged MLS Count
 * Replace: /public_html/lead-engine/mls-expired-count.php
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb($m,$ep,$p=null,$extra=[]){
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers=array_merge($headers,$extra);
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>90]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $raw=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
  $head=substr($raw,0,$hs); $body=substr($raw,$hs); $d=json_decode($body,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'headers'=>$head,'body'=>$body,'data'=>is_array($d)?$d:[]];
}
try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

  $c=sb('GET','mls_expired_records?select=id&limit=1',null,['Prefer: count=exact']);
  $count=0;
  if(preg_match('/content-range:\s*\d+-\d+\/(\d+)/i',$c['headers'],$m)) $count=(int)$m[1];

  $batches=[]; $statuses=[]; $max=0; $loaded=0; $page=0; $pageSize=1000;
  while(true){
    $offset=$page*$pageSize;
    $r=sb('GET','mls_expired_records?select=import_batch,status,opportunity_score&order=created_at.asc&limit='.$pageSize.'&offset='.$offset);
    if(!$r['ok']) break;
    $rows=$r['data'];
    if(empty($rows)) break;
    foreach($rows as $row){
      $b=$row['import_batch']?:'unknown';
      $s=$row['status']?:'unknown';
      if(!isset($batches[$b]))$batches[$b]=0; $batches[$b]++;
      if(!isset($statuses[$s]))$statuses[$s]=0; $statuses[$s]++;
      $max=max($max,(int)($row['opportunity_score']??0));
      $loaded++;
    }
    if(count($rows)<$pageSize) break;
    $page++;
    if($page>100) break;
  }
  arsort($batches); arsort($statuses);
  echo json_encode(['success'=>true,'count'=>$count,'paged_loaded'=>$loaded,'batches'=>$batches,'statuses'=>$statuses,'max_opportunity_score'=>$max],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>