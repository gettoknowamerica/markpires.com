<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function req($ep,$extra=[]){
  $headers=array_merge(['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],$extra);
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$ep);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60]);
  $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
  $head=substr($raw,0,$hs); $body=substr($raw,$hs); $data=json_decode($body,true);
  $count=null; if(preg_match('/content-range:\s*\d+-\d+\/(\d+)/i',$head,$m)) $count=(int)$m[1];
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'count'=>$count,'data'=>is_array($data)?$data:[],'body'=>$body];
}
function countq($table,$filter=''){
  $r=req($table.'?select=id&limit=1'.($filter?'&'.$filter:''),['Prefer: count=exact']);
  return ['ok'=>$r['ok'],'http'=>$r['http'],'count'=>$r['count']];
}
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
echo json_encode([
 'success'=>true,
 'counts'=>[
  'mls_failed_records'=>countq('mls_failed_records'),
  'mls_status_records'=>countq('mls_status_records'),
  'status_failed'=>countq('mls_status_records','status_type=eq.failed'),
  'status_closed'=>countq('mls_status_records','status_type=eq.closed'),
  'status_active'=>countq('mls_status_records','status_type=eq.active'),
  'status_pending'=>countq('mls_status_records','status_type=eq.pending')
 ],
 'status_sample'=>req('mls_status_records?select=status_type,status,source_name,import_batch,address,town&limit=20')['data'],
 'failed_sample'=>req('mls_failed_records?select=address,town,status,owner_name,list_price,days_on_market&limit=10')['data']
],JSON_PRETTY_PRINT);
?>