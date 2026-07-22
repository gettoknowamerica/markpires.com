<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function api($m,$ep,$body=null,$range=null,$extra=[]){
 $headers=array_merge(['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],$extra);
 if($range)$headers[]='Range: '.$range;
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>120]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode($b,true);
 return['ok'=>$http>=200&&$http<300,'http'=>$http,'data'=>is_array($data)?$data:[],'body'=>$b];
}
try{
 $key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 api('DELETE','jessica_opportunity_engine?opportunity_type=eq.goliath_unique_seller');
 $created=0;$offset=0;$limit=1000;$preview=[];
 while(true){
  $rows=api('GET','goliath_consolidation_work?select=*&order=max_score.desc,updated_at.desc',null,$offset.'-'.($offset+$limit-1))['data'];
  if(!$rows)break; $payload=[];
  foreach($rows as $g){
   $attemptBonus=min(20,max(0,((int)$g['attempts']-1)*5));$recencyBonus=0;
   if(!empty($g['last_seen'])){$days=floor((time()-strtotime($g['last_seen']))/86400); if($days<=90)$recencyBonus=15; elseif($days<=180)$recencyBonus=12; elseif($days<=365)$recencyBonus=9; elseif($days<=730)$recencyBonus=5;}
   $luxuryBonus=((float)$g['highest_price']>=2000000)?10:(((float)$g['highest_price']>=1000000)?6:0);
   $score=min(100,(int)$g['max_score']+$attemptBonus+$recencyBonus+$luxuryBonus);
   $why='Unique unresolved failed-sale property. Attempts found: '.$g['attempts'].'.';
   if($g['last_seen'])$why.=' Last known failed activity: '.$g['last_seen'].'.';
   if($g['owner_name'])$why.=' Owner: '.$g['owner_name'].'.';
   if((float)$g['last_price']>0)$why.=' Last known list price: $'.number_format((float)$g['last_price']).'.';
   if((float)$g['highest_price']>0)$why.=' Highest known list price: $'.number_format((float)$g['highest_price']).'.';
   $payload[]=['opportunity_date'=>date('Y-m-d'),'opportunity_type'=>'goliath_unique_seller','title'=>'Goliath Seller Opportunity: '.$g['address'],'source_table'=>'goliath_consolidation_work','source_id'=>$g['source_ids'],'town'=>$g['town'],'address'=>$g['address'],'revenue_score'=>$score,'confidence_score'=>97,'urgency_score'=>min(100,75+$recencyBonus+$attemptBonus),'why_now'=>$why,'recommended_action'=>'Verify current MLS status and owner details. If still unsold/off-market, move to Mark review and prepare a value-first seller strategy.','content_angle'=>'Seller education: failed listing recovery, pricing reset, and marketing relaunch strategy.','ad_angle'=>'Did your home fail to sell? Get a fresh local strategy review before relisting.','followup_angle'=>'Create outreach list by score, recency, town, price, bedrooms, and square footage.','compliance_note'=>'MLS intelligence only. Mark performs human review before outreach.','status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')];
  }
  foreach(array_chunk($payload,200) as $chunk){$r=api('POST','jessica_opportunity_engine',$chunk); if($r['ok'])$created+=count($chunk);}
  if(!$preview)$preview=array_slice($payload,0,10);
  if(count($rows)<$limit)break; $offset+=$limit;
 }
 echo json_encode(['success'=>true,'consolidated_created'=>$created,'top_preview'=>$preview],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>