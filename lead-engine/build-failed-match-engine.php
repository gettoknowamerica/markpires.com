<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb($m,$ep,$p=null){
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>180]);
 if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
 $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
 return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function c($s){$s=strtolower(trim((string)$s));$s=preg_replace('/\b(road|rd|street|st|avenue|ave|lane|ln|drive|dr|court|ct|place|pl|circle|cir|boulevard|blvd|terrace|ter)\b/','',$s);return preg_replace('/[^a-z0-9]+/','',$s);}
function k($r){return c($r['address']??'').'|'.c($r['town']??'');}
function bkt($r){$s=strtolower(($r['status_type']??'').' '.($r['status']??'').' '.($r['last_status']??''));if(str_contains($s,'closed')||str_contains($s,'sold')||str_contains($s,'clsd'))return'sold';if(str_contains($s,'active')||str_contains($s,'actv'))return'active';if(str_contains($s,'pending')||str_contains($s,'deposit')||str_contains($s,'under contract')||str_contains($s,'pnd'))return'pending';if(str_contains($s,'expired')||str_contains($s,'expd')||str_contains($s,'withdraw')||str_contains($s,'with')||str_contains($s,'cancel')||str_contains($s,'canc'))return'failed';return'other';}
function n($v){return(float)preg_replace('/[^0-9.]/','',(string)$v);}
function sc($r){$score=55;$s=strtolower(($r['status']??'').' '.($r['status_type']??''));$p=n($r['list_price']??$r['price']??0);$dom=(int)n($r['days_on_market']??$r['dom']??0);if(str_contains($s,'exp'))$score+=18;if(str_contains($s,'with'))$score+=12;if(str_contains($s,'canc'))$score+=10;if($dom>=90)$score+=10;if($dom>=180)$score+=8;if($dom>=365)$score+=5;if($p>=700000)$score+=8;if($p>=1200000)$score+=8;if($p>=2000000)$score+=5;if(!empty($r['owner_name']))$score+=4;return min(100,$score);}

try{
 $key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 sb('DELETE','jessica_opportunity_engine?source_table=eq.mls_status_records');
 $rows=sb('GET','mls_status_records?select=*&limit=50000')['data'];
 $sold=[];$active=[];$pending=[];$failed=[];
 foreach($rows as $r){$keyrow=k($r);if(strlen($keyrow)<4)continue;$b=bkt($r);if($b==='sold')$sold[$keyrow]=true;elseif($b==='active')$active[$keyrow]=true;elseif($b==='pending')$pending[$keyrow]=true;elseif($b==='failed')$failed[]=$r;}
 $reviewed=count($failed);$ms=0;$ma=0;$mp=0;$never=0;$created=0;$seen=[];$batch=[];
 foreach($failed as $r){$keyrow=k($r);if(isset($seen[$keyrow]))continue;$seen[$keyrow]=true;if(isset($sold[$keyrow])){$ms++;continue;}if(isset($active[$keyrow])){$ma++;continue;}if(isset($pending[$keyrow])){$mp++;continue;}$never++;$score=sc($r);$addr=$r['address']??'Unknown address';$town=$r['town']??'';$price=n($r['list_price']??$r['price']??0);$why='Failed listing did not match any later sold, active, or pending MLS status record. This is an unresolved seller opportunity candidate.';if($price>0)$why.=' Last known price: $'.number_format($price).'.';
  $batch[]=['opportunity_date'=>date('Y-m-d'),'opportunity_type'=>'failed_never_sold','title'=>'Never-Sold Failed Listing: '.$addr,'source_table'=>'mls_status_records','source_id'=>(string)($r['id']??''),'town'=>$town,'address'=>$addr,'revenue_score'=>$score,'confidence_score'=>96,'urgency_score'=>85,'why_now'=>$why,'recommended_action'=>'Verify current status one more time, then prepare a value-first seller strategy outreach.','content_angle'=>'Create seller content: Why homes fail to sell and what to do differently before relisting.','ad_angle'=>'Home Value Funnel angle: Did your home fail to sell? Get a fresh local strategy review.','followup_angle'=>'Review owner/property details, confirm not sold/active/pending, then add to Mark review list.','compliance_note'=>'MLS status intelligence. Human review before outreach.','status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')];
  if(count($batch)>=200){$res=sb('POST','jessica_opportunity_engine',$batch);if($res['ok'])$created+=count($batch);$batch=[];}
 }
 if($batch){$res=sb('POST','jessica_opportunity_engine',$batch);if($res['ok'])$created+=count($batch);}
 sb('POST','mls_failed_match_runs',[['run_date'=>date('c'),'failed_reviewed'=>$reviewed,'marked_sold'=>$ms,'marked_active'=>$ma,'marked_pending'=>$mp,'marked_never_sold'=>$never,'opportunities_created'=>$created,'notes'=>'Run Goliath V21.2 status match: failed minus sold/active/pending equals opportunity.']]);
 echo json_encode(['success'=>true,'failed_reviewed'=>$reviewed,'sold_removed'=>$ms,'active_removed'=>$ma,'pending_removed'=>$mp,'never_sold_opportunities'=>$never,'opportunities_created'=>$created,'rule'=>'Failed listings are kept only when no matching sold, active, or pending record exists.'],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>