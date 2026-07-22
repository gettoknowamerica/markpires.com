<?php
/**
 * V13.2 Source Hunter
 * Upload: /public_html/lead-engine/build-source-hunter.php
 *
 * Creates lawful source-hunting missions and target placeholders from high-intent seller categories.
 * Does NOT bypass DNC/realtor/approval logic. Pushes only staged target records to acquisition review.
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

  function sb1320($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows1320($table,$query){ $r=sb1320('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function town_value1320($town){
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)) return 1400000;
    if(in_array($town,['Wilton','Fairfield'],true)) return 950000;
    if(in_array($town,['Stamford','Norwalk'],true)) return 750000;
    return 700000;
  }
  function source_score1320($type){
    $map=[
      'fsbo'=>100,
      'make_me_move'=>96,
      'expired'=>94,
      'cancelled'=>90,
      'withdrawn'=>88,
      'rental_owner'=>78,
      'investor_owner'=>76,
      'vacant_signal'=>72,
      'long_owner'=>66
    ];
    return $map[$type] ?? 60;
  }
  function platform_queries1320($town,$type){
    $base=$town.' Fairfield County CT';
    $queries=[
      'fsbo'=>[
        'for sale by owner '.$base,
        'FSBO '.$base,
        '"for sale by owner" "'.$town.'" "CT"',
        'site:zillow.com "For Sale By Owner" "'.$town.'"',
        'site:fsbo.com "'.$town.'" "CT"'
      ],
      'make_me_move'=>[
        'make me move '.$base,
        '"make me move" "'.$town.'"',
        'owner willing to sell '.$base
      ],
      'expired'=>[
        'expired listing '.$base,
        '"expired listing" "'.$town.'"',
        '"listing expired" "'.$town.'" "CT"'
      ],
      'cancelled'=>[
        'cancelled listing '.$base,
        '"cancelled listing" "'.$town.'" "CT"'
      ],
      'withdrawn'=>[
        'withdrawn listing '.$base,
        '"withdrawn listing" "'.$town.'" "CT"'
      ],
      'rental_owner'=>[
        'rental property owner '.$base,
        '"for rent" "'.$town.'" "owner"',
        'single family rental '.$base
      ],
      'investor_owner'=>[
        'investment property owner '.$base,
        'landlord '.$base,
        'LLC owned property '.$base
      ]
    ];
    return $queries[$type] ?? [$type.' '.$base];
  }

  $towns=['Greenwich','Stamford','Darien','New Canaan','Norwalk','Westport','Wilton','Fairfield'];
  $types=['fsbo','make_me_move','expired','cancelled','withdrawn','rental_owner','investor_owner'];
  $today=date('Y-m-d');

  $missions=[];
  foreach($towns as $town){
    foreach($types as $type){
      foreach(array_slice(platform_queries1320($town,$type),0,3) as $q){
        $missions[]=[
          'mission_date'=>$today,
          'mission_name'=>ucwords(str_replace('_',' ',$type)).' — '.$town,
          'source_type'=>$type,
          'town'=>$town,
          'market'=>'Lower Fairfield County',
          'search_query'=>$q,
          'source_platform'=>str_contains($q,'zillow')?'Zillow/Search':(str_contains($q,'fsbo.com')?'FSBO/Search':'Google/Search'),
          'target_url'=>'',
          'priority_score'=>source_score1320($type),
          'status'=>'planned',
          'notes'=>'Use this as a search mission. Import specific owner/contact details only through approved acquisition workflow.',
          'raw_payload'=>['query'=>$q,'town'=>$town,'type'=>$type],
          'created_at'=>date('c'),
          'updated_at'=>date('c')
        ];
      }
    }
  }

  $inserted=[];$errors=[];
  foreach(array_chunk($missions,100) as $chunk){
    $r=sb1320('POST','source_hunter_missions',$chunk);
    if($r['ok'])$inserted[]=['missions'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['mission_error'=>$r['body'],'http'=>$r['http']];
  }

  // Convert existing strong non-person opportunity signals into source targets for acquisition review.
  $campaigns=rows1320('first_campaign_plan','select=*&order=priority_score.desc,created_at.desc&limit=150');
  $heat=rows1320('market_heat_snapshots','select=*&order=total_heat.desc,created_at.desc&limit=100');
  $seo=rows1320('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=150');

  $targets=[]; $seen=[];
  foreach($campaigns as $c){
    $town=$c['town']??'';
    $key='campaign:'.($c['id']??md5(json_encode($c)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $targets[]=[
      'target_date'=>$today,'source_type'=>'campaign_signal','source_platform'=>'Jessica Campaign Plan','source_url'=>'',
      'property_address'=>'','owner_name'=>'','phone'=>'','email'=>'','town'=>$town,'market'=>$c['market']??'Lower Fairfield County',
      'listing_price'=>0,'estimated_value'=>town_value1320($town),'signal_strength'=>(int)($c['priority_score']??80),'intent_score'=>min(88,(int)($c['priority_score']??80)),
      'acquisition_score'=>min(88,(int)($c['priority_score']??80)),'opportunity_reason'=>$c['campaign_name']??'High seller campaign signal',
      'recommended_action'=>'Use this signal to search for FSBO/expired/withdrawn owner opportunities in '.$town.'.',
      'acquisition_status'=>'source_review','pushed_to_acquisition'=>false,'compliance_status'=>'research_only',
      'raw_payload'=>$c,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }
  foreach($heat as $h){
    $town=$h['town']??'';
    $score=(int)($h['total_heat']??0);
    if($score<70)continue;
    $key='heat:'.$town.':'.$score;
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $targets[]=[
      'target_date'=>$today,'source_type'=>'hot_market_signal','source_platform'=>'Market Heat Map','source_url'=>'',
      'property_address'=>'','owner_name'=>'','phone'=>'','email'=>'','town'=>$town,'market'=>$h['market']??'Lower Fairfield County',
      'listing_price'=>0,'estimated_value'=>town_value1320($town),'signal_strength'=>$score,'intent_score'=>min(90,$score),
      'acquisition_score'=>min(90,$score),'opportunity_reason'=>'Hot market signal: '.($h['heat_band']??'hot'),
      'recommended_action'=>'Hunt FSBO, expired, cancelled, withdrawn, and owner-direct seller signals in '.$town.'.',
      'acquisition_status'=>'source_review','pushed_to_acquisition'=>false,'compliance_status'=>'research_only',
      'raw_payload'=>$h,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }
  foreach($seo as $s){
    $town=$s['town']??'';
    $score=(int)($s['priority_score']??0);
    if($score<75)continue;
    $key='seo:'.($s['id']??md5(json_encode($s)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $targets[]=[
      'target_date'=>$today,'source_type'=>'seller_content_signal','source_platform'=>'SEO/AEO','source_url'=>'/blog/'.($s['slug']??''),
      'property_address'=>'','owner_name'=>'','phone'=>'','email'=>'','town'=>$town,'market'=>$s['market']??'Lower Fairfield County',
      'listing_price'=>0,'estimated_value'=>town_value1320($town),'signal_strength'=>$score,'intent_score'=>min(86,$score),
      'acquisition_score'=>min(86,$score),'opportunity_reason'=>$s['title']??'Seller content signal',
      'recommended_action'=>'Create/approve content and use it to attract owner-direct leads in '.$town.'.',
      'acquisition_status'=>'source_review','pushed_to_acquisition'=>false,'compliance_status'=>'research_only',
      'raw_payload'=>$s,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }

  usort($targets,function($a,$b){ return $b['intent_score']<=>$a['intent_score']; });

  foreach(array_chunk(array_slice($targets,0,500),100) as $chunk){
    $r=sb1320('POST','source_hunter_targets',$chunk);
    if($r['ok'])$inserted[]=['targets'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['target_error'=>$r['body'],'http'=>$r['http']];
  }

  $counts=['fsbo'=>0,'expired'=>0,'cancelled'=>0,'withdrawn'=>0,'investor'=>0,'rental'=>0]; $townCounts=[];
  foreach($targets as $t){
    $type=$t['source_type']??'';
    if(str_contains($type,'fsbo'))$counts['fsbo']++;
    if(str_contains($type,'expired'))$counts['expired']++;
    if(str_contains($type,'cancelled'))$counts['cancelled']++;
    if(str_contains($type,'withdrawn'))$counts['withdrawn']++;
    if(str_contains($type,'investor'))$counts['investor']++;
    if(str_contains($type,'rental'))$counts['rental']++;
    $town=$t['town']?:'Unknown';
    $townCounts[$town]=($townCounts[$town]??0)+1;
  }
  arsort($townCounts);

  $recs=[
    'Start with FSBO, Make Me Move, expired, cancelled, and withdrawn sources before random homeowner prospecting.',
    'Use Source Hunter as the research layer; only push real owner/contact details through V13.1 acquisition.',
    'The current system is ready to process owner CSVs and approved source records into call queues.'
  ];

  $brief="V13.2 SOURCE HUNTER\\n";
  $brief.="========================================\\n\\n";
  $brief.="Missions Created:     ".count($missions)."\\n";
  $brief.="Targets Created:      ".count($targets)."\\n";
  $brief.="Top Town:             ".(array_key_first($townCounts)?:'n/a')."\\n";
  $brief.="FSBO Signals:         ".$counts['fsbo']."\\n";
  $brief.="Expired Signals:      ".$counts['expired']."\\n";
  $brief.="Cancelled Signals:    ".$counts['cancelled']."\\n";
  $brief.="Withdrawn Signals:    ".$counts['withdrawn']."\\n";
  $brief.="Investor Signals:     ".$counts['investor']."\\n";
  $brief.="Rental Signals:       ".$counts['rental']."\\n\\n";
  $brief.="TOP TARGETS\\n----------------------------------------\\n";
  foreach(array_slice($targets,0,15) as $i=>$t){
    $brief.=($i+1).". ".$t['opportunity_reason']."\\n";
    $brief.="     Town:        ".$t['town']."\\n";
    $brief.="     Type:        ".$t['source_type']."\\n";
    $brief.="     Intent:      ".$t['intent_score']."\\n";
    $brief.="     Action:      ".$t['recommended_action']."\\n\\n";
  }
  $brief.="JESSICA RECOMMENDS\\n----------------------------------------\\n";
  foreach($recs as $i=>$r){ $brief.=($i+1).". {$r}\\n\\n"; }

  $daily=[[
    'briefing_date'=>$today,'missions_created'=>count($missions),'targets_created'=>count($targets),
    'fsbo_count'=>$counts['fsbo'],'expired_count'=>$counts['expired'],'cancelled_count'=>$counts['cancelled'],'withdrawn_count'=>$counts['withdrawn'],
    'investor_count'=>$counts['investor'],'rental_count'=>$counts['rental'],'top_town'=>array_key_first($townCounts)?:'',
    'top_targets'=>array_slice($targets,0,25),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];

  $dr=sb1320('POST','source_hunter_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb1320('PATCH','source_hunter_briefings?briefing_date=eq.'.rawurlencode($today),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'missions_created'=>count($missions),
    'targets_created'=>count($targets),
    'top_town'=>array_key_first($townCounts)?:null,
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>