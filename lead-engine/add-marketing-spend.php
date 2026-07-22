<?php
/**
 * V12.8 Add Marketing Spend
 * /lead-engine/add-marketing-spend.php?key=YOUR_KEY&platform=Meta&campaign=Greenwich%20Home%20Value&spend=25
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
function sb128a($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$payload=[[
  'spend_date'=>$_GET['date']??date('Y-m-d'),
  'platform'=>$_GET['platform']??'Meta',
  'campaign_name'=>$_GET['campaign']??'Manual Campaign',
  'market'=>$_GET['market']??'Fairfield County',
  'town'=>$_GET['town']??'',
  'audience'=>$_GET['audience']??'',
  'spend'=>(float)($_GET['spend']??0),
  'impressions'=>(int)($_GET['impressions']??0),
  'clicks'=>(int)($_GET['clicks']??0),
  'leads'=>(int)($_GET['leads']??0),
  'appointments'=>(int)($_GET['appointments']??0),
  'notes'=>$_GET['notes']??'Manual spend log',
  'created_at'=>date('c'),
  'updated_at'=>date('c')
]];
$res=sb128a('POST','marketing_spend_log',$payload);
echo json_encode(['success'=>$res['ok'],'inserted'=>$payload[0],'supabase'=>$res],JSON_PRETTY_PRINT);
?>