<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>75]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function norm($s){return strtolower(trim(preg_replace('/[^a-z0-9]+/i',' ',(string)$s)));}
function getv($row,$keys){foreach($keys as $k){foreach($row as $rk=>$v){if(norm($rk)===norm($k)&&trim((string)$v)!=='')return trim((string)$v);}}return '';}
function n($v){$x=(float)preg_replace('/[^0-9.\-]/','',(string)$v); return ($x>9999999999||$x<-1)?0:$x;}
function si($v,$min=0,$max=10000){$x=(int)n($v); return ($x<$min||$x>$max)?0:$x;}
function yr($v){$x=(int)n($v); return ($x>=1600&&$x<=2100)?$x:null;}
function dv($v){$t=strtotime((string)$v);return $t?date('Y-m-d',$t):null;}
function dt($v){$t=strtotime((string)$v);return $t?date('c',$t):null;}
function sc($r){$s=48;$dom=(int)$r['days_on_market'];$p=(float)$r['list_price'];$st=strtoupper((string)$r['status']);if(str_contains($st,'EXP'))$s+=15;if(str_contains($st,'WITH'))$s+=10;if(str_contains($st,'CANC'))$s+=10;if($dom>=90)$s+=12;if($dom>=180)$s+=10;if($dom>=365)$s+=8;if($p>=700000)$s+=8;if($p>=1200000)$s+=8;if($p>=2000000)$s+=5;if(!empty($r['owner_name']))$s+=5;if(!empty($r['expired_date']))$s+=7;if(strlen((string)$r['listing_description'])>200)$s+=5;if((float)$r['tax_amount']>10000)$s+=5;if((float)$r['assessed_value']>500000)$s+=4;return min(100,$s);}
try{
  $in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))$in=$_POST;
  $key=$in['key']??''; if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
  $rows=$in['rows']??[]; $batch=$in['batch']??('expired_'.date('Ymd_His')); $chunk=(int)($in['chunk_index']??0);
  if(!is_array($rows)||!count($rows)){echo json_encode(['success'=>false,'error'=>'No rows received. Export MLS as CSV or tab-delimited text if this was a true binary Excel file.','chunk_index'=>$chunk]);exit;}
  $payload=[];$bad=[];
  foreach($rows as $i=>$row){
    if(!is_array($row)){ $bad[]=['row'=>$i,'reason'=>'not object']; continue; }
    $sn=getv($row,['Street Number']); $street=getv($row,['Street Name']);
    $addr=getv($row,['Address','Property Address','Street Address','Listing Address','Full Address']);
    if(!$addr && ($sn||$street))$addr=trim($sn.' '.$street);
    $town=getv($row,['City','Town','Municipality']);
    if(!$addr){$bad[]=['row'=>$i,'reason'=>'missing address'];continue;}
    $rec=['import_batch'=>$batch,'source_name'=>'MLS Export','address'=>$addr,'town'=>$town,'status'=>getv($row,['Status','Listing Status']),'list_price'=>n(getv($row,['List Price','Price','Last List Price','Current Price'])),'original_price'=>n(getv($row,['Original List Price','Original Price'])),'expired_date'=>dv(getv($row,['Expiration Date','Expired Date','Off Market Date'])),'list_date'=>dv(getv($row,['Start Marketing Date','List Date','Listing Date'])),'days_on_market'=>si(getv($row,['DOM','Days On Market','CDOM']),0,5000),'bedrooms'=>si(getv($row,['Beds Total','Bedrooms','Beds','BR']),0,99),'bathrooms'=>n(getv($row,['Baths Total','Bathrooms','Baths','BA'])),'sqft'=>n(getv($row,['Sq Ft Total','SQFT Est Heated Above Grade','Sqft','Square Feet','Living Area'])),'year_built'=>yr(getv($row,['Year Built','Yr Built'])),'tax_amount'=>n(getv($row,['Property Tax','Tax','Taxes','Tax Amount','Annual Tax'])),'assessed_value'=>n(getv($row,['Assessed Value','Assessment'])),'listing_description'=>getv($row,['Remarks - Public','Remarks','Public Remarks','Description','Listing Description']),'last_agent'=>getv($row,['List Agent Full Name','Agent','Listing Agent','List Agent']),'office_name'=>getv($row,['List Office Name','Office','Listing Office','Brokerage']),'acres'=>n(getv($row,['Acres'])),'street_name'=>$street,'street_number'=>$sn,'county'=>getv($row,['County']),'list_agent_email'=>getv($row,['List Agent Email']),'list_agent_mlsid'=>getv($row,['List Agent MLSID']),'list_agent_phone'=>getv($row,['List Agent Preferred Phone','Other Phone Number']),'list_office_mlsid'=>getv($row,['List Office MLSID']),'mls_number'=>getv($row,['MLS Number']),'neighborhood'=>getv($row,['Neighborhood']),'mil_rate_base'=>n(getv($row,['Mil Rate Base'])),'mil_rate_total'=>n(getv($row,['Mil Rate Total'])),'owner_name'=>getv($row,['Owner Name']),'parcel_number'=>getv($row,['Parcel Number']),'previous_expiration_date'=>dv(getv($row,['Previous Expiration Date'])),'last_status'=>getv($row,['Last Status']),'property_sub_type'=>getv($row,['Property Sub Type']),'property_type'=>getv($row,['Property Type']),'selling_agent_full_name'=>getv($row,['Selling Agent Full Name']),'selling_agent_mlsid'=>getv($row,['Selling Agent MLSID']),'buyer_office_id'=>getv($row,['Buyer Office ID']),'high_school'=>getv($row,['High School']),'sqft_heated_below_grade'=>n(getv($row,['Sq Ft Est Heated Below Grade'])),'sqft_source'=>getv($row,['Sq Ft Source']),'state'=>getv($row,['State']),'status_change_timestamp'=>dt(getv($row,['Status Change Timestamp','Last Change Timestamp'])),'style'=>getv($row,['Style']),'subdivision'=>getv($row,['Subdivision','Subdivision/Complex']),'branded_virtual_tour_url'=>getv($row,['Branded Virtual Tour URL']),'walk_score'=>n(getv($row,['Walk Score'])),'waterfront_description'=>getv($row,['Waterfront Description']),'water_source'=>getv($row,['Water Source']),'direct_waterfront_yn'=>getv($row,['Direct Waterfront YN']),'zoning'=>getv($row,['Zoning']),'zip_code'=>getv($row,['Zip Code']),'transaction_type'=>getv($row,['Transaction Type']),'unit_number'=>getv($row,['Unit Number']),'raw'=>$row,'data_confidence'=>99,'created_at'=>date('c'),'updated_at'=>date('c')];
    $rec['opportunity_score']=sc($rec);$rec['raw_import_hash']=sha1(strtolower(($rec['mls_number']??'').'|'.$rec['address'].'|'.$rec['town'].'|'.($rec['expired_date']??'')));$rec['jessica_reason']='Trusted MLS export. Mark remains human review gate.';$payload[]=$rec;
  }
  if(!$payload){echo json_encode(['success'=>true,'inserted'=>0,'bad_rows'=>$bad,'message'=>'No valid rows in chunk']);exit;}
  $res=sb('POST','mls_expired_records',$payload);
  if(!$res['ok']){http_response_code(500);echo json_encode(['success'=>false,'error'=>'Supabase insert failed','chunk_index'=>$chunk,'attempted'=>count($payload),'bad_rows'=>$bad,'details'=>$res['body']],JSON_PRETTY_PRINT);exit;}
  echo json_encode(['success'=>true,'chunk_index'=>$chunk,'inserted'=>count($payload),'bad_rows'=>$bad,'batch'=>$batch],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>