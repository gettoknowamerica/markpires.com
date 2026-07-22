<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function api($method,$ep,$body=null,$extra=[]){
 $headers=array_merge(['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],$extra);
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$ep);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HEADER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>120]);
 if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
 $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE); curl_close($ch);
 $bodytxt=substr($raw,$hs); $data=json_decode($bodytxt,true);
 $count=null; if(preg_match('/content-range:\s*\d+-\d+\/(\d+)/i',substr($raw,0,$hs),$m)) $count=(int)$m[1];
 return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$bodytxt,'data'=>is_array($data)?$data:[],'count'=>$count];
}
function cnt($table,$filter=''){return api('GET',$table.'?select=id&limit=1'.($filter?'&'.$filter:''),null,['Prefer: count=exact'])['count']??0;}
function clean($s){$s=strtolower(trim((string)$s));$s=preg_replace('/\b(road|rd|street|st|avenue|ave|lane|ln|drive|dr|court|ct|place|pl|circle|cir|boulevard|blvd|terrace|ter|way|parkway|pkwy)\b/','',$s);return preg_replace('/[^a-z0-9]+/','',$s);}
function val($r,$keys){foreach($keys as $k){if(isset($r[$k])&&trim((string)$r[$k])!=='')return $r[$k];}return '';}
function keyrow($r){return clean(val($r,['address','Address','property_address'])).'|'.clean(val($r,['town','Town','city','City']));}
function num($v){return (float)preg_replace('/[^0-9.]/','',(string)$v);}
function score($r){$s=60;$p=num(val($r,['list_price','price','List Price']));$dom=(int)num(val($r,['days_on_market','dom','DOM']));$st=strtolower(val($r,['status','Status']).' '.val($r,['status_type']));if(str_contains($st,'exp'))$s+=18;if(str_contains($st,'with'))$s+=12;if(str_contains($st,'canc'))$s+=10;if($dom>=90)$s+=10;if($dom>=180)$s+=8;if($dom>=365)$s+=5;if($p>=700000)$s+=8;if($p>=1200000)$s+=8;if($p>=2000000)$s+=5;if(val($r,['owner_name','Owner Name']))$s+=4;return min(100,$s);}
try{
 $key=$_GET['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
 $offset=max(0,(int)($_GET['offset']??0)); $limit=max(1,min(1000,(int)($_GET['limit']??1000)));
 $source=cnt('mls_failed_records')>0?'mls_failed_records':'mls_status_records';
 $failedEp=$source==='mls_failed_records'?'mls_failed_records?select=*&order=address.asc&limit='.$limit.'&offset='.$offset:'mls_status_records?select=*&status_type=eq.failed&order=address.asc&limit='.$limit.'&offset='.$offset;
 $sold=[];$act=[];$pend=[];
 foreach(api('GET','mls_status_records?select=address,town&status_type=eq.closed&limit=50000')['data'] as $r){$k=keyrow($r);if(strlen($k)>4)$sold[$k]=1;}
 foreach(api('GET','mls_status_records?select=address,town&status_type=eq.active&limit=50000')['data'] as $r){$k=keyrow($r);if(strlen($k)>4)$act[$k]=1;}
 foreach(api('GET','mls_status_records?select=address,town&status_type=eq.pending&limit=50000')['data'] as $r){$k=keyrow($r);if(strlen($k)>4)$pend[$k]=1;}
 $failed=api('GET',$failedEp)['data']; $reviewed=count($failed); $sr=0;$ar=0;$pr=0;$never=0;$created=0;$batch=[];$seen=[];
 foreach($failed as $r){
  $k=keyrow($r); if(strlen($k)<=4 || isset($seen[$k]))continue; $seen[$k]=1;
  if(isset($sold[$k])){$sr++;continue;} if(isset($act[$k])){$ar++;continue;} if(isset($pend[$k])){$pr++;continue;}
  $never++; $addr=val($r,['address','Address','property_address'])?:'Unknown address'; $town=val($r,['town','Town','city','City']); $price=num(val($r,['list_price','price','List Price']));
  $why='Failed listing has no matching sold, active, or pending record in the uploaded MLS data. This remains an unresolved seller opportunity.'; if($price>0)$why.=' Last known list price: $'.number_format($price).'.';
  $batch[]=['opportunity_date'=>date('Y-m-d'),'opportunity_type'=>'failed_never_sold','title'=>'Never-Sold Failed Listing: '.$addr,'source_table'=>$source,'source_id'=>(string)($r['id']??''),'town'=>$town,'address'=>$addr,'revenue_score'=>score($r),'confidence_score'=>96,'urgency_score'=>85,'why_now'=>$why,'recommended_action'=>'Verify current status once more, then prepare a value-first seller strategy outreach.','content_angle'=>'Seller education: why homes fail to sell and what to do differently before relisting.','ad_angle'=>'Home Value Funnel: Did your home fail to sell? Get a fresh local strategy review.','followup_angle'=>'Review details, confirm not sold/active/pending, then add to Mark review list.','compliance_note'=>'MLS intelligence; Mark performs human review before outreach.','status'=>'new','created_at'=>date('c'),'updated_at'=>date('c')];
  if(count($batch)>=200){$res=api('POST','jessica_opportunity_engine',$batch);if($res['ok'])$created+=count($batch);$batch=[];}
 }
 if($batch){$res=api('POST','jessica_opportunity_engine',$batch);if($res['ok'])$created+=count($batch);}
 echo json_encode(['success'=>true,'failed_source'=>$source,'offset'=>$offset,'limit'=>$limit,'reviewed'=>$reviewed,'sold_removed'=>$sr,'active_removed'=>$ar,'pending_removed'=>$pr,'never_sold'=>$never,'opportunities_created'=>$created],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>