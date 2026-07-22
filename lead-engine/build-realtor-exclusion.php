<?php
/**
 * V14.3 Realtor Exclusion Engine
 * Upload: /public_html/lead-engine/build-realtor-exclusion.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb143($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows143($table,$query){ $r=sb143('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function phone143($p){ $p=preg_replace('/[^0-9]/','',(string)$p); if(strlen($p)===11 && substr($p,0,1)==='1')$p=substr($p,1); return $p; }
  function norm143($s){ $s=strtolower(trim((string)$s)); $s=preg_replace('/[^a-z0-9 ]/',' ',$s); return trim(preg_replace('/\s+/',' ',$s)); }
  function name_parts143($name){
    $n=norm143($name); $parts=array_values(array_filter(explode(' ',$n)));
    return ['full'=>$n,'first'=>$parts[0]??'','last'=>count($parts)>1?$parts[count($parts)-1]:''];
  }
  function match_agent143($record,$agentsByPhone,$agentsByEmail,$agentsByName){
    $phone=phone143($record['phone']??$record['owner_phone']??$record['enriched_phone']??$record['current_phone']??'');
    $email=strtolower(trim((string)($record['email']??$record['owner_email']??$record['enriched_email']??$record['current_email']??'')));
    $name=$record['name']??$record['owner_name']??'';
    if($phone && isset($agentsByPhone[$phone])) return ['agent'=>$agentsByPhone[$phone],'type'=>'phone','confidence'=>98];
    if($email && isset($agentsByEmail[$email])) return ['agent'=>$agentsByEmail[$email],'type'=>'email','confidence'=>99];
    $np=name_parts143($name);
    if($np['full'] && isset($agentsByName[$np['full']])) return ['agent'=>$agentsByName[$np['full']],'type'=>'exact_name','confidence'=>88];
    if($np['first'] && $np['last']){
      $key=$np['first'].' '.$np['last'];
      if(isset($agentsByName[$key])) return ['agent'=>$agentsByName[$key],'type'=>'name','confidence'=>82];
    }
    return null;
  }
  function make_match143($sourceTable,$r,$m){
    $agent=$m['agent'];
    $phone=phone143($r['phone']??$r['owner_phone']??$r['enriched_phone']??$r['current_phone']??'');
    $email=strtolower(trim((string)($r['email']??$r['owner_email']??$r['enriched_email']??$r['current_email']??'')));
    $name=$r['name']??$r['owner_name']??'';
    $addr=$r['address']??$r['property_address']??'';
    return [
      'match_date'=>date('Y-m-d'),
      'source_table'=>$sourceTable,
      'source_id'=>(string)($r['id']??''),
      'source_name'=>$name,
      'source_phone'=>$phone,
      'source_email'=>$email,
      'source_address'=>$addr,
      'source_town'=>$r['town']??'',
      'agent_id'=>$agent['id']??null,
      'agent_name'=>$agent['full_name']??'',
      'office_name'=>$agent['office_name']??'',
      'match_type'=>$m['type'],
      'match_confidence'=>$m['confidence'],
      'recommended_action'=>'Exclude from seller/prospect call queue: matched active Realtor/agent list.',
      'raw_payload'=>['source'=>$r,'agent'=>$agent],
      'status'=>'active',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
  }

  $agents=rows143('agent_master_list','select=*&status=eq.active&limit=10000');
  $agentsByPhone=[]; $agentsByEmail=[]; $agentsByName=[];
  foreach($agents as $a){
    $p=phone143($a['office_phone']??''); if($p) $agentsByPhone[$p]=$a;
    $mp=phone143($a['mobile_phone']??''); if($mp) $agentsByPhone[$mp]=$a;
    $e=strtolower(trim((string)($a['email']??''))); if($e) $agentsByEmail[$e]=$a;
    $n=norm143($a['full_name']??''); if($n) $agentsByName[$n]=$a;
  }

  $matches=[]; $flagged=['seller'=>0,'enrichment'=>0,'acquisition'=>0,'pool'=>0]; $errors=[];

  $sources = [
    'seller_opportunity_sources' => rows143('seller_opportunity_sources','select=*&status=eq.active&realtor_status=neq.match&limit=1000'),
    'contact_enrichment_queue' => rows143('contact_enrichment_queue','select=*&status=eq.active&realtor_status=neq.match&limit=1000'),
    'contact_acquisition_candidates' => rows143('contact_acquisition_candidates','select=*&realtor_match=neq.true&limit=1000'),
    'approved_contact_pool' => rows143('approved_contact_pool','select=*&status=eq.active&realtor_flag=neq.true&limit=1000')
  ];

  foreach($sources as $table=>$records){
    foreach($records as $r){
      $m=match_agent143($r,$agentsByPhone,$agentsByEmail,$agentsByName);
      if(!$m) continue;
      $matches[]=make_match143($table,$r,$m);

      if($table==='seller_opportunity_sources'){
        $res=sb143('PATCH','seller_opportunity_sources?id=eq.'.rawurlencode($r['id']),[
          'realtor_status'=>'match','call_eligible'=>false,'recommended_action'=>'Excluded: matched Realtor/agent list.','updated_at'=>date('c')
        ]);
        if($res['ok'])$flagged['seller']++; else $errors[]=['seller_patch'=>$res['body']];
      } elseif($table==='contact_enrichment_queue'){
        $res=sb143('PATCH','contact_enrichment_queue?id=eq.'.rawurlencode($r['id']),[
          'realtor_status'=>'match','call_eligible'=>false,'enrichment_status'=>'rejected','recommended_action'=>'Excluded: matched Realtor/agent list.','updated_at'=>date('c')
        ]);
        if($res['ok'])$flagged['enrichment']++; else $errors[]=['enrichment_patch'=>$res['body']];
      } elseif($table==='contact_acquisition_candidates'){
        $res=sb143('PATCH','contact_acquisition_candidates?id=eq.'.rawurlencode($r['id']),[
          'realtor_checked'=>true,'realtor_match'=>true,'call_eligible'=>false,'approved_contact'=>false,'status'=>'rejected','recommended_action'=>'Excluded: matched Realtor/agent list.','updated_at'=>date('c')
        ]);
        if($res['ok'])$flagged['acquisition']++; else $errors[]=['acq_patch'=>$res['body']];
      } elseif($table==='approved_contact_pool'){
        $res=sb143('PATCH','approved_contact_pool?id=eq.'.rawurlencode($r['id']),[
          'realtor_flag'=>true,'do_not_call'=>true,'call_eligible'=>false,'status'=>'rejected','recommended_action'=>'Excluded: matched Realtor/agent list.','updated_at'=>date('c')
        ]);
        if($res['ok'])$flagged['pool']++; else $errors[]=['pool_patch'=>$res['body']];
      }
    }
  }

  $inserted=[];
  foreach(array_chunk(array_slice($matches,0,1000),100) as $chunk){
    $r=sb143('POST','realtor_exclusion_matches',$chunk);
    if($r['ok'])$inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['match_insert'=>$r['body']];
  }

  $allMatches=rows143('realtor_exclusion_matches','select=*&status=eq.active&order=created_at.desc&limit=1000');
  $recs=[
    'Re-run this after every owner/contact import before approving any call queue.',
    'Phone/email matches should be treated as hard excludes.',
    'Name-only matches should be reviewed if the owner name is common.'
  ];

  $brief="V14.3 REALTOR EXCLUSION ENGINE\\n========================================\\n\\n";
  $brief.="Total Agents:          ".count($agents)."\\n";
  $brief.="Matches This Run:      ".count($matches)."\\n";
  $brief.="Total Active Matches:  ".count($allMatches)."\\n";
  $brief.="Seller Sources Flagged: ".$flagged['seller']."\\n";
  $brief.="Enrichment Flagged:    ".$flagged['enrichment']."\\n";
  $brief.="Acquisition Flagged:   ".$flagged['acquisition']."\\n";
  $brief.="Approved Pool Flagged: ".$flagged['pool']."\\n\\n";
  $brief.="TOP MATCHES\\n----------------------------------------\\n";
  foreach(array_slice($allMatches,0,15) as $i=>$m){
    $brief.=($i+1).". ".($m['source_name']?:$m['source_phone'])." matched ".$m['agent_name']." — ".$m['match_type']." — ".$m['match_confidence']."%\\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_agents'=>count($agents),'matches_found'=>count($matches),
    'seller_sources_flagged'=>$flagged['seller'],'enrichment_flagged'=>$flagged['enrichment'],'acquisition_flagged'=>$flagged['acquisition'],
    'approved_pool_flagged'=>$flagged['pool'],'top_matches'=>array_slice($allMatches,0,25),'recommendations'=>$recs,
    'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb143('POST','realtor_exclusion_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb143('PATCH','realtor_exclusion_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'total_agents'=>count($agents),'matches_this_run'=>count($matches),'flagged'=>$flagged,'inserted'=>$inserted,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>