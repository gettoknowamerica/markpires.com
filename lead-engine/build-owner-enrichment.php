<?php
/**
 * V20.2 Owner Enrichment Engine
 * Upload: /public_html/lead-engine/build-owner-enrichment.php
 *
 * This does NOT scrape restricted websites.
 * It creates compliant research queues, search queries, seller-signal scoring,
 * and human-review tasks from imported public municipal owner records.
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function oesb($m,$ep,$p=null){
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
    CURLOPT_TIMEOUT=>60
  ]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}

function q($s){ return trim(preg_replace('/\s+/', ' ', (string)$s)); }

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $limit = max(1, min(500, intval($_GET['limit'] ?? 150)));

  $records=oesb('GET','municipal_owner_records?select=*&order=owner_signal_score.desc,updated_at.desc&limit='.$limit);
  if(!$records['ok']){
    echo json_encode(['success'=>false,'error'=>'Could not read municipal_owner_records','details'=>$records['body']],JSON_PRETTY_PRINT);
    exit;
  }

  $queued=0; $updated=0; $skipped=0; $top=[]; $errors=[];

  foreach($records['data'] as $r){
    $id=$r['id'];
    $owner=q($r['owner_name']??'');
    $address=q($r['property_address']??'');
    $town=q($r['town']??'Fairfield') ?: 'Fairfield';
    if(!$address){ $skipped++; continue; }

    $tax=(float)($r['total_tax']??0);
    $score=(int)($r['owner_signal_score']??0);
    $tenure=(int)($r['estimated_tenure_years']??0);
    $yearsObserved=(int)($r['years_observed']??0);
    $ownerUpper=strtoupper($owner);

    $seller=30;
    if($tenure>=7) $seller+=20;
    if($tenure>=12) $seller+=10;
    if($yearsObserved>=3) $seller+=10;
    if($tax>=15000) $seller+=10;
    if($tax>=25000) $seller+=10;
    if(str_contains($ownerUpper,'TRUST')) $seller+=10;
    if(str_contains($ownerUpper,'ESTATE')) $seller+=15;
    if(str_contains($ownerUpper,'LLC')) $seller+=8;
    if(($r['outstanding']??0)>0) $seller+=4;
    $seller=max(1,min(100,$seller));

    $priority=max($score,$seller);

    $targets=[
      'town_assessor'=>'Search town assessor/property card for sale date, owner mailing address, building details.',
      'town_land_records'=>'Search town land records for deed date, grantee/grantor, transfer history.',
      'secretary_of_state'=>'If owner is LLC, verify entity principals when allowed.',
      'people_search'=>'Manual-only people-search review for possible contact data. Do not auto-call.',
      'dnc'=>'Run DNC before any phone outreach.',
      'realtor_exclusion'=>'Check if owner is an active realtor/agent before outreach.'
    ];

    $queries=[
      '"'.$address.'" "'.$town.'" assessor',
      '"'.$address.'" "'.$town.'" "sale date"',
      '"'.$address.'" "'.$owner.'"',
      '"'.$owner.'" "'.$town.'" Connecticut',
      '"'.$address.'" "land records"',
      '"'.$owner.'" "phone" "'.$town.'"',
      '"'.$owner.'" "email" "'.$town.'"'
    ];

    $status='research_ready';
    $human='not_ready';
    if($priority>=80) $status='needs_human_review';
    if(($r['dnc_status']??'not_checked')==='blocked' || ($r['realtor_status']??'not_checked')==='blocked'){
      $status='blocked';
      $human='blocked';
    }

    $payload=[
      'municipal_owner_record_id'=>$id,
      'property_address'=>$address,
      'town'=>$town,
      'owner_name'=>$owner,
      'enrichment_status'=>$status,
      'priority_score'=>$priority,
      'research_targets'=>$targets,
      'search_queries'=>$queries,
      'enrichment_notes'=>'Jessica queued this owner for compliant enrichment. Public record first, contact search second, DNC/realtor exclusion before outreach.',
      'estimated_years_owned'=>$tenure,
      'seller_signal_score'=>$seller,
      'compliance_status'=>'not_checked',
      'human_approval_status'=>$human,
      'updated_at'=>date('c')
    ];

    $existing=oesb('GET','owner_enrichment_queue?select=id&municipal_owner_record_id=eq.'.rawurlencode($id).'&limit=1');
    if($existing['ok'] && !empty($existing['data'])){
      $qid=$existing['data'][0]['id'];
      $res=oesb('PATCH','owner_enrichment_queue?id=eq.'.rawurlencode($qid),$payload);
      if($res['ok']) $updated++; else $errors[]=$res['body'];
    } else {
      $payload['created_at']=date('c');
      $res=oesb('POST','owner_enrichment_queue',[$payload]);
      if($res['ok']) $queued++; else $errors[]=$res['body'];
    }

    oesb('PATCH','municipal_owner_records?id=eq.'.rawurlencode($id),[
      'enrichment_status'=>$status,
      'enrichment_priority'=>$priority,
      'enrichment_queries'=>$queries,
      'seller_signal_score'=>$seller,
      'updated_at'=>date('c')
    ]);

    if($priority>=70){
      $top[]=[
        'owner'=>$owner,
        'address'=>$address,
        'town'=>$town,
        'priority_score'=>$priority,
        'seller_signal_score'=>$seller,
        'estimated_years_owned'=>$tenure,
        'status'=>$status,
        'next_action'=>'Research assessor + land records, then DNC/realtor exclusion.'
      ];
    }
  }

  usort($top, fn($a,$b)=>$b['priority_score']<=>$a['priority_score']);

  echo json_encode([
    'success'=>empty($errors),
    'records_reviewed'=>count($records['data']),
    'queued'=>$queued,
    'updated'=>$updated,
    'skipped'=>$skipped,
    'top_research_targets'=>array_slice($top,0,50),
    'errors'=>array_slice($errors,0,5),
    'compliance_note'=>'This creates research tasks and search queries. Do not auto-scrape restricted sites or auto-call. Verify source permissions, DNC, realtor exclusion, and human approval before outreach.'
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>