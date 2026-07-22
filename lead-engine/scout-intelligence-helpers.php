<?php
/**
 * Goliath V93.2.2 Scout Intelligence Helpers
 * Fixes mapping for the real internal_crm_contacts schema:
 * phone_1, phone_2, email_1, email_2, phone_confidence, email_confidence, priority_score.
 */
if(!function_exists('scout_tbl')){
function scout_tbl($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('scout_col')){
function scout_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
}
if(!function_exists('scout_uid')){
function scout_uid($p='scout'){if(function_exists('gdb_uid')) return gdb_uid($p); return $p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
}
if(!function_exists('scout_json')){
function scout_json($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
if(!function_exists('scout_insert')){
function scout_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(scout_col($t,$k))$safe[$k]=$v;} if(!$safe)return null; return gdb_insert($t,$safe);}
}
if(!function_exists('scout_update')){
function scout_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(scout_col($t,$k))$safe[$k]=$v;} if(!$safe||!$id)return false; gdb_update($t,$safe,'id=:id',['id'=>(int)$id]); return true;}
}
if(!function_exists('scout_event')){
function scout_event($missionId,$dossierId,$contactId,$type,$title,$details='',$meta=[]){
  try{return scout_insert('scout_intel_events',['event_uid'=>scout_uid('se'),'mission_id'=>$missionId,'dossier_id'=>$dossierId,'contact_id'=>$contactId,'event_type'=>$type,'title'=>$title,'details'=>$details,'metadata'=>scout_json($meta),'created_at'=>gdb_now()]);}catch(Throwable $e){return null;}
}}
if(!function_exists('scout_clean')){
function scout_clean($v){if(is_array($v))return trim(strip_tags(implode(', ',array_filter(array_map('strval',$v))))); return trim(strip_tags((string)$v));}
}
if(!function_exists('scout_contact_value')){
function scout_contact_value($c,$keys){foreach($keys as $k){if(isset($c[$k]) && trim((string)$c[$k])!=='')return scout_clean($c[$k]);} return '';}
}
if(!function_exists('scout_phone_values')){
function scout_phone_values($c){
  $vals=[];
  foreach(['phone_1','phone_2','phone','existing_phone','mobile','cell'] as $k){
    if(isset($c[$k]) && trim((string)$c[$k])!=='') $vals[]=scout_clean($c[$k]);
  }
  return array_values(array_unique($vals));
}}
if(!function_exists('scout_email_values')){
function scout_email_values($c){
  $vals=[];
  foreach(['email_1','email_2','email','existing_email'] as $k){
    if(isset($c[$k]) && trim((string)$c[$k])!=='') $vals[]=strtolower(scout_clean($c[$k]));
  }
  return array_values(array_unique($vals));
}}
if(!function_exists('scout_int_val')){
function scout_int_val($v,$fallback=0){
  if($v===null || $v==='') return $fallback;
  $digits=preg_replace('/[^0-9]/','',(string)$v);
  return $digits===''?$fallback:(int)$digits;
}}
if(!function_exists('scout_blog_match')){
function scout_blog_match($town,$type='seller'){
  $town=trim((string)$town); $slug=$town?strtolower(trim(preg_replace('/[^a-z0-9]+/','-', $town),'-')):'fairfield-county';
  if(stripos($type,'buyer')!==false) return "/blog/{$slug}-ct-living-guide.html";
  if(stripos($type,'expired')!==false) return "/blog/why-homes-expire-and-how-to-relaunch.html";
  return "/blog/{$slug}-home-selling-guide.html";
}}
if(!function_exists('scout_confidence')){
function scout_confidence($c){
  $phones=scout_phone_values($c);
  $emails=scout_email_values($c);

  $phoneConf=scout_int_val($c['phone_confidence']??'',null);
  $emailConf=scout_int_val($c['email_confidence']??'',null);
  $priority=scout_int_val($c['priority_score']??'',0);

  $contact=0;
  if(count($phones)){
    $contact=max($contact, $phoneConf!==null ? min(100,$phoneConf) : 75);
  }
  if(count($emails)){
    $contact=max($contact, $emailConf!==null ? min(100,$emailConf) : 75);
  }
  if(count($phones) && count($emails)) $contact=max($contact,85);

  $property=0;
  if(scout_contact_value($c,['property_address','address']))$property+=55;
  if(scout_contact_value($c,['town','city']))$property+=20;
  if(scout_contact_value($c,['owner_name','name','full_name']))$property+=25;

  $market=20;
  if(scout_contact_value($c,['estimated_value','last_list_price','price','budget','listing_history','mls_history','expired_history']))$market+=20;
  if($priority>0)$market=max($market,min(100,40+$priority));

  $overall=min(100,(int)round(($contact+$property+$market)/3));
  if($contact>=70 && $property>=70) $overall=max($overall,75);

  return ['overall'=>$overall,'contact'=>min(100,$contact),'property'=>min(100,$property),'market'=>min(100,$market),'priority_score'=>$priority];
}}
if(!function_exists('scout_status_for')){
function scout_status_for($conf,$phone,$email,$address){
  if(($phone || $email) && $address) return ['ready_for_mark','ready_for_mark','Ready for Mark: contact route and property context available.'];
  if($phone || $email) return ['needs_property_review','not_ready','Contact route found, property context needs review.'];
  if($address) return ['needs_contact_research','not_ready','Property context found, contact info still needs research.'];
  return ['needs_research','not_ready','Insufficient owner/property/contact info.'];
}}
if(!function_exists('scout_make_dossier_from_contact')){
function scout_make_dossier_from_contact($contact,$missionId=null,$source='internal_crm'){
  $contactId=(int)($contact['id']??0);
  $owner=scout_contact_value($contact,['owner_name','name','full_name']);
  $address=scout_contact_value($contact,['property_address','address']);
  $town=scout_contact_value($contact,['town','city']);
  $phones=scout_phone_values($contact);
  $emails=scout_email_values($contact);
  $phone=implode(' | ',$phones);
  $email=implode(' | ',$emails);
  $type=scout_contact_value($contact,['lead_type','type','source_type','contact_status']);
  $conf=scout_confidence($contact);
  [$research,$handoff,$next]=scout_status_for($conf,$phone,$email,$address);

  $call="Open warm and specific. Reference the property/town context already known. Ask if they would like Mark to review the current market and whether selling or buying is still on their radar.";
  if(stripos($type,'valuation')!==false)$call="Open with the valuation request. Confirm property details first, then explain Mark will compare real local sales instead of relying on an automated estimate.";
  if(stripos($type,'buyer')!==false)$call="Open with their target town and buying timeline. Ask what matters most: schools, commute, space, lifestyle, budget, or timing.";
  if(stripos($type,'expired')!==false || stripos($source,'expired')!==false)$call="Open around the prior listing. Be respectful: ask if they still have any interest in selling and mention Mark can review what may have changed in the local market since the last attempt.";

  $emailStrategy="Human-touch email from Mark. Keep it short. Acknowledge the property/town context and include a matching local guide. Do not sound automated.";
  $recommended=scout_blog_match($town,$type);
  $evidenceBits=[];
  if($phone)$evidenceBits[]="Phone(s) from CRM/upload: ".$phone;
  if($email)$evidenceBits[]="Email(s) from CRM/upload: ".$email;
  if(isset($contact['phone_confidence']))$evidenceBits[]="Phone confidence: ".$contact['phone_confidence'];
  if(isset($contact['email_confidence']))$evidenceBits[]="Email confidence: ".$contact['email_confidence'];
  if(isset($contact['priority_score']))$evidenceBits[]="Priority score: ".$contact['priority_score'];
  if(!empty($contact['evidence']))$evidenceBits[]="Source evidence: ".scout_clean($contact['evidence']);
  $evidence="Source: {$source}. ".implode(' | ',$evidenceBits)." | No external facts invented by this cycle.";

  $existing=null;
  if($contactId) $existing=gdb_one("SELECT id FROM scout_intel_dossiers WHERE contact_id=? ORDER BY id DESC LIMIT 1",[$contactId]);

  $publicNotes=scout_contact_value($contact,['notes','message','evidence']);
  if(!$publicNotes && !empty($contact['raw_data'])) $publicNotes=scout_clean($contact['raw_data']);

  $row=[
    'dossier_uid'=>scout_uid('dossier'),'mission_id'=>$missionId,'contact_id'=>$contactId?:null,'lead_uid'=>scout_contact_value($contact,['lead_uid','contact_uid']),
    'owner_name'=>$owner,'property_address'=>$address,'mailing_address'=>scout_contact_value($contact,['mailing_address']),
    'town'=>$town,'state'=>scout_contact_value($contact,['state']),'zip'=>scout_contact_value($contact,['zip','postal_code']),
    'phone'=>$phone,'email'=>$email,'source_label'=>$source,'research_status'=>$research,'handoff_status'=>$handoff,
    'confidence_score'=>$conf['overall'],'contact_confidence'=>$conf['contact'],'property_confidence'=>$conf['property'],'market_confidence'=>$conf['market'],
    'listing_history'=>scout_contact_value($contact,['listing_history','mls_history','expired_history']),
    'nearby_sales'=>scout_contact_value($contact,['nearby_sales','comps','sales']),
    'public_notes'=>$publicNotes,
    'call_strategy'=>$call,'email_strategy'=>$emailStrategy,'recommended_blog'=>$recommended,'next_action'=>$next,
    'evidence_log'=>$evidence,'raw_json'=>scout_json($contact),'completed_at'=>($handoff==='ready_for_mark'?gdb_now():null),
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ];

  if($existing){
    $id=(int)$existing['id'];
    unset($row['dossier_uid'],$row['created_at']);
    scout_update('scout_intel_dossiers',$id,$row);
  } else {
    $id=scout_insert('scout_intel_dossiers',$row);
  }

  scout_event($missionId,$id,$contactId,'dossier_built','Scout dossier built',$next,['confidence'=>$conf,'source'=>$source,'phones'=>$phones,'emails'=>$emails]);

  return [
    'id'=>$id,
    'research_status'=>$research,
    'handoff_status'=>$handoff,
    'confidence'=>$conf,
    'owner_name'=>$owner,
    'property_address'=>$address,
    'phone'=>$phone,
    'email'=>$email,
    'next_action'=>$next
  ];
}}
?>