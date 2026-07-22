<?php
/**
 * V21 Drive Mode Executive Patch
 * Optional replacement for /public_html/lead-engine/jessica-drive-api.php
 * Adds direct Executive Assistant answers while preserving natural conversation behavior.
 */
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
function jd_sb($m,$ep,$p=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>60]);if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return ['ok'=>$h>=200&&$h<300,'data'=>is_array($d)?$d:[],'body'=>$b];}
function rows($ep){$r=jd_sb('GET',$ep);return $r['ok']?$r['data']:[];}
try{
 $in=json_decode(file_get_contents('php://input'),true);if(!is_array($in))$in=array_merge($_GET,$_POST);
 $key=$in['key']??'';if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 $msg=trim($in['message']??'What should I focus on right now?');$m=strtolower($msg);
 $plan=rows('goliath_daily_plans?select=*&order=created_at.desc&limit=1');$opps=rows('jessica_opportunity_engine?select=*&order=revenue_score.desc,confidence_score.desc&limit=3');
 if(str_contains($m,'money')||str_contains($m,'today')||str_contains($m,'focus')||str_contains($m,'executive')||str_contains($m,'priority')){
   if($plan){$response=$plan[0]['executive_summary'];}
   elseif($opps){$o=$opps[0];$response='Top opportunity: '.$o['title'].'. Revenue score '.$o['revenue_score'].'. Recommended action: '.$o['recommended_action'];}
   else{$response='I need you to run the Opportunity Engine and Executive Assistant builder first.';}
 } elseif(str_contains($m,'opportunit')){
   if($opps){$o=$opps[0];$response='The best opportunity I see is '.$o['title'].' with revenue score '.$o['revenue_score'].'. Why now: '.$o['why_now'].' Recommended action: '.$o['recommended_action'];}
   else{$response='No ranked opportunities yet. Run the Opportunity Engine.';}
 } else {
   $response=$plan?$plan[0]['executive_summary']:'Jessica is online. Ask me where the money is, what to focus on today, or what the best opportunity is.';
 }
 jd_sb('POST','jessica_drive_sessions',[['session_date'=>date('Y-m-d'),'user_message'=>$msg,'jessica_response'=>$response,'mode'=>'drive','source'=>'v21_executive','created_at'=>date('c')]]);
 echo json_encode(['success'=>true,'response'=>$response],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>