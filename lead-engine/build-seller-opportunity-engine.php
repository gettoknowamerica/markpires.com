<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try{
$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
function sb141($m,$ep,$p=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];}
function rows141($t,$q){$r=sb141('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
function ph141($p){$p=preg_replace('/[^0-9]/','',(string)$p);if(strlen($p)===11&&substr($p,0,1)==='1')$p=substr($p,1);return $p;}
function val141($town){if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true))return 1400000;if(in_array($town,['Wilton','Fairfield','Weston'],true))return 950000;if(in_array($town,['Stamford','Norwalk','Trumbull'],true))return 750000;return 650000;}
function intent141($type,$dom,$red){$m=['fsbo'=>95,'make_me_move'=>92,'expired'=>90,'withdrawn'=>86,'cancelled'=>84,'price_reduced'=>80,'manual'=>65];$s=$m[$type]??60;if($dom>=30)$s+=5;if($red>=1)$s+=8;if($red>=2)$s+=6;return min(100,$s);}
$sources=rows141('seller_opportunity_sources','select=*&status=eq.active&order=created_at.desc&limit=1000');
$updated=0;$errors=[];$pushAcq=0;$pushPipe=0;
foreach($sources as $s){
 $id=$s['id'];$type=$s['source_type']??'manual';$town=$s['town']?:'Unknown';$dom=(int)($s['days_on_market']??0);$red=(int)($s['price_reductions']??0);
 $phone=ph141($s['owner_phone']??'');$email=strtolower(trim($s['owner_email']??''));$value=(float)($s['estimated_value']??0);if($value<=0)$value=(float)($s['list_price']??0);if($value<=0)$value=val141($town);
 $last=(float)($s['last_sale_price']??0);$equity=(float)($s['estimated_equity']??0);if($equity<=0&&$last>0)$equity=max(0,$value-$last);if($equity<=0)$equity=$value*.35;$years=(float)($s['years_owned']??0);
 $intent=intent141($type,$dom,$red);$own=$years>=10?90:($years>=5?75:($years>0?55:35));$eq=$equity>=700000?90:($equity>=350000?75:55);
 $contact=0;if($phone)$contact+=45;if($email)$contact+=25;if(!empty($s['owner_name']))$contact+=15;if(!empty($s['property_address']))$contact+=15;
 $dnc=$s['dnc_status']??'unchecked';$real=$s['realtor_status']??'unchecked';$ap=$s['approval_status']??'source_review';
 $call=($phone&&$dnc==='clear'&&$real==='clear'&&in_array($ap,['approved','imported'],true));
 $total=round(($intent*.42)+($own*.18)+($eq*.18)+($contact*.22));if($call)$total=min(100,$total+10);$band=$total>=85?'A':($total>=70?'B':'C');
 $action=$call?'Call today with FSBO/seller-option review approach.':(($phone&&$dnc!=='clear')?'Run DNC check before phone outreach.':((!$phone&&!$email)?'Needs contact enrichment before outreach.':'Review and approve before outreach.'));
 $patch=['seller_intent_score'=>$intent,'ownership_score'=>$own,'equity_score'=>$eq,'contact_score'=>$contact,'total_seller_score'=>$total,'priority_band'=>$band,'estimated_value'=>$value,'estimated_equity'=>$equity,'call_eligible'=>$call,'recommended_action'=>$action,'updated_at'=>date('c')];
 $r=sb141('PATCH','seller_opportunity_sources?id=eq.'.rawurlencode($id),$patch);if($r['ok'])$updated++;else $errors[]=$r['body'];
 if(empty($s['pushed_to_acquisition'])){
  $acq=[['source_table'=>'seller_opportunity_sources','source_id'=>(string)$id,'source_name'=>'V14.1 Seller Opportunity Engine','source_type'=>$type,'owner_name'=>$s['owner_name']??'','phone'=>$phone,'email'=>$email,'property_address'=>$s['property_address']??'','town'=>$town,'state'=>$s['state']??'CT','market'=>'Lower Fairfield County','property_type'=>'seller_source','years_owned'=>$years,'last_sale_price'=>$last,'estimated_value'=>$value,'estimated_equity'=>$equity,'dnc_status'=>$dnc,'dnc_checked'=>$dnc!=='unchecked','dnc_match'=>in_array($dnc,['blocked','listed','do_not_call'],true),'realtor_checked'=>$real!=='unchecked','realtor_match'=>in_array($real,['match','agent','realtor'],true),'consent_status'=>$type==='fsbo'?'seller_public_listing':'unknown','approval_status'=>$ap,'approved_contact'=>$call,'call_eligible'=>$call,'email_eligible'=>($email&&in_array($ap,['approved','imported'],true)),'motivation'=>strtoupper($type).' / active seller source','motivation_score'=>$intent,'contact_score'=>$total,'priority_band'=>$band,'recommended_action'=>$action,'next_step'=>$call?'push_to_call_queue':'enrich_review_approve','raw_payload'=>array_merge($s,$patch),'status'=>$call?'approved':'review','created_at'=>date('c'),'updated_at'=>date('c')]];
  $ar=sb141('POST','contact_acquisition_candidates',$acq);if($ar['ok']){$pushAcq++;sb141('PATCH','seller_opportunity_sources?id=eq.'.rawurlencode($id),['pushed_to_acquisition'=>true,'updated_at'=>date('c')]);}
 }
 if($call&&empty($s['pushed_to_pipeline'])){
  $commission=$value*.025;$prob=35;$pipe=[['pipeline_date'=>date('Y-m-d'),'source_table'=>'seller_opportunity_sources','source_id'=>(string)$id,'opportunity_type'=>'seller','name'=>$s['owner_name']??'FSBO Seller','phone'=>$phone,'email'=>$email,'address'=>$s['property_address']??'','town'=>$town,'pipeline_stage'=>'call_queue','stage_score'=>40,'priority_score'=>$total,'probability'=>$prob,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*($prob/100),2),'next_step'=>$action,'next_followup_at'=>date('c',strtotime('+1 day')),'last_activity_at'=>date('c'),'notes'=>'V14.1 seller source: '.$type,'raw_payload'=>array_merge($s,$patch),'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')]];
  $pr=sb141('POST','jessica_opportunity_pipeline',$pipe);if($pr['ok']){$pushPipe++;sb141('PATCH','seller_opportunity_sources?id=eq.'.rawurlencode($id),['pushed_to_pipeline'=>true,'updated_at'=>date('c')]);}
 }
}
$all=rows141('seller_opportunity_sources','select=*&status=eq.active&order=total_seller_score.desc,created_at.desc&limit=1000');
$c=['fsbo'=>0,'expired'=>0,'withdrawn'=>0,'price'=>0,'a'=>0,'b'=>0,'call'=>0];$towns=[];
foreach($all as $o){$t=$o['source_type']??'';if($t==='fsbo')$c['fsbo']++;if($t==='expired')$c['expired']++;if($t==='withdrawn')$c['withdrawn']++;if($t==='price_reduced')$c['price']++;if(($o['priority_band']??'')==='A')$c['a']++;if(($o['priority_band']??'')==='B')$c['b']++;if(!empty($o['call_eligible']))$c['call']++;$town=$o['town']?:'Unknown';$towns[$town]=($towns[$town]??0)+1;}arsort($towns);
$recs=['Start with FSBO and expired/withdrawn sellers before broad homeowner lists.','No phone/email means contact enrichment, not call queue.','Call eligibility requires phone + DNC clear + realtor clear + approval.'];
$brief="V14.1 SELLER OPPORTUNITY ENGINE\n========================================\n\nTotal Sources: ".count($all)."\nFSBO: {$c['fsbo']}\nExpired: {$c['expired']}\nWithdrawn: {$c['withdrawn']}\nPrice Reduced: {$c['price']}\nA Tier: {$c['a']}\nB Tier: {$c['b']}\nCall Eligible: {$c['call']}\nPushed Acquisition: {$pushAcq}\nPushed Pipeline: {$pushPipe}\nTop Town: ".(array_key_first($towns)?:'n/a')."\n\nTOP SELLER SOURCES\n----------------------------------------\n";
foreach(array_slice($all,0,15) as $i=>$o){$brief.=($i+1).'. '.(($o['property_address']??'')?:($o['listing_title']??'Seller Source')).' — '.$o['town'].' — '.$o['source_type'].' — Score '.$o['total_seller_score']."\n     Action: ".$o['recommended_action']."\n\n";}
$daily=[['briefing_date'=>date('Y-m-d'),'total_sources'=>count($all),'fsbo_count'=>$c['fsbo'],'expired_count'=>$c['expired'],'withdrawn_count'=>$c['withdrawn'],'price_reduced_count'=>$c['price'],'a_tier'=>$c['a'],'b_tier'=>$c['b'],'call_eligible'=>$c['call'],'pushed_to_acquisition'=>$pushAcq,'top_town'=>array_key_first($towns)?:'','top_opportunities'=>array_slice($all,0,25),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')]];
$dr=sb141('POST','seller_opportunity_briefings',$daily);if(!$dr['ok']&&str_contains($dr['body'],'duplicate key'))sb141('PATCH','seller_opportunity_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
echo json_encode(['success'=>empty($errors),'sources_scored'=>count($sources),'updated'=>$updated,'pushed_to_acquisition'=>$pushAcq,'pushed_to_pipeline'=>$pushPipe,'call_eligible'=>$c['call'],'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>