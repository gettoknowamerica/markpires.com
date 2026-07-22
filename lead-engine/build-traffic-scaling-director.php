<?php
/**
 * V15.2 PRO Traffic Scaling Director
 * Upload: /public_html/lead-engine/build-traffic-scaling-director.php
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

  function sb152($method,$endpoint,$payload=null){
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
    $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows152($t,$q){$r=sb152('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function src152($v){
    $v=strtolower(trim((string)$v));
    if(str_contains($v,'valuation') || str_contains($v,'home value')) return 'Home Valuation Funnel';
    if(str_contains($v,'fsbo') || str_contains($v,'expired') || str_contains($v,'withdrawn') || str_contains($v,'seller_source')) return 'Seller Source Imports';
    if(str_contains($v,'voice') || str_contains($v,'retell') || str_contains($v,'call')) return 'Voice / Jessica Calls';
    if(str_contains($v,'discover')) return 'Discover CT';
    if(str_contains($v,'detective')) return 'House Detective';
    if(str_contains($v,'blog') || str_contains($v,'seo') || str_contains($v,'aeo')) return 'Blog / SEO / AEO';
    if(str_contains($v,'creative')) return 'Creative Intelligence';
    if(str_contains($v,'referral') || str_contains($v,'manual')) return 'Manual / Referral';
    return 'Manual / Referral';
  }
  function blankMetric($name,$type='mixed'){
    return [
      'traffic_date'=>date('Y-m-d'),'source_name'=>$name,'source_type'=>$type,'lead_count'=>0,'qualified_count'=>0,'call_count'=>0,
      'appointment_count'=>0,'acquisition_count'=>0,'ready_to_contact_count'=>0,'creative_asset_count'=>0,'publish_ready_count'=>0,
      'pipeline_value'=>0,'estimated_commission'=>0,'expected_value'=>0,'traffic_score'=>0,'revenue_score'=>0,
      'scale_recommendation'=>'watch','recommended_action'=>'Collect more signal before scaling.','raw_payload'=>[],
      'created_at'=>date('c'),'updated_at'=>date('c')
    ];
  }

  $sourceNames=[
    'Home Valuation Funnel','Seller Source Imports','Seller Acquisition Director','Voice / Jessica Calls',
    'Discover CT','House Detective','Blog / SEO / AEO','Creative Intelligence','Manual / Referral'
  ];
  $metrics=[];
  foreach($sourceNames as $s){$metrics[$s]=blankMetric($s);}

  // leads table
  $leads=rows152('leads','select=*&order=created_at.desc&limit=5000');
  foreach($leads as $l){
    $source=src152(($l['source']??'').' '.($l['type']??'').' '.($l['lead_type']??''));
    $metrics[$source]['lead_count']++;
    $score=(int)($l['lead_score']??0);
    if($score>=70 || (($l['route']??'')==='mark_priority')) $metrics[$source]['qualified_count']++;
    if(!empty($l['phone'])) $metrics[$source]['ready_to_contact_count']++;
  }

  // voice intelligence
  $voice=rows152('voice_intelligence_events','select=*&order=created_at.desc&limit=5000');
  foreach($voice as $v){
    $source='Voice / Jessica Calls';
    $metrics[$source]['call_count']++;
    if(!empty($v['lead_related'])) $metrics[$source]['lead_count']++;
    if((int)($v['lead_score']??0)>=70 || !empty($v['hot_lead'])) $metrics[$source]['qualified_count']++;
    if(!empty($v['appointment_requested'])) $metrics[$source]['appointment_count']++;
    if(!empty($v['callback_needed'])) $metrics[$source]['ready_to_contact_count']++;
  }

  // seller acquisition director
  $sad=rows152('seller_acquisition_director','select=*&status=eq.active&order=acquisition_score.desc&limit=5000');
  foreach($sad as $s){
    $source=src152(($s['source_type']??'').' '.($s['source_table']??''));
    if($source==='Manual / Referral') $source='Seller Acquisition Director';
    $metrics[$source]['acquisition_count']++;
    if((int)($s['acquisition_score']??0)>=75) $metrics[$source]['qualified_count']++;
    if(!empty($s['ready_to_contact'])) $metrics[$source]['ready_to_contact_count']++;
    $metrics[$source]['pipeline_value']+=(float)($s['estimated_sale_price']??0);
    $metrics[$source]['estimated_commission']+=(float)($s['estimated_commission']??0);
    $metrics[$source]['expected_value']+=(float)($s['expected_value']??0);
  }

  // seller opportunity sources
  $seller=rows152('seller_opportunity_sources','select=*&status=eq.active&limit=5000');
  foreach($seller as $s){
    $source='Seller Source Imports';
    $metrics[$source]['lead_count']++;
    if((int)($s['total_seller_score']??0)>=70) $metrics[$source]['qualified_count']++;
    if(!empty($s['call_eligible'])) $metrics[$source]['ready_to_contact_count']++;
  }

  // contact enrichment
  $enrich=rows152('contact_enrichment_queue','select=*&status=eq.active&limit=5000');
  foreach($enrich as $e){
    $source='Seller Source Imports';
    if(($e['source_table']??'')==='seller_acquisition_director') $source='Seller Acquisition Director';
    if(($e['enrichment_status']??'')==='needs_contact') $metrics[$source]['lead_count']++;
    if(($e['enrichment_status']??'')==='call_queue') $metrics[$source]['ready_to_contact_count']++;
  }

  // listing intelligence
  $li=rows152('listing_intelligence_opportunities','select=*&status=eq.active&limit=5000');
  foreach($li as $l){
    $source='Seller Acquisition Director';
    $metrics[$source]['acquisition_count']++;
    if((int)($l['listing_probability_score']??0)>=75) $metrics[$source]['qualified_count']++;
    if(!empty($l['call_eligible'])) $metrics[$source]['ready_to_contact_count']++;
    $value=(float)($l['estimated_sale_price']??0);
    if($value>0){
      $metrics[$source]['pipeline_value']+=$value;
      $metrics[$source]['estimated_commission']+=$value*.025;
      $metrics[$source]['expected_value']+=($value*.025)*.25;
    }
  }

  // creative intelligence
  $creative=rows152('creative_intelligence_assets','select=*&status=eq.active&limit=5000');
  foreach($creative as $c){
    $source=src152(($c['brand_pillar']??'').' '.($c['asset_type']??'').' '.($c['title']??''));
    $metrics[$source]['creative_asset_count']++;
    if(in_array(($c['recommendation']??''),['publish','repurpose'],true)) $metrics[$source]['publish_ready_count']++;
    if((int)($c['lead_gen_score']??0)>=70) $metrics[$source]['qualified_count']++;
  }

  // calculate scores
  foreach($metrics as $name=>$m){
    $activity=$m['lead_count']+$m['call_count']+$m['acquisition_count']+$m['creative_asset_count'];
    $conversion=$m['qualified_count']*3 + $m['appointment_count']*8 + $m['ready_to_contact_count']*4 + $m['publish_ready_count']*2;
    $revenueScore=min(100, round(($m['estimated_commission']/25000)*20 + ($m['expected_value']/10000)*15));
    $trafficScore=min(100, round(min(40,$activity*3)+min(45,$conversion)+min(25,$revenueScore)));
    $metrics[$name]['traffic_score']=$trafficScore;
    $metrics[$name]['revenue_score']=$revenueScore;

    if($trafficScore>=80) {
      $metrics[$name]['scale_recommendation']='scale';
      $metrics[$name]['recommended_action']='Double down: this source is producing enough signal to justify more time, content, or budget.';
    } elseif($trafficScore>=55) {
      $metrics[$name]['scale_recommendation']='optimize';
      $metrics[$name]['recommended_action']='Optimize: improve creative, follow-up, or funnel conversion before increasing budget.';
    } elseif($activity>0) {
      $metrics[$name]['scale_recommendation']='watch';
      $metrics[$name]['recommended_action']='Watch: keep collecting data and improve targeting.';
    } else {
      $metrics[$name]['scale_recommendation']='seed';
      $metrics[$name]['recommended_action']='Seed: no meaningful traffic signal yet. Add content, leads, or source imports.';
    }
    $metrics[$name]['raw_payload']=['generated_from'=>'V15.2 PRO integrated tables'];
  }

  $errors=[]; $inserted=[];
  foreach($metrics as $m){
    $r=sb152('POST','traffic_performance',[$m]);
    if($r['ok'])$inserted[]=['source'=>$m['source_name'],'http'=>$r['http']];
    else $errors[]=['source'=>$m['source_name'],'body'=>$r['body']];
  }

  usort($metrics,function($a,$b){return $b['traffic_score']<=>$a['traffic_score'];});

  $totals=['leads'=>0,'calls'=>0,'appointments'=>0,'pipeline'=>0,'commission'=>0];
  foreach($metrics as $m){
    $totals['leads']+=(int)$m['lead_count'];
    $totals['calls']+=(int)$m['call_count'];
    $totals['appointments']+=(int)$m['appointment_count'];
    $totals['pipeline']+=(float)$m['pipeline_value'];
    $totals['commission']+=(float)$m['estimated_commission'];
  }

  $top=$metrics[0]??blankMetric('None');
  $recs=[
    'Scale only sources with appointment or ready-to-contact signal, not raw lead count.',
    'Use Creative Intelligence to feed weak but strategic sources before spending money.',
    'Home Valuation, Seller Source Imports, and Voice Calls should be the first proof-of-concept traffic loops.',
    'Use 2.5% commission estimates for revenue comparisons.'
  ];

  $brief="V15.2 PRO TRAFFIC SCALING DIRECTOR\\n========================================\\n\\n";
  $brief.="Top Source:                 ".$top['source_name']."\\n";
  $brief.="Top Recommendation:         ".$top['scale_recommendation']."\\n";
  $brief.="Total Leads:                ".$totals['leads']."\\n";
  $brief.="Total Calls:                ".$totals['calls']."\\n";
  $brief.="Total Appointments:         ".$totals['appointments']."\\n";
  $brief.="Total Pipeline Value:       $".number_format($totals['pipeline'],0)."\\n";
  $brief.="Estimated Commission 2.5%:  $".number_format($totals['commission'],0)."\\n\\n";
  $brief.="SOURCE RANKINGS\\n----------------------------------------\\n";
  foreach($metrics as $i=>$m){
    $brief.=($i+1).". ".$m['source_name']." — Score ".$m['traffic_score']." — ".$m['scale_recommendation']."\\n";
    $brief.="   Leads ".$m['lead_count']." | Calls ".$m['call_count']." | Appts ".$m['appointment_count']." | Ready ".$m['ready_to_contact_count']." | Commission $".number_format((float)$m['estimated_commission'],0)."\\n";
    $brief.="   Action: ".$m['recommended_action']."\\n\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_sources'=>count($metrics),'total_leads'=>$totals['leads'],'total_calls'=>$totals['calls'],
    'total_appointments'=>$totals['appointments'],'total_pipeline_value'=>round($totals['pipeline'],2),'total_estimated_commission'=>round($totals['commission'],2),
    'top_source'=>$top['source_name'],'top_recommendation'=>$top['recommended_action'],'source_rankings'=>array_values($metrics),
    'briefing_text'=>$brief,'recommendations'=>$recs,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb152('POST','traffic_scaling_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb152('PATCH','traffic_scaling_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'top_source'=>$top['source_name'],'total_leads'=>$totals['leads'],'total_calls'=>$totals['calls'],'total_appointments'=>$totals['appointments'],'estimated_commission'=>$totals['commission'],'briefing'=>$brief,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>