<?php
/**
 * V13.5 Opportunity Pipeline Builder
 * Upload: /public_html/lead-engine/build-opportunity-pipeline.php
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

  function sb135($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows135($table,$query){ $r=sb135('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function phone135($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }
  function value135($town,$v=0){
    $v=(float)$v; if($v>0)return $v;
    if(in_array($town,['Greenwich','Westport','Darien','New Canaan'],true)) return 1400000;
    if(in_array($town,['Wilton','Fairfield'],true)) return 950000;
    if(in_array($town,['Stamford','Norwalk'],true)) return 750000;
    return 700000;
  }
  function pipeline_item135($o){
    $base=[
      'pipeline_date'=>date('Y-m-d'),'source_table'=>'','source_id'=>'','opportunity_type'=>'seller','name'=>'','phone'=>'','email'=>'','address'=>'','town'=>'',
      'market'=>'Lower Fairfield County','pipeline_stage'=>'new','stage_score'=>10,'priority_score'=>0,'probability'=>10,
      'estimated_sale_price'=>0,'estimated_commission'=>0,'expected_value'=>0,'next_step'=>'Review','next_followup_at'=>null,'last_activity_at'=>date('c'),
      'assigned_to'=>'mark','status'=>'active','notes'=>'','raw_payload'=>[],'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }

  $items=[]; $seen=[];

  // Approved contacts / call queue
  $contacts=rows135('approved_contact_pool','select=*&status=eq.active&order=call_eligible.desc,contact_score.desc,created_at.desc&limit=1000');
  foreach($contacts as $c){
    $phone=phone135($c['phone']??'');
    $key=$phone?'p:'.$phone:'contact:'.($c['id']??md5(json_encode($c)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $town=$c['town']??''; $v=value135($town,$c['estimated_value']??0);
    $stage=!empty($c['call_eligible'])?'call_queue':(!empty($c['email_eligible'])?'approved_contact':'review');
    $prob=$stage==='call_queue'?18:($stage==='approved_contact'?12:5);
    $score=(int)($c['contact_score']??0);
    $items[]=pipeline_item135([
      'source_table'=>'approved_contact_pool','source_id'=>(string)($c['id']??''),'opportunity_type'=>$c['lead_type']??'seller',
      'name'=>$c['name']??'','phone'=>$phone,'email'=>$c['email']??'','address'=>$c['address']??'','town'=>$town,'market'=>$c['market']??'Lower Fairfield County',
      'pipeline_stage'=>$stage,'stage_score'=>$stage==='call_queue'?30:20,'priority_score'=>$score,'probability'=>$prob,
      'estimated_sale_price'=>$v,'estimated_commission'=>round($v*.025,2),'expected_value'=>round(($v*.025)*($prob/100),2),
      'next_step'=>$stage==='call_queue'?'Call today / Jessica queue.':'Review or nurture before outreach.',
      'next_followup_at'=>date('c',strtotime('+1 day')),'notes'=>$c['recommended_action']??'','raw_payload'=>$c
    ]);
  }

  // Conversation learning / appointments
  $appts=rows135('appointment_intelligence_queue','select=*&order=appointment_priority.desc,created_at.desc&limit=300');
  foreach($appts as $a){
    $phone=phone135($a['caller_phone']??'');
    $key=$phone?'p:'.$phone:'appt:'.($a['id']??md5(json_encode($a)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $town=$a['town']??''; $v=value135($town,0); $prob=45; $score=(int)($a['appointment_priority']??70);
    $items[]=pipeline_item135([
      'source_table'=>'appointment_intelligence_queue','source_id'=>(string)($a['id']??''),'opportunity_type'=>$a['lead_type']??'seller',
      'name'=>$a['caller_name']??'','phone'=>$phone,'email'=>$a['caller_email']??'','town'=>$town,
      'pipeline_stage'=>'appointment','stage_score'=>70,'priority_score'=>$score,'probability'=>$prob,
      'estimated_sale_price'=>$v,'estimated_commission'=>round($v*.025,2),'expected_value'=>round(($v*.025)*($prob/100),2),
      'next_step'=>'Schedule/confirm appointment with Mark.','next_followup_at'=>date('c',strtotime('+4 hours')),'notes'=>$a['appointment_reason']??'','raw_payload'=>$a
    ]);
  }

  // Executive call inbox
  $exec=rows135('executive_call_inbox','select=*&status=eq.new&order=call_date.desc&limit=200');
  foreach($exec as $e){
    $phone=phone135($e['caller_phone']??'');
    $key=$phone?'p:'.$phone:'exec:'.($e['id']??md5(json_encode($e)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    $stage=!empty($e['lead_related'])?'conversation':'review';
    $prob=!empty($e['appointment_requested'])?40:($stage==='conversation'?25:8);
    $score=($e['urgency']??'')==='urgent'?95:(($e['urgency']??'')==='high'?85:60);
    $v=value135('',0);
    $items[]=pipeline_item135([
      'source_table'=>'executive_call_inbox','source_id'=>(string)($e['id']??''),'opportunity_type'=>!empty($e['lead_related'])?'seller':'executive_call',
      'name'=>$e['caller_name']??'','phone'=>$phone,'email'=>$e['caller_email']??'',
      'pipeline_stage'=>$stage,'stage_score'=>$stage==='conversation'?50:15,'priority_score'=>$score,'probability'=>$prob,
      'estimated_sale_price'=>$v,'estimated_commission'=>round($v*.025,2),'expected_value'=>round(($v*.025)*($prob/100),2),
      'next_step'=>$e['recommended_action']??'Review forwarded call.','next_followup_at'=>date('c',strtotime('+2 hours')),'notes'=>$e['summary']??'','raw_payload'=>$e
    ]);
  }

  // Listing intelligence
  $listings=rows135('listing_intelligence_opportunities','select=*&status=eq.active&order=call_eligible.desc,listing_probability_score.desc&limit=500');
  foreach($listings as $l){
    $phone=phone135($l['phone']??'');
    $key=$phone?'p:'.$phone:'listing:'.($l['id']??md5(json_encode($l)));
    if(isset($seen[$key]))continue; $seen[$key]=true;
    if(empty($l['call_eligible']) && empty($l['phone']) && empty($l['email'])) continue;
    $score=(int)($l['listing_probability_score']??0);
    $stage=!empty($l['call_eligible'])?'call_queue':'listing_opportunity';
    $prob=!empty($l['call_eligible'])?22:min(35,max(10,round($score/3)));
    $v=value135($l['town']??'', $l['estimated_sale_price']??0);
    $items[]=pipeline_item135([
      'source_table'=>'listing_intelligence_opportunities','source_id'=>(string)($l['id']??''),'opportunity_type'=>'seller',
      'name'=>$l['name']??'','phone'=>$phone,'email'=>$l['email']??'','address'=>$l['address']??'','town'=>$l['town']??'','market'=>$l['market']??'Lower Fairfield County',
      'pipeline_stage'=>$stage,'stage_score'=>$stage==='call_queue'?30:65,'priority_score'=>$score,'probability'=>$prob,
      'estimated_sale_price'=>$v,'estimated_commission'=>round($v*.025,2),'expected_value'=>round(($v*.025)*($prob/100),2),
      'next_step'=>$l['next_best_action']??'Review listing opportunity.','next_followup_at'=>date('c',strtotime('+1 day')),'notes'=>$l['why_this_matters']??'','raw_payload'=>$l
    ]);
  }

  usort($items,function($a,$b){
    if($a['stage_score']!==$b['stage_score']) return $b['stage_score']<=>$a['stage_score'];
    return $b['priority_score']<=>$a['priority_score'];
  });

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($items,0,1000),100) as $chunk){
    $r=sb135('POST','jessica_opportunity_pipeline',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows135('jessica_opportunity_pipeline','select=*&status=eq.active&order=stage_score.desc,priority_score.desc&limit=1000');
  $counts=['new'=>0,'call_queue'=>0,'contacted'=>0,'conversation'=>0,'appointment'=>0,'listing_opportunity'=>0,'signed'=>0,'closed'=>0];
  $pipeline=0;$expected=0;
  foreach($all as $i){
    $st=$i['pipeline_stage']??'new'; if(isset($counts[$st]))$counts[$st]++;
    $pipeline+=(float)($i['estimated_commission']??0); $expected+=(float)($i['expected_value']??0);
  }
  $recs=[];
  if($counts['appointment']>0)$recs[]='Appointments exist. Confirm these before new prospecting.';
  if($counts['call_queue']>0)$recs[]='Call queue exists. Prioritize callable contacts today.';
  if($counts['call_queue']===0 && $counts['appointment']===0)$recs[]='No active call/appointment pipeline yet. Feed V13.1 with real owner or inbound lead data.';
  $recs[]='This pipeline should become the truth layer for Mark: what is new, what was contacted, what needs follow-up, and what is closest to revenue.';

  $brief="V13.5 OPPORTUNITY PIPELINE\\n========================================\\n\\n";
  $brief.="Total Active:          ".count($all)."\\n";
  $brief.="Call Queue:            ".$counts['call_queue']."\\n";
  $brief.="Conversations:         ".$counts['conversation']."\\n";
  $brief.="Appointments:          ".$counts['appointment']."\\n";
  $brief.="Listing Opportunities: ".$counts['listing_opportunity']."\\n";
  $brief.="Signed Listings:       ".$counts['signed']."\\n";
  $brief.="Closed:                ".$counts['closed']."\\n";
  $brief.="Commission Pipeline:   $".number_format($pipeline,0)."\\n";
  $brief.="Expected Value:        $".number_format($expected,0)."\\n\\nTOP OPPORTUNITIES\\n----------------------------------------\\n";
  foreach(array_slice($all,0,15) as $n=>$o){
    $brief.=($n+1).". ".(($o['name']??'')?:'Unnamed')." — ".$o['pipeline_stage']." — ".$o['town']." — Expected $".number_format((float)($o['expected_value']??0),0)."\\n";
    $brief.="     Next: ".$o['next_step']."\\n\\n";
  }
  $brief.="JESSICA RECOMMENDS\\n----------------------------------------\\n";
  foreach($recs as $n=>$r){$brief.=($n+1).". {$r}\\n";}

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_active'=>count($all),'new_count'=>$counts['new'],'call_queue_count'=>$counts['call_queue'],
    'contacted_count'=>$counts['contacted'],'conversation_count'=>$counts['conversation'],'appointment_count'=>$counts['appointment'],
    'listing_opportunity_count'=>$counts['listing_opportunity'],'signed_count'=>$counts['signed'],'closed_count'=>$counts['closed'],
    'estimated_pipeline_value'=>round($pipeline,2),'expected_value'=>round($expected,2),'top_opportunities'=>array_slice($all,0,25),
    'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb135('POST','jessica_pipeline_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb135('PATCH','jessica_pipeline_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'pipeline_items_created'=>count($items),'total_active'=>count($all),'call_queue'=>$counts['call_queue'],'appointments'=>$counts['appointment'],'expected_value'=>round($expected,2),'inserted'=>$inserted,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>