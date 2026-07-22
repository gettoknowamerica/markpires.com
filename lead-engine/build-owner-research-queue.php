<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$m,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>120
  ]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function owner_from_why($why){
  if(preg_match('/Owner:\\s*([^\\.\\|]+)/i',(string)$why,$m)) return trim($m[1]);
  return '';
}
function q($s){ return trim(preg_replace('/\s+/',' ',(string)$s)); }

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $limit=max(1,min(1000,(int)($_GET['limit']??250)));
  $mode=$_GET['mode']??'replace';

  if($mode==='replace'){
    sb('DELETE','owner_research_queue?id=not.is.null');
  }

  $opps=sb('GET','jessica_opportunity_engine?select=*&opportunity_type=eq.goliath_unique_seller&order=revenue_score.desc,urgency_score.desc&limit='.$limit)['data'];
  if(!$opps){
    $opps=sb('GET','jessica_opportunity_engine?select=*&opportunity_type=eq.failed_never_sold&order=revenue_score.desc,urgency_score.desc&limit='.$limit)['data'];
  }

  $payload=[];
  foreach($opps as $o){
    $address=q($o['address']??'');
    $town=q($o['town']??'');
    if(!$address) continue;
    $owner=owner_from_why($o['why_now']??'');
    $base=q(($owner?$owner.' ':'').$address.' '.$town.' CT');

    $payload[]=[
      'source_opportunity_id'=>(string)($o['id']??''),
      'queue_status'=>'needs_research',
      'priority_score'=>(int)($o['revenue_score']??0),
      'owner_name'=>$owner,
      'address'=>$address,
      'town'=>$town,
      'why_now'=>$o['why_now']??'',
      'recommended_action'=>$o['recommended_action']??'',
      'google_query'=>$base.' property owner phone',
      'assessor_query'=>$address.' '.$town.' CT assessor field card owner',
      'people_search_query'=>$base.' phone number',
      'mark_review_status'=>'not_reviewed',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    if(count($payload)>=200){
      sb('POST','owner_research_queue',$payload);
      $payload=[];
    }
  }
  if($payload) sb('POST','owner_research_queue',$payload);

  echo json_encode([
    'success'=>true,
    'queue_created_from'=>count($opps),
    'mode'=>$mode,
    'message'=>'Owner research queue created. Use dashboard to open research links, fill phone/email manually, and mark review status.'
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>