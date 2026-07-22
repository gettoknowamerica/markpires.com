<?php
/**
 * V16.4A Internal Learning Brain
 * Upload: /public_html/lead-engine/build-internal-learning-brain.php
 */

ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb164($method,$endpoint,$payload=null){
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
  function rows164($t,$q){$r=sb164('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function assetKey164($source,$id,$title){return strtolower(trim($source.':'.$id.':'.preg_replace('/\s+/',' ',(string)$title)));}
  function event164($type,$source,$id,$title,$brand,$content,$town,$raw,$extra=[]){
    return array_merge([
      'event_date'=>date('Y-m-d'),
      'event_type'=>$type,
      'source_table'=>$source,
      'source_id'=>(string)$id,
      'asset_title'=>$title,
      'brand_pillar'=>$brand,
      'content_type'=>$content,
      'campaign_name'=>$title,
      'town'=>$town,
      'lead_id'=>'',
      'opportunity_id'=>'',
      'contact_name'=>'',
      'phone'=>'',
      'email'=>'',
      'lead_score'=>0,
      'opportunity_score'=>0,
      'pipeline_value'=>0,
      'estimated_commission'=>0,
      'expected_value'=>0,
      'performance_weight'=>0,
      'raw_payload'=>$raw,
      'created_at'=>date('c')
    ],$extra);
  }

  // avoid duplicate event flood by clearing today's generated events from this builder
  sb164('DELETE','internal_performance_events?event_date=eq.'.rawurlencode(date('Y-m-d')).'&raw_payload->>builder=eq.v16_4_internal_learning');

  $events=[];

  $review=rows164('creative_review_studio_items','select=*&created_at=gte.'.rawurlencode(date('c',strtotime('-30 days'))).'&limit=5000');
  foreach($review as $r){
    $status=$r['review_status']??'review';
    $type='creative_created';
    $weight=5;
    if($status==='approved'){$type='creative_approved';$weight=20;}
    if($status==='sent_to_blotato'){$type='sent_to_blotato';$weight=30;}
    if($status==='improve'){$type='creative_needs_improvement';$weight=3;}
    $raw=$r; $raw['builder']='v16_4_internal_learning';
    $events[]=event164($type,'creative_review_studio_items',$r['id']??'',$r['review_title']??'Creative Review',$r['brand_pillar']??'mark_pires',$r['content_type']??'creative',$r['town']??'', $raw,[
      'lead_score'=>(int)($r['review_score']??0),
      'performance_weight'=>$weight
    ]);
  }

  $dist=rows164('blotato_distribution_queue','select=*&created_at=gte.'.rawurlencode(date('c',strtotime('-30 days'))).'&limit=5000');
  foreach($dist as $d){
    $status=$d['distribution_status']??'draft';
    $approval=$d['approval_status']??'review';
    $type='queued';
    $weight=8;
    if($approval==='approved'){$type='distribution_approved';$weight=25;}
    if($status==='scheduled'){$type='scheduled';$weight=35;}
    if($status==='posted'){$type='posted';$weight=50;}
    $raw=$d; $raw['builder']='v16_4_internal_learning';
    $events[]=event164($type,'blotato_distribution_queue',$d['id']??'',$d['distribution_title']??'Distribution Item',$d['brand_pillar']??'mark_pires',$d['content_type']??'post','',$raw,[
      'lead_score'=>(int)($d['distribution_score']??0),
      'performance_weight'=>$weight
    ]);
  }

  $leads=rows164('leads','select=*&created_at=gte.'.rawurlencode(date('c',strtotime('-90 days'))).'&limit=5000');
  foreach($leads as $l){
    $source=trim(($l['source']??'').' '.($l['type']??''));
    $title=$source ?: 'Lead Source';
    $brand='seller_authority';
    if(stripos($source,'discover')!==false)$brand='discover_ct';
    if(stripos($source,'detective')!==false)$brand='house_detective';
    $value=(float)($l['budget']??$l['value']??$l['estimated_value']??0);
    $commission=$value>0?$value*.025:0;
    $raw=$l; $raw['builder']='v16_4_internal_learning';
    $events[]=event164('lead','leads',$l['id']??'',$title,$brand,'lead',$l['town']??'', $raw,[
      'lead_id'=>(string)($l['id']??''),
      'contact_name'=>$l['name']??'',
      'phone'=>$l['phone']??'',
      'email'=>$l['email']??'',
      'lead_score'=>(int)($l['lead_score']??0),
      'pipeline_value'=>$value,
      'estimated_commission'=>$commission,
      'expected_value'=>$commission*.20,
      'performance_weight'=>40
    ]);
  }

  $voice=rows164('voice_intelligence_events','select=*&created_at=gte.'.rawurlencode(date('c',strtotime('-90 days'))).'&limit=5000');
  foreach($voice as $v){
    $raw=$v; $raw['builder']='v16_4_internal_learning';
    $events[]=event164('call','voice_intelligence_events',$v['id']??'','Jessica Voice Call','voice','call',$v['town']??'', $raw,[
      'lead_score'=>(int)($v['lead_score']??0),
      'performance_weight'=>35
    ]);
  }

  $opp=rows164('seller_acquisition_director','select=*&status=eq.active&limit=5000');
  foreach($opp as $o){
    $title=$o['opportunity_title']??$o['property_address']??$o['campaign_name']??'Seller Opportunity';
    $raw=$o; $raw['builder']='v16_4_internal_learning';
    $score=(int)($o['acquisition_score']??0);
    $eventType=!empty($o['ready_to_contact'])?'appointment_signal':'seller_opportunity';
    $weight=!empty($o['ready_to_contact'])?65:45;
    $events[]=event164($eventType,'seller_acquisition_director',$o['id']??'',$title,'seller_authority','seller_opportunity',$o['town']??'', $raw,[
      'opportunity_id'=>(string)($o['id']??''),
      'opportunity_score'=>$score,
      'pipeline_value'=>(float)($o['estimated_sale_price']??0),
      'estimated_commission'=>(float)($o['estimated_commission']??0),
      'expected_value'=>(float)($o['expected_value']??0),
      'performance_weight'=>$weight
    ]);
  }

  $inserted=[];$errors=[];
  foreach(array_chunk($events,100) as $chunk){
    $keys=array_keys($chunk[0]);
    $norm=[];
    foreach($chunk as $row){$clean=[]; foreach($keys as $k){$clean[$k]=$row[$k]??null;} $norm[]=$clean;}
    $r=sb164('POST','internal_performance_events',$norm);
    if($r['ok'])$inserted[]=['count'=>count($norm),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  // Aggregate all recent events into asset performance.
  $allEvents=rows164('internal_performance_events','select=*&created_at=gte.'.rawurlencode(date('c',strtotime('-1 day'))).'&limit=10000');
  $agg=[];
  foreach($allEvents as $e){
    $key=assetKey164($e['source_table']??'', $e['source_id']??'', $e['asset_title']??'');
    if(!isset($agg[$key])){
      $agg[$key]=[
        'performance_date'=>date('Y-m-d'),
        'asset_key'=>$key,
        'asset_title'=>$e['asset_title']??'',
        'brand_pillar'=>$e['brand_pillar']??'',
        'content_type'=>$e['content_type']??'',
        'source_table'=>$e['source_table']??'',
        'source_id'=>(string)($e['source_id']??''),
        'town'=>$e['town']??'',
        'created_count'=>0,'approved_count'=>0,'sent_to_distribution_count'=>0,'posted_count'=>0,
        'lead_count'=>0,'call_count'=>0,'appointment_count'=>0,'listing_count'=>0,'closing_count'=>0,
        'pipeline_value'=>0,'estimated_commission'=>0,'expected_value'=>0,
        'learning_score'=>0,'revenue_score'=>0,'conversion_score'=>0,
        'recommendation'=>'watch','recommended_next_action'=>'Keep watching for more internal signal.',
        'raw_payload'=>['events'=>[]],
        'created_at'=>date('c'),'updated_at'=>date('c')
      ];
    }
    $type=$e['event_type']??'';
    if(in_array($type,['creative_created','queued']))$agg[$key]['created_count']++;
    if(in_array($type,['creative_approved','distribution_approved']))$agg[$key]['approved_count']++;
    if(in_array($type,['sent_to_blotato','scheduled']))$agg[$key]['sent_to_distribution_count']++;
    if($type==='posted')$agg[$key]['posted_count']++;
    if($type==='lead')$agg[$key]['lead_count']++;
    if($type==='call')$agg[$key]['call_count']++;
    if($type==='appointment_signal')$agg[$key]['appointment_count']++;
    if($type==='listing')$agg[$key]['listing_count']++;
    if($type==='closing')$agg[$key]['closing_count']++;
    $agg[$key]['pipeline_value']+=(float)($e['pipeline_value']??0);
    $agg[$key]['estimated_commission']+=(float)($e['estimated_commission']??0);
    $agg[$key]['expected_value']+=(float)($e['expected_value']??0);
    $agg[$key]['raw_payload']['events'][]=$e['id'];
  }

  // Upsert manually via delete/insert because PostgREST may not have unique metadata refreshed immediately.
  $perfRows=[];
  foreach($agg as $key=>$a){
    $conversion=($a['approved_count']*5)+($a['sent_to_distribution_count']*8)+($a['posted_count']*12)+($a['lead_count']*18)+($a['call_count']*15)+($a['appointment_count']*25)+($a['listing_count']*40)+($a['closing_count']*60);
    $revenue=min(100,round(($a['estimated_commission']/25000)*25 + ($a['expected_value']/10000)*20));
    $learning=min(100,round(min(70,$conversion)+min(30,$revenue)));
    $a['conversion_score']=min(100,$conversion);
    $a['revenue_score']=$revenue;
    $a['learning_score']=$learning;
    if($learning>=85){$a['recommendation']='scale';$a['recommended_next_action']='Scale this pattern. Create more similar creative and route it to distribution.';}
    elseif($learning>=70){$a['recommendation']='repeat';$a['recommended_next_action']='Repeat this pattern with new variants and stronger CTA.';}
    elseif($learning>=45){$a['recommendation']='improve';$a['recommended_next_action']='Improve creative, hook, CTA, or audience before scaling.';}
    else {$a['recommendation']='watch';$a['recommended_next_action']='Keep collecting internal signal before making a scale decision.';}
    $perfRows[]=$a;
  }

  sb164('DELETE','internal_asset_performance?performance_date=eq.'.rawurlencode(date('Y-m-d')));
  $perfInserted=[];$perfErrors=[];
  foreach(array_chunk($perfRows,100) as $chunk){
    if(empty($chunk)) continue;
    $keys=array_keys($chunk[0]); $norm=[];
    foreach($chunk as $row){$clean=[]; foreach($keys as $k){$clean[$k]=$row[$k]??null;} $norm[]=$clean;}
    $r=sb164('POST','internal_asset_performance',$norm);
    if($r['ok'])$perfInserted[]=['count'=>count($norm),'http'=>$r['http']];
    else $perfErrors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $assets=rows164('internal_asset_performance','select=*&performance_date=eq.'.rawurlencode(date('Y-m-d')).'&order=learning_score.desc,estimated_commission.desc&limit=1000');
  $counts=['scale'=>0,'repeat'=>0,'improve'=>0,'watch'=>0,'leads'=>0,'calls'=>0,'pipeline'=>0,'commission'=>0];
  foreach($assets as $a){
    $rec=$a['recommendation']??'watch'; if(isset($counts[$rec]))$counts[$rec]++;
    $counts['leads']+=(int)($a['lead_count']??0);
    $counts['calls']+=(int)($a['call_count']??0);
    $counts['pipeline']+=(float)($a['pipeline_value']??0);
    $counts['commission']+=(float)($a['estimated_commission']??0);
  }
  $top=$assets[0]??[];

  $brief="V16.4A INTERNAL LEARNING BRAIN\n========================================\n\n";
  $brief.="Assets Learned From:       ".count($assets)."\n";
  $brief.="Scale Patterns:            ".$counts['scale']."\n";
  $brief.="Repeat Patterns:           ".$counts['repeat']."\n";
  $brief.="Improve Patterns:          ".$counts['improve']."\n";
  $brief.="Watch Patterns:            ".$counts['watch']."\n";
  $brief.="Internal Leads:            ".$counts['leads']."\n";
  $brief.="Internal Calls:            ".$counts['calls']."\n";
  $brief.="Pipeline Value:            $".number_format($counts['pipeline'],0)."\n";
  $brief.="Estimated Commission:      $".number_format($counts['commission'],0)."\n";
  $brief.="Top Asset:                 ".($top['asset_title']??'n/a')."\n\n";
  $brief.="TOP INTERNAL WINNERS\n----------------------------------------\n";
  foreach(array_slice($assets,0,25) as $i=>$a){
    $brief.=($i+1).". ".$a['asset_title']." — ".$a['brand_pillar']." — ".$a['recommendation']." — Score ".$a['learning_score']."\n";
    $brief.="   Leads ".$a['lead_count']." | Calls ".$a['call_count']." | Appts ".$a['appointment_count']." | Commission $".number_format((float)$a['estimated_commission'],0)."\n";
    $brief.="   Action: ".$a['recommended_next_action']."\n\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),
    'total_assets'=>count($assets),
    'scale_count'=>$counts['scale'],
    'repeat_count'=>$counts['repeat'],
    'improve_count'=>$counts['improve'],
    'watch_count'=>$counts['watch'],
    'total_leads'=>$counts['leads'],
    'total_calls'=>$counts['calls'],
    'total_pipeline_value'=>$counts['pipeline'],
    'total_estimated_commission'=>$counts['commission'],
    'top_asset'=>$top['asset_title']??'',
    'top_brand_pillar'=>$top['brand_pillar']??'',
    'top_town'=>$top['town']??'',
    'winners'=>array_slice($assets,0,30),
    'briefing_text'=>$brief,
    'recommendations'=>[
      'Scale assets with internal revenue or appointment signal first.',
      'Repeat patterns with approval plus lead/call signal.',
      'Use improve for content that is approved but not converting.',
      'V17 Media Director should prioritize patterns marked scale or repeat.'
    ],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $dr=sb164('POST','internal_learning_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb164('PATCH','internal_learning_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors)&&empty($perfErrors),
    'events_created'=>count($events),
    'assets_scored'=>count($assets),
    'scale_count'=>$counts['scale'],
    'repeat_count'=>$counts['repeat'],
    'total_leads'=>$counts['leads'],
    'total_calls'=>$counts['calls'],
    'estimated_commission'=>$counts['commission'],
    'briefing'=>$brief,
    'inserted_events'=>$inserted,
    'inserted_performance'=>$perfInserted,
    'errors'=>array_merge($errors,$perfErrors)
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>