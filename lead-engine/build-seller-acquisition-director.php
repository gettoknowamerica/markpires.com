<?php
/**
 * V14.5 Seller Acquisition Director
 * Upload: /public_html/lead-engine/build-seller-acquisition-director.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb145($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>[
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
      ],
      CURLOPT_TIMEOUT=>45
    ]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch);
    $h=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows145($t,$q){$r=sb145('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function phone145($p){$p=preg_replace('/[^0-9]/','',(string)$p);if(strlen($p)===11&&substr($p,0,1)==='1')$p=substr($p,1);return $p;}
  function val145($town,$v=0){
    $v=(float)$v; if($v>0)return $v;
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true))return 1400000;
    if(in_array($town,['Wilton','Fairfield','Weston'],true))return 950000;
    if(in_array($town,['Stamford','Norwalk','Trumbull'],true))return 750000;
    return 650000;
  }
  function market_score145($town){
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true))return 95;
    if(in_array($town,['Wilton','Fairfield','Weston'],true))return 82;
    if(in_array($town,['Stamford','Norwalk'],true))return 72;
    return 60;
  }
  function source_score145($type){
    $type=strtolower((string)$type);
    if(str_contains($type,'fsbo'))return 96;
    if(str_contains($type,'expired'))return 92;
    if(str_contains($type,'withdrawn'))return 88;
    if(str_contains($type,'cancel'))return 85;
    if(str_contains($type,'price'))return 82;
    if(str_contains($type,'valuation'))return 90;
    if(str_contains($type,'voice'))return 86;
    if(str_contains($type,'business'))return 80;
    if(str_contains($type,'listing'))return 78;
    return 60;
  }
  function item145($o){
    $base=[
      'acquisition_date'=>date('Y-m-d'),'source_table'=>'','source_id'=>'','source_type'=>'','source_url'=>'',
      'name'=>'','phone'=>'','email'=>'','address'=>'','town'=>'','market'=>'Lower Fairfield County','opportunity_category'=>'seller',
      'acquisition_stage'=>'review','acquisition_score'=>0,'motivation_score'=>0,'market_score'=>0,'value_score'=>0,'contact_score'=>0,
      'luxury_score'=>0,'readiness_score'=>0,'estimated_sale_price'=>0,'estimated_commission'=>0,'expected_value'=>0,'commission_rate'=>0.025,
      'mark_priority'=>false,'referral_priority'=>false,'auction_priority'=>false,'ready_to_contact'=>false,'needs_enrichment'=>false,'needs_review'=>false,
      'compliance_status'=>'unknown','dnc_status'=>'unchecked','realtor_status'=>'unchecked','recommended_action'=>'','recommended_script'=>'',
      'notes'=>'','raw_payload'=>[],'status'=>'active','pushed_to_pipeline'=>false,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }

  $existing=rows145('seller_acquisition_director','select=source_table,source_id,address&status=eq.active&limit=5000');
  $seen=[];
  foreach($existing as $e){
    if(!empty($e['source_table'])&&!empty($e['source_id']))$seen[$e['source_table'].':'.$e['source_id']]=true;
  }

  $new=[];

  // 1. Seller Opportunity Engine
  $seller=rows145('seller_opportunity_sources','select=*&status=eq.active&order=total_seller_score.desc,created_at.desc&limit=1000');
  foreach($seller as $s){
    $key='seller_opportunity_sources:'.($s['id']??'');
    if(isset($seen[$key]))continue;
    $town=$s['town']??''; $value=val145($town,$s['estimated_value']??($s['list_price']??0)); $commission=$value*.025;
    $phone=phone145($s['owner_phone']??''); $email=strtolower(trim($s['owner_email']??''));
    $mot=(int)($s['seller_intent_score']??source_score145($s['source_type']??'')); $ms=market_score145($town);
    $vs=$value>=1500000?100:($value>=1000000?88:($value>=750000?75:60));
    $cs=($phone?55:0)+($email?25:0)+(!empty($s['owner_name'])?10:0)+(!empty($s['property_address'])?10:0);
    $lux=in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)||$value>=1500000?100:($value>=1000000?75:30);
    $ready=!empty($s['call_eligible']);
    $needsEnrich=empty($phone)&&empty($email);
    $readiness=$ready?100:($needsEnrich?35:60);
    $score=round($mot*.32+$ms*.18+$vs*.18+$cs*.14+$lux*.10+$readiness*.08);
    $mark=$score>=85 || $lux>=90 || in_array($town,['Greenwich','Westport','Darien','New Canaan'],true);
    $stage=$ready?'call':($needsEnrich?'enrich':'review');
    $script='Seller opportunity approach: lead with helpful market-position review, equity awareness, and private valuation guidance.';

    $new[]=item145([
      'source_table'=>'seller_opportunity_sources','source_id'=>(string)($s['id']??''),'source_type'=>$s['source_type']??'seller_source','source_url'=>$s['source_url']??'',
      'name'=>$s['owner_name']??'','phone'=>$phone,'email'=>$email,'address'=>$s['property_address']??'','town'=>$town,
      'acquisition_stage'=>$stage,'acquisition_score'=>$score,'motivation_score'=>$mot,'market_score'=>$ms,'value_score'=>$vs,'contact_score'=>$cs,
      'luxury_score'=>$lux,'readiness_score'=>$readiness,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),
      'expected_value'=>round($commission*($ready?.35:.15),2),'mark_priority'=>$mark,'referral_priority'=>!$mark&&$value<750000,'auction_priority'=>$score>=80&&!$mark,
      'ready_to_contact'=>$ready,'needs_enrichment'=>$needsEnrich,'needs_review'=>!$ready&&!$needsEnrich,'compliance_status'=>$ready?'clear':'needs_review',
      'dnc_status'=>$s['dnc_status']??'unchecked','realtor_status'=>$s['realtor_status']??'unchecked',
      'recommended_action'=>$ready?'CALL: approved seller opportunity.':($needsEnrich?'ENRICH: find phone/email before outreach.':'REVIEW: clear DNC/Realtor/approval.'),
      'recommended_script'=>$script,'notes'=>$s['recommended_action']??'','raw_payload'=>$s
    ]);
  }

  // 2. Contact Enrichment Queue
  $enrich=rows145('contact_enrichment_queue','select=*&status=eq.active&order=priority_score.desc,created_at.desc&limit=1000');
  foreach($enrich as $e){
    $key='contact_enrichment_queue:'.($e['id']??'');
    if(isset($seen[$key]))continue;
    $town=$e['town']??''; $value=val145($town,$e['estimated_value']??0); $commission=$value*.025;
    $phone=phone145($e['enriched_phone']?:($e['current_phone']??'')); $email=strtolower(trim($e['enriched_email']?:($e['current_email']??'')));
    $ready=!empty($e['call_eligible']) || (($e['enrichment_status']??'')==='call_queue');
    $needsEnrich=($e['enrichment_status']??'')==='needs_contact';
    $mot=(int)($e['seller_score']??source_score145($e['source_type']??'')); $ms=market_score145($town);
    $vs=$value>=1500000?100:($value>=1000000?88:($value>=750000?75:60));
    $cs=(int)($e['contact_completeness_score']??(($phone?55:0)+($email?25:0)+(!empty($e['owner_name'])?10:0)+(!empty($e['property_address'])?10:0)));
    $lux=in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)||$value>=1500000?100:($value>=1000000?75:30);
    $readiness=$ready?100:($needsEnrich?35:65);
    $score=max((int)($e['priority_score']??0), round($mot*.30+$ms*.18+$vs*.18+$cs*.18+$lux*.08+$readiness*.08));
    $mark=$score>=85 || $lux>=90 || in_array($town,['Greenwich','Westport','Darien','New Canaan'],true);
    $stage=$ready?'call':($needsEnrich?'enrich':'review');

    $new[]=item145([
      'source_table'=>'contact_enrichment_queue','source_id'=>(string)($e['id']??''),'source_type'=>$e['source_type']??'enrichment','source_url'=>$e['source_url']??'',
      'name'=>$e['owner_name']??'','phone'=>$phone,'email'=>$email,'address'=>$e['property_address']??'','town'=>$town,
      'acquisition_stage'=>$stage,'acquisition_score'=>$score,'motivation_score'=>$mot,'market_score'=>$ms,'value_score'=>$vs,'contact_score'=>$cs,
      'luxury_score'=>$lux,'readiness_score'=>$readiness,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),
      'expected_value'=>round($commission*($ready?.35:.12),2),'mark_priority'=>$mark,'referral_priority'=>!$mark&&$value<750000,'auction_priority'=>$score>=80&&!$mark,
      'ready_to_contact'=>$ready,'needs_enrichment'=>$needsEnrich,'needs_review'=>!$ready&&!$needsEnrich,'compliance_status'=>$ready?'clear':'needs_review',
      'dnc_status'=>$e['dnc_status']??'unchecked','realtor_status'=>$e['realtor_status']??'unchecked',
      'recommended_action'=>$ready?'CALL: enriched and approved opportunity.':($needsEnrich?'ENRICH: complete contact data.':'REVIEW: finish compliance and approval.'),
      'recommended_script'=>'Use seller equity / timing review approach.','notes'=>$e['recommended_action']??'','raw_payload'=>$e
    ]);
  }

  // 3. Listing Intelligence
  $listings=rows145('listing_intelligence_opportunities','select=*&status=eq.active&order=call_eligible.desc,listing_probability_score.desc&limit=500');
  foreach($listings as $l){
    $key='listing_intelligence_opportunities:'.($l['id']??'');
    if(isset($seen[$key]))continue;
    $town=$l['town']??''; $value=val145($town,$l['estimated_sale_price']??0); $commission=$value*.025;
    $phone=phone145($l['phone']??''); $email=strtolower(trim($l['email']??''));
    $ready=!empty($l['call_eligible']); $needsEnrich=empty($phone)&&empty($email);
    $mot=(int)($l['listing_probability_score']??source_score145('listing')); $ms=market_score145($town);
    $vs=$value>=1500000?100:($value>=1000000?88:($value>=750000?75:60));
    $cs=($phone?55:0)+($email?25:0)+(!empty($l['name'])?10:0)+(!empty($l['address'])?10:0);
    $lux=in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)||$value>=1500000?100:($value>=1000000?75:30);
    $readiness=$ready?100:($needsEnrich?35:60);
    $score=round($mot*.34+$ms*.18+$vs*.18+$cs*.12+$lux*.10+$readiness*.08);
    $mark=$score>=85 || $lux>=90 || in_array($town,['Greenwich','Westport','Darien','New Canaan'],true);

    $new[]=item145([
      'source_table'=>'listing_intelligence_opportunities','source_id'=>(string)($l['id']??''),'source_type'=>'listing_intelligence',
      'name'=>$l['name']??'','phone'=>$phone,'email'=>$email,'address'=>$l['address']??'','town'=>$town,
      'acquisition_stage'=>$ready?'call':($needsEnrich?'enrich':'review'),'acquisition_score'=>$score,'motivation_score'=>$mot,'market_score'=>$ms,'value_score'=>$vs,'contact_score'=>$cs,
      'luxury_score'=>$lux,'readiness_score'=>$readiness,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*($ready?.30:.10),2),
      'mark_priority'=>$mark,'referral_priority'=>!$mark&&$value<750000,'auction_priority'=>$score>=80&&!$mark,'ready_to_contact'=>$ready,'needs_enrichment'=>$needsEnrich,'needs_review'=>!$ready&&!$needsEnrich,
      'compliance_status'=>$ready?'clear':'needs_review','recommended_action'=>$l['next_best_action']??($ready?'CALL: listing intelligence opportunity.':'REVIEW / ENRICH before contact.'),
      'recommended_script'=>'Listing intelligence approach: ask if they want a private market-position review.','notes'=>$l['why_this_matters']??'','raw_payload'=>$l
    ]);
  }

  // 4. Voice / business seller calls
  $voice=rows145('voice_intelligence_events','select=*&lead_related=eq.true&order=lead_score.desc,created_at.desc&limit=500');
  foreach($voice as $v){
    $key='voice_intelligence_events:'.($v['id']??'');
    if(isset($seen[$key]))continue;
    $town=$v['town']??''; $value=val145($town,0); $commission=$value*.025;
    $phone=phone145($v['caller_phone']??''); $email=strtolower(trim($v['caller_email']??''));
    $ready=$phone && !empty($v['callback_needed']); $mot=(int)($v['lead_score']??70); $ms=market_score145($town);
    $vs=$value>=1500000?100:($value>=1000000?88:($value>=750000?75:60));
    $cs=($phone?60:0)+($email?25:0)+(!empty($v['caller_name'])?15:0);
    $lux=in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)?90:40;
    $readiness=$ready?100:70;
    $score=round($mot*.42+$ms*.15+$vs*.12+$cs*.16+$lux*.05+$readiness*.10);
    $mark=true;
    $new[]=item145([
      'source_table'=>'voice_intelligence_events','source_id'=>(string)($v['id']??''),'source_type'=>'voice_lead',
      'name'=>$v['caller_name']??'','phone'=>$phone,'email'=>$email,'address'=>$v['address']??'','town'=>$town,
      'acquisition_stage'=>$ready?'call':'review','acquisition_score'=>$score,'motivation_score'=>$mot,'market_score'=>$ms,'value_score'=>$vs,'contact_score'=>$cs,
      'luxury_score'=>$lux,'readiness_score'=>$readiness,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*.35,2),
      'mark_priority'=>$mark,'referral_priority'=>false,'auction_priority'=>false,'ready_to_contact'=>$ready,'needs_enrichment'=>false,'needs_review'=>!$ready,
      'compliance_status'=>'inbound_or_callback','recommended_action'=>$ready?'CALL BACK: voice lead requested follow-up.':'REVIEW: voice lead needs qualification.',
      'recommended_script'=>'Callback approach: reference their inquiry and offer a private consultation.','notes'=>$v['summary']??'','raw_payload'=>$v
    ]);
  }

  // 5. Website valuation / leads
  $leads=rows145('leads','select=*&order=created_at.desc&limit=500');
  foreach($leads as $lead){
    $type=strtolower($lead['type']??$lead['lead_type']??'');
    if($type && !str_contains($type,'valuation') && !str_contains($type,'seller') && !str_contains($type,'home')) continue;
    $key='leads:'.($lead['id']??'');
    if(isset($seen[$key]))continue;
    $town=$lead['town']??''; $value=val145($town,$lead['estimated_value']??($lead['value']??0)); $commission=$value*.025;
    $phone=phone145($lead['phone']??''); $email=strtolower(trim($lead['email']??''));
    $ready=($phone||$email); $mot=(int)($lead['lead_score']??85); $ms=market_score145($town);
    $vs=$value>=1500000?100:($value>=1000000?88:($value>=750000?75:60));
    $cs=($phone?55:0)+($email?30:0)+(!empty($lead['name'])?15:0);
    $lux=in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)||$value>=1500000?100:60;
    $readiness=$ready?90:40;
    $score=round($mot*.38+$ms*.16+$vs*.15+$cs*.16+$lux*.07+$readiness*.08);
    $mark=$score>=80 || $lux>=90;
    $new[]=item145([
      'source_table'=>'leads','source_id'=>(string)($lead['id']??''),'source_type'=>'valuation_lead',
      'name'=>$lead['name']??'','phone'=>$phone,'email'=>$email,'address'=>$lead['address']??'','town'=>$town,
      'acquisition_stage'=>$ready?'call':'enrich','acquisition_score'=>$score,'motivation_score'=>$mot,'market_score'=>$ms,'value_score'=>$vs,'contact_score'=>$cs,
      'luxury_score'=>$lux,'readiness_score'=>$readiness,'estimated_sale_price'=>$value,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*.35,2),
      'mark_priority'=>$mark,'referral_priority'=>!$mark&&$value<750000,'auction_priority'=>false,'ready_to_contact'=>$ready,'needs_enrichment'=>!$ready,'needs_review'=>false,
      'compliance_status'=>'inbound','recommended_action'=>$ready?'CALL: valuation lead requested help.':'ENRICH / REVIEW valuation lead.',
      'recommended_script'=>'Inbound valuation approach: thank them and offer a private CMA review.','notes'=>$lead['message']??'','raw_payload'=>$lead
    ]);
  }

  usort($new,function($a,$b){return $b['acquisition_score']<=>$a['acquisition_score'];});
  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,1200),100) as $chunk){
    $r=sb145('POST','seller_acquisition_director',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows145('seller_acquisition_director','select=*&status=eq.active&order=acquisition_score.desc,created_at.desc&limit=1500');
  $counts=['ready'=>0,'enrich'=>0,'review'=>0,'mark'=>0,'luxury'=>0]; $pipeline=0; $commissionTotal=0; $expected=0; $towns=[];
  foreach($all as $o){
    if(!empty($o['ready_to_contact']))$counts['ready']++;
    if(!empty($o['needs_enrichment']))$counts['enrich']++;
    if(!empty($o['needs_review']))$counts['review']++;
    if(!empty($o['mark_priority']))$counts['mark']++;
    if((int)($o['luxury_score']??0)>=90)$counts['luxury']++;
    $pipeline+=(float)($o['estimated_sale_price']??0);
    $commissionTotal+=(float)($o['estimated_commission']??0);
    $expected+=(float)($o['expected_value']??0);
    $town=$o['town']?:'Unknown'; $towns[$town]=($towns[$town]??0)+1;
  }
  arsort($towns);

  $recs=[
    'Work ready-to-contact Mark Priority sellers before enrichment tasks.',
    'Enrich A/B seller sources before buying broad owner lists.',
    'Run DNC + Realtor Exclusion before any outbound calling.',
    'Use 2.5% commission estimate for realistic pipeline forecasting.'
  ];

  $brief="V14.5 SELLER ACQUISITION DIRECTOR\\n========================================\\n\\n";
  $brief.="Total Opportunities:       ".count($all)."\\n";
  $brief.="Ready To Contact:          ".$counts['ready']."\\n";
  $brief.="Needs Enrichment:          ".$counts['enrich']."\\n";
  $brief.="Needs Review:              ".$counts['review']."\\n";
  $brief.="Mark Priority:             ".$counts['mark']."\\n";
  $brief.="Luxury Opportunities:      ".$counts['luxury']."\\n";
  $brief.="Estimated Pipeline Value:  $".number_format($pipeline,0)."\\n";
  $brief.="Estimated Commission 2.5%: $".number_format($commissionTotal,0)."\\n";
  $brief.="Expected Value:            $".number_format($expected,0)."\\n";
  $brief.="Top Town:                  ".(array_key_first($towns)?:'n/a')."\\n\\n";
  $brief.="TOP SELLER OPPORTUNITIES\\n----------------------------------------\\n";
  foreach(array_slice($all,0,25) as $i=>$o){
    $brief.=($i+1).". ".(($o['address']??'')?:($o['name']??'Seller'))." — ".$o['town']." — ".$o['source_type']." — Score ".$o['acquisition_score']." — Commission $".number_format((float)$o['estimated_commission'],0)."\\n";
    $brief.="     Action: ".$o['recommended_action']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_opportunities'=>count($all),'ready_to_contact'=>$counts['ready'],'needs_enrichment'=>$counts['enrich'],
    'needs_review'=>$counts['review'],'mark_priority_count'=>$counts['mark'],'luxury_count'=>$counts['luxury'],
    'estimated_pipeline_value'=>round($pipeline,2),'estimated_commission_value'=>round($commissionTotal,2),'expected_value'=>round($expected,2),
    'top_town'=>array_key_first($towns)?:'','top_opportunities'=>array_slice($all,0,30),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb145('POST','seller_acquisition_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb145('PATCH','seller_acquisition_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'new_created'=>count($new),'total_opportunities'=>count($all),'ready_to_contact'=>$counts['ready'],'mark_priority'=>$counts['mark'],'estimated_commission_value'=>round($commissionTotal,2),'briefing'=>$brief,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>