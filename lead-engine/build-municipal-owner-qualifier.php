<?php
/**
 * V20.1.5 Municipal Owner Qualifier
 * Upload: /public_html/lead-engine/build-municipal-owner-qualifier.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function sbq($m,$ep,$p=null){
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

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $rows=sbq('GET','municipal_owner_records?select=*&order=updated_at.desc&limit=5000');
  if(!$rows['ok']){
    echo json_encode(['success'=>false,'error'=>'Could not read municipal_owner_records','details'=>$rows['body']],JSON_PRETTY_PRINT);
    exit;
  }

  $qualified=0; $needs_research=0; $mark_review=0; $top=[];

  foreach($rows['data'] as $r){
    $score=35;
    $owner=strtoupper($r['owner_name']??'');
    $address=$r['property_address']??'';
    $tax=(float)($r['total_tax']??0);
    $out=(float)($r['outstanding']??0);
    $first=(int)($r['first_seen_year']??0);
    $last=(int)($r['last_seen_year']??0);
    $estTenure=(int)($r['estimated_tenure_years']??0);
    $yearsObserved=(int)($r['years_observed']??0);

    /*
      Important:
      If your CSV only contains one tax year, Jessica cannot prove 7+ years from that file alone.
      She flags as "needs tenure research" instead of pretending.
    */
    $tenureConfidence='low';
    $sevenPlus=false;

    if($estTenure>=7 && $first>0){
      $sevenPlus=true;
      $tenureConfidence = ($yearsObserved>=2) ? 'medium' : 'low';
      $score += 25;
    }

    if($yearsObserved>=7){
      $sevenPlus=true;
      $tenureConfidence='high';
      $score += 35;
    } elseif($yearsObserved>=3){
      $tenureConfidence='medium';
      $score += 12;
    }

    if($tax>=10000) $score += 5;
    if($tax>=15000) $score += 10;
    if($tax>=25000) $score += 10;
    if($out>0) $score += 4;
    if(str_contains($owner,'LLC')) $score += 8;
    if(str_contains($owner,'TRUST')) $score += 8;
    if(str_contains($owner,'ESTATE')) $score += 12;
    if(strlen($owner)>3) $score += 3;

    $dnc=$r['dnc_status']??'not_checked';
    $realtor=$r['realtor_status']??'not_checked';
    if($dnc==='clear') $score+=8;
    if($realtor==='clear') $score+=8;
    if($dnc==='blocked' || $realtor==='blocked') $score-=50;

    $score=max(1,min(100,$score));

    $stage='research';
    $contact='not_ready';
    $action='Research ownership tenure and verify DNC/realtor exclusion before outreach.';
    $review='Jessica found this owner/property record but needs more public-record confirmation before direct contact.';

    if($sevenPlus){
      $stage='tenure_7_plus_candidate';
      $action='Verify owner tenure through town land records / assessor history, then check DNC and realtor exclusion.';
      $review='Possible 7+ year owner based on available municipal/tax-year signal. Tenure confidence: '.$tenureConfidence.'.';
      $needs_research++;
    }

    if($score>=75 && $sevenPlus){
      $stage='priority_research';
      $action='High-priority research candidate. Confirm ownership, mailing address, DNC status, and agent/realtor exclusion.';
      $review='Strong owner-intelligence candidate. Not outreach-ready until compliance checks are complete.';
      $qualified++;
    }

    if($score>=88 && $sevenPlus && $dnc==='clear' && $realtor==='clear'){
      $stage='mark_review';
      $contact='human_approval_required';
      $action='Mark review. If approved, prepare compliant mail/email/text strategy. Do not call until DNC/compliance is confirmed.';
      $review='Top owner candidate with 7+ year signal and cleared exclusions.';
      $mark_review++;
    }

    sbq('PATCH','municipal_owner_records?id=eq.'.rawurlencode($r['id']),[
      'owner_signal_score'=>$score,
      'priority_status'=>$stage,
      'qualification_stage'=>$stage,
      'contact_status'=>$contact,
      'tenure_confidence'=>$tenureConfidence,
      'jessica_review'=>$review,
      'mark_action'=>$action,
      'notes'=>'V20.1.5 qualifier: score '.$score.', tenure signal '.$estTenure.' estimated years, '.$yearsObserved.' observed tax years, confidence '.$tenureConfidence.'.',
      'updated_at'=>date('c')
    ]);

    if($score>=65){
      $top[]=[
        'owner_name'=>$r['owner_name'],
        'property_address'=>$address,
        'town'=>$r['town'],
        'score'=>$score,
        'estimated_tenure_years'=>$estTenure,
        'years_observed'=>$yearsObserved,
        'tenure_confidence'=>$tenureConfidence,
        'stage'=>$stage,
        'mark_action'=>$action
      ];
    }
  }

  usort($top,fn($a,$b)=>$b['score']<=>$a['score']);

  echo json_encode([
    'success'=>true,
    'records_reviewed'=>count($rows['data']),
    'tenure_7_plus_candidates'=>$needs_research,
    'priority_research'=>$qualified,
    'mark_review'=>$mark_review,
    'top_candidates'=>array_slice($top,0,50),
    'important_note'=>'If top_priority was empty before, your CSV likely only contained one tax year. Jessica can score records, but needs multi-year tax data or assessor/land-record history to confidently prove 7+ year ownership.'
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>