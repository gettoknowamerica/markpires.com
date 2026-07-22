<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function api($m,$ep,$body=null,$extra=[]){
 $headers=array_merge(['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],$extra);
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HEADER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>90]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 $head=substr($raw,0,$hs);$bodytxt=substr($raw,$hs);$count=null;if(preg_match('/content-range:\s*\d+-\d+\/(\d+)/i',$head,$mm))$count=(int)$mm[1];
 $data=json_decode($bodytxt,true);return['ok'=>$http>=200&&$http<300,'http'=>$http,'count'=>$count,'data'=>is_array($data)?$data:[],'body'=>$bodytxt];
}
$key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
api('DELETE','goliath_consolidation_work?match_key=not.is.null');
api('DELETE','jessica_opportunity_engine?opportunity_type=eq.goliath_unique_seller');
$c=api('GET','jessica_opportunity_engine?select=id&opportunity_type=eq.failed_never_sold&limit=1',null,['Prefer: count=exact']);
echo json_encode(['success'=>true,'total_raw'=>$c['count']??0,'message'=>'Consolidation initialized. Work table cleared.'],JSON_PRETTY_PRINT);
?>