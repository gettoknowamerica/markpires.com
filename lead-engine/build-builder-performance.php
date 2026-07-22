<?php
/**
 * V11.6 Builder Performance Profile Builder
 * Upload: /public_html/lead-engine/build-builder-performance.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb116p($method,$endpoint,$payload=null){
  $url = rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[] = $method === 'POST'
    ? 'Prefer: resolution=merge-duplicates,return=representation'
    : 'Prefer: return=representation';

  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function pct116($num,$den){ return $den>0 ? round($num/$den,4) : 0; }
function top_counts116($rows,$field){
  $counts=[];
  foreach($rows as $r){
    $v=trim((string)($r[$field]??''));
    if(!$v) continue;
    $counts[$v]=($counts[$v]??0)+1;
  }
  arsort($counts);
  $out=[];
  foreach(array_slice($counts,0,6,true) as $k=>$v) $out[]=['name'=>$k,'count'=>$v];
  return $out;
}

$builders=sb116p('GET','builder_contacts?select=*&status=eq.active&limit=1000')['data'];
$created=[]; $errors=[];

foreach($builders as $b){
  if(!is_array($b) || empty($b['id'])) continue;
  $bid=$b['id'];

  $matches=sb116p('GET','builder_opportunity_matches?select=*&builder_contact_id=eq.'.rawurlencode($bid).'&limit=1000')['data'];
  $intros=sb116p('GET','builder_intro_outreach?select=*&builder_contact_id=eq.'.rawurlencode($bid).'&limit=1000')['data'];
  $pipeline=sb116p('GET','builder_pipeline?select=*&builder_contact_id=eq.'.rawurlencode($bid).'&limit=1000')['data'];

  $totalMatches=count($matches);
  $totalIntros=count($intros);
  $responses=0; $siteVisits=0; $offers=0; $contracts=0; $closed=0; $active=0;
  $refPotential=0; $expected=0; $closedValue=0;

  foreach($pipeline as $p){
    $stage=$p['pipeline_stage']??'new';
    if(!in_array($stage,['new','intro_sent','dead'],true)) $responses++;
    if($stage==='site_visit') $siteVisits++;
    if(in_array($stage,['offer_possible','offer_made'],true)) $offers++;
    if($stage==='under_contract') $contracts++;
    if($stage==='closed') $closed++;
    if(!in_array($stage,['closed','dead'],true)) $active++;

    $ref=(float)($p['referral_potential']??0);
    $prob=(int)($p['deal_probability']??0);
    $refPotential += $ref;
    $expected += $ref * ($prob/100);
    if($stage==='closed') $closedValue += $ref;
  }

  /* Treat introduced/contacted matches as response signals if no pipeline row yet */
  foreach($matches as $m){
    if(in_array(($m['status']??''),['introduced','contacted'],true)) $responses++;
  }

  $responseRate=pct116($responses,max(1,$totalIntros ?: $totalMatches));
  $conversionRate=pct116($offers+$contracts+$closed,max(1,$totalMatches));
  $closeRate=pct116($closed,max(1,$totalMatches));

  $tier='Tier 3';
  if($closed>0 || $expected>=25000 || $responseRate>=0.65) $tier='Tier 1';
  elseif($expected>=10000 || $responseRate>=0.35 || $active>=2) $tier='Tier 2';

  $allRows=array_merge($matches,$pipeline);
  $towns=top_counts116($allRows,'opportunity_town');
  $types=top_counts116($allRows,'opportunity_type');

  $notes="Builder ranked {$tier}. Response rate ".round($responseRate*100,1)."%. Expected referral value $".number_format($expected,0).".";

  $payload=[[
    'builder_contact_id'=>$bid,
    'builder_name'=>$b['name']??'',
    'company'=>$b['company']??'',
    'phone'=>$b['phone']??'',
    'email'=>$b['email']??'',
    'towns'=>$b['towns']??'',
    'buyer_profile'=>$b['buyer_profile']??'',
    'total_matches'=>$totalMatches,
    'total_intros'=>$totalIntros,
    'total_responses'=>$responses,
    'total_site_visits'=>$siteVisits,
    'total_offers'=>$offers,
    'total_contracts'=>$contracts,
    'total_closed'=>$closed,
    'active_pipeline'=>$active,
    'response_rate'=>$responseRate,
    'conversion_rate'=>$conversionRate,
    'close_rate'=>$closeRate,
    'total_referral_potential'=>$refPotential,
    'expected_referral_value'=>$expected,
    'closed_referral_value'=>$closedValue,
    'tier'=>$tier,
    'preferred_towns'=>$towns,
    'preferred_opportunity_types'=>$types,
    'performance_notes'=>$notes,
    'raw_stats'=>[
      'matches'=>$matches,
      'intros'=>$intros,
      'pipeline'=>$pipeline
    ],
    'updated_at'=>date('c')
  ]];

  $r=sb116p('POST','builder_performance_profiles',$payload);
  if($r['ok']) $created[]=['builder'=>$b['name']??'','tier'=>$tier,'expected_referral_value'=>$expected];
  else $errors[]=['builder'=>$b['name']??'','http'=>$r['http'],'body'=>$r['body']];
}

echo json_encode([
  'success'=>empty($errors),
  'profiles_updated'=>count($created),
  'profiles'=>$created,
  'errors'=>$errors
],JSON_PRETTY_PRINT);
?>