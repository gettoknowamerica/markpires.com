<?php
/**
 * V13.0.1 Listing Intelligence Center — 500 + PGRST102 Key Fix
 * Upload over: /public_html/lead-engine/build-listing-intelligence.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb1301($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows1301($table,$query){ $r=sb1301('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function town_value1301($town){
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)) return 1400000;
    if(in_array($town,['Wilton','Fairfield'],true)) return 950000;
    if(in_array($town,['Stamford','Norwalk'],true)) return 750000;
    return 700000;
  }
  function motivation1301($text,$leadType=''){
    $t=strtolower((string)$text.' '.(string)$leadType);
    if(str_contains($t,'downsize')) return ['Downsizing',92];
    if(str_contains($t,'relocat')||str_contains($t,'move out of state')) return ['Relocation / Out of State Move',90];
    if(str_contains($t,'retire')) return ['Retirement Planning',88];
    if(str_contains($t,'estate')) return ['Estate / Family Transition',84];
    if(str_contains($t,'seller')||str_contains($t,'home value')||str_contains($t,'valuation')) return ['Equity / Home Value Curiosity',76];
    if(str_contains($t,'builder')||str_contains($t,'developer')) return ['Builder / Development Signal',72];
    return ['Market Timing / Seller Window',65];
  }
  function tier1301($score){
    if($score>=92) return 'A+';
    if($score>=85) return 'A';
    if($score>=72) return 'B';
    return 'C';
  }
  function opp1301($overrides){
    $base=[
      'opportunity_date'=>date('Y-m-d'),
      'source_table'=>'',
      'source_id'=>'',
      'name'=>'',
      'phone'=>'',
      'email'=>'',
      'address'=>'',
      'town'=>'',
      'market'=>'',
      'lead_type'=>'seller',
      'likely_motivation'=>'',
      'seller_motivation_score'=>0,
      'market_heat_score'=>0,
      'contact_quality_score'=>0,
      'conversation_score'=>0,
      'appointment_score'=>0,
      'equity_score'=>0,
      'listing_probability_score'=>0,
      'priority_tier'=>'C',
      'estimated_sale_price'=>0,
      'estimated_commission'=>0,
      'expected_value'=>0,
      'recommended_approach'=>'',
      'why_this_matters'=>'',
      'next_best_action'=>'',
      'call_eligible'=>false,
      'compliance_status'=>'review',
      'status'=>'active',
      'raw_payload'=>[],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    return array_merge($base,$overrides);
  }

  $today=date('Y-m-d');
  $contacts=rows1301('approved_contact_pool','select=*&status=eq.active&order=contact_score.desc,created_at.desc&limit=1000');
  $queue=rows1301('daily_action_queue','select=*&status=eq.open&order=priority_score.desc,created_at.desc&limit=500');
  $heat=rows1301('market_heat_snapshots','select=*&order=total_heat.desc,created_at.desc&limit=200');
  $appts=rows1301('appointment_intelligence_queue','select=*&appointment_status=eq.pending&order=appointment_priority.desc,created_at.desc&limit=200');
  $events=rows1301('conversation_learning_events','select=*&order=appointment_intent_score.desc,created_at.desc&limit=500');

  $heatByTown=[];
  foreach($heat as $h){ $heatByTown[$h['town']??'']=(int)($h['total_heat']??0); }

  $apptByPhone=[]; foreach($appts as $a){ if(!empty($a['caller_phone'])) $apptByPhone[$a['caller_phone']]=$a; }
  $eventByPhone=[]; foreach($events as $e){ if(!empty($e['caller_phone'])) $eventByPhone[$e['caller_phone']]=$e; }

  $opps=[]; $seen=[];

  foreach($contacts as $c){
    $phone=$c['phone']??''; $email=$c['email']??'';
    $key=$phone ? 'p:'.$phone : ($email ? 'e:'.$email : 'x:'.($c['id']??md5(json_encode($c))));
    if(isset($seen[$key])) continue; $seen[$key]=true;

    $town=$c['town'] ?: 'Unknown';
    $text=($c['motivation']??'').' '.($c['notes']??'').' '.($c['lead_type']??'').' '.($c['source_type']??'');
    [$motivation,$motScore]=motivation1301($text,$c['lead_type']??'');

    $marketHeat=$heatByTown[$town]??50;
    $contactScore=(int)($c['contact_score']??0);
    $appt=$apptByPhone[$phone]??null;
    $event=$eventByPhone[$phone]??null;
    $apptScore=$appt ? (int)($appt['appointment_priority']??80) : 0;
    $convScore=$event ? max((int)($event['appointment_intent_score']??0),(int)($event['motivation_score']??0)) : 0;

    $value=(float)($c['estimated_value']??0);
    if($value<=0) $value=town_value1301($town);
    $equityScore=$value>=1200000?90:($value>=800000?78:65);

    $final=round(($marketHeat*.20)+($contactScore*.25)+($motScore*.20)+($convScore*.15)+($apptScore*.15)+($equityScore*.05));
    $final=max(0,min(100,$final));
    $tier=tier1301($final);
    $commission=$value*.025;
    $expected=$commission*($final/100);

    $why=[
      "Market heat: {$marketHeat}",
      "Contact quality: {$contactScore}",
      "Motivation: {$motivation} ({$motScore})",
      "Estimated sale price: $".number_format($value,0)
    ];
    if($convScore>0)$why[]="Conversation score: {$convScore}";
    if($apptScore>0)$why[]="Appointment/follow-up score: {$apptScore}";

    $callEligible=!empty($c['call_eligible']);
    $next=$callEligible ? 'Call today using seller market-position review approach.' : 'Use for ads/content/review until contact is approved.';
    if($apptScore>0) $next='Prioritize appointment/follow-up with Mark.';

    $opps[]=opp1301([
      'source_table'=>'approved_contact_pool',
      'source_id'=>(string)($c['id']??''),
      'name'=>$c['name']??'',
      'phone'=>$phone,
      'email'=>$email,
      'address'=>$c['address']??'',
      'town'=>$town,
      'market'=>$c['market']??'',
      'lead_type'=>$c['lead_type']??'seller',
      'likely_motivation'=>$motivation,
      'seller_motivation_score'=>$motScore,
      'market_heat_score'=>$marketHeat,
      'contact_quality_score'=>$contactScore,
      'conversation_score'=>$convScore,
      'appointment_score'=>$apptScore,
      'equity_score'=>$equityScore,
      'listing_probability_score'=>$final,
      'priority_tier'=>$tier,
      'estimated_sale_price'=>$value,
      'estimated_commission'=>round($commission,2),
      'expected_value'=>round($expected,2),
      'recommended_approach'=>$callEligible?'Seller equity / market-position conversation':'Content/ad/review-first approach',
      'why_this_matters'=>implode(' | ',$why),
      'next_best_action'=>$next,
      'call_eligible'=>$callEligible,
      'compliance_status'=>($c['approval_status']??'review').' / '.($c['dnc_status']??'unchecked').' / '.($c['consent_status']??'unknown'),
      'raw_payload'=>['contact'=>$c,'appointment'=>$appt,'conversation'=>$event]
    ]);
  }

  foreach($queue as $q){
    if(in_array(($q['queue_type']??''),['watch','ad_launch','content','review'],true)){
      $town=$q['town'] ?: 'Unknown';
      $heatScore=$heatByTown[$town]??60;
      $score=max((int)($q['priority_score']??0),$heatScore);
      $value=town_value1301($town);
      $final=min(88,round(($score*.55)+($heatScore*.45)));
      $opps[]=opp1301([
        'source_table'=>'daily_action_queue',
        'source_id'=>(string)($q['id']??''),
        'name'=>$q['name']??($q['action_title']??'Opportunity'),
        'phone'=>$q['phone']??'',
        'email'=>$q['email']??'',
        'address'=>'',
        'town'=>$town,
        'market'=>$q['market']??'',
        'lead_type'=>$q['lead_type']??'seller',
        'likely_motivation'=>'Market Signal / Targeting Opportunity',
        'seller_motivation_score'=>65,
        'market_heat_score'=>$heatScore,
        'contact_quality_score'=>0,
        'conversation_score'=>0,
        'appointment_score'=>0,
        'equity_score'=>70,
        'listing_probability_score'=>$final,
        'priority_tier'=>tier1301($final),
        'estimated_sale_price'=>$value,
        'estimated_commission'=>round($value*.025,2),
        'expected_value'=>round(($value*.025)*($final/100),2),
        'recommended_approach'=>'Use as campaign/content/listing watch signal. Do not call without approved contact.',
        'why_this_matters'=>'Queue signal: '.($q['queue_type']??'').' | Market heat: '.$heatScore.' | Priority score: '.($q['priority_score']??0),
        'next_best_action'=>'Use this opportunity to guide ads, content, and approved contact review.',
        'call_eligible'=>false,
        'compliance_status'=>'research_only',
        'raw_payload'=>$q
      ]);
    }
  }

  usort($opps,function($a,$b){
    // Real callable people outrank campaigns/content/watch signals.
    if(!empty($a['call_eligible']) !== !empty($b['call_eligible'])) return !empty($a['call_eligible']) ? -1 : 1;
    $aPerson = (!empty($a['phone']) || !empty($a['email']) || !empty($a['address']));
    $bPerson = (!empty($b['phone']) || !empty($b['email']) || !empty($b['address']));
    if($aPerson !== $bPerson) return $aPerson ? -1 : 1;
    return $b['listing_probability_score']<=>$a['listing_probability_score'];
  });

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($opps,0,1000),100) as $chunk){
    $r=sb1301('POST','listing_intelligence_opportunities',$chunk);
    if($r['ok']) $inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $counts=['A+'=>0,'A'=>0,'B'=>0,'call'=>0]; $pipeline=0; $commission=0; $towns=[]; $motives=[];
  foreach($opps as $o){
    if(isset($counts[$o['priority_tier']])) $counts[$o['priority_tier']]++;
    if($o['call_eligible']) $counts['call']++;
    $pipeline += (float)$o['estimated_sale_price'];
    $commission += (float)$o['estimated_commission'];
    $towns[$o['town']] = ($towns[$o['town']]??0)+1;
    $motives[$o['likely_motivation']] = ($motives[$o['likely_motivation']]??0)+1;
  }
  arsort($towns); arsort($motives);
  $topMotives=[]; foreach(array_slice($motives,0,8,true) as $k=>$v){ $topMotives[]=['motivation'=>$k,'count'=>$v]; }

  $recs=[];
  if(($counts['call']??0)===0) $recs[]='No listing opportunities are call-eligible yet. Use this center to prioritize ads/content/review until approved contacts enter the system.';
  if(!empty($opps[0])) $recs[]='Top listing intelligence item: '.$opps[0]['name'].' / '.$opps[0]['town'].' — score '.$opps[0]['listing_probability_score'].'.';
  if(!empty(array_key_first($towns))) $recs[]='Highest listing opportunity town: '.array_key_first($towns).'.';
  $recs[]='Focus on the top A/A+ items only. This dashboard is designed to reduce noise, not increase it.';

  $brief="========================================\\n";
  $brief.="V13 LISTING INTELLIGENCE CENTER\\n";
  $brief.="{$today}\\n";
  $brief.="========================================\\n\\n";

  $brief.="EXECUTIVE SNAPSHOT\\n";
  $brief.="----------------------------------------\\n";
  $brief.="Total Listing Opportunities:  ".number_format(count($opps))."\\n";
  $brief.="A+ Tier:                      ".$counts['A+']."\\n";
  $brief.="A Tier:                       ".$counts['A']."\\n";
  $brief.="B Tier:                       ".$counts['B']."\\n";
  $brief.="Call Eligible:                ".$counts['call']."\\n";
  $brief.="Top Town:                     ".(array_key_first($towns)?:'n/a')."\\n";
  $brief.="Estimated Pipeline Value:     $".number_format($pipeline,0)."\\n";
  $brief.="Estimated Commission Value:   $".number_format($commission,0)."\\n\\n";

  $brief.="TOP LISTING OPPORTUNITIES\\n";
  $brief.="----------------------------------------\\n";
  foreach(array_slice($opps,0,15) as $i=>$o){
    $brief.=str_pad(($i+1).".",4)." ".$o['name']."\\n";
    $brief.="     Town:          ".$o['town']."\\n";
    $brief.="     Tier / Score:  ".$o['priority_tier']." / ".$o['listing_probability_score']."\\n";
    $brief.="     Motivation:    ".$o['likely_motivation']."\\n";
    $brief.="     Commission:    $".number_format((float)$o['estimated_commission'],0)."\\n";
    $brief.="     Call Eligible: ".(!empty($o['call_eligible'])?'YES':'NO')."\\n";
    $brief.="     Next Action:   ".$o['next_best_action']."\\n\\n";
  }

  $brief.="TOP SELLER MOTIVATIONS\\n";
  $brief.="----------------------------------------\\n";
  foreach(array_slice($topMotives,0,8) as $m){
    $brief.="• ".$m['motivation']." — ".$m['count']."\\n";
  }

  $brief.="\\nJESSICA RECOMMENDS\\n";
  $brief.="----------------------------------------\\n";
  foreach($recs as $i=>$r){
    $brief.=($i+1).". ".$r."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>$today,
    'total_opportunities'=>count($opps),
    'a_plus'=>$counts['A+'],
    'a_tier'=>$counts['A'],
    'b_tier'=>$counts['B'],
    'call_eligible'=>$counts['call'],
    'estimated_pipeline_value'=>round($pipeline,2),
    'estimated_commission_value'=>round($commission,2),
    'top_town'=>array_key_first($towns)?:'',
    'top_opportunities'=>array_slice($opps,0,25),
    'top_motivations'=>$topMotives,
    'recommendations'=>$recs,
    'briefing_text'=>$brief,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $dr=sb1301('POST','listing_intelligence_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb1301('PATCH','listing_intelligence_briefings?briefing_date=eq.'.rawurlencode($today),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'total_opportunities'=>count($opps),
    'a_plus'=>$counts['A+'],
    'a_tier'=>$counts['A'],
    'b_tier'=>$counts['B'],
    'call_eligible'=>$counts['call'],
    'top_town'=>array_key_first($towns)?:null,
    'estimated_pipeline_value'=>round($pipeline,2),
    'estimated_commission_value'=>round($commission,2),
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>