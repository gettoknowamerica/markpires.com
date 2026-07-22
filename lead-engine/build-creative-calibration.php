<?php
/**
 * V16.0 Creative Calibration Builder
 * Upload: /public_html/lead-engine/build-creative-calibration.php
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

  function sb160($method,$endpoint,$payload=null){
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
    $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $data=json_decode($body,true);
    return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'data'=>is_array($data)?$data:[]];
  }
  function rows160($t,$q){$r=sb160('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

  $errors=[]; $updatedCreative=0; $updatedMine=0;

  $creative=rows160('creative_intelligence_assets','select=*&status=eq.active&limit=2000');
  foreach($creative as $a){
    $title=strtolower(($a['title']??'').' '.($a['description']??'').' '.($a['brand_pillar']??''));
    $brand=$a['brand_pillar']??'mark_pires';
    $score=(int)($a['creative_impact_score']??0);
    $rec=$a['recommendation']??'review';

    if(in_array($brand,['discover_ct','house_detective'],true)){
      $score=max($score,86); $rec='repurpose';
    } elseif($brand==='seller_authority' || str_contains($title,'home value') || str_contains($title,'seller') || str_contains($title,'valuation')) {
      $score=max($score,84); $rec='create';
    } elseif($brand==='beatseat') {
      $score=max($score,76); $rec='repurpose';
    } elseif(str_contains($title,'blog') || str_contains($title,'guide')) {
      $score=max($score,80); $rec='create';
    }

    if($score > (int)($a['creative_impact_score']??0) || $rec !== ($a['recommendation']??'')){
      $r=sb160('PATCH','creative_intelligence_assets?id=eq.'.rawurlencode($a['id']),[
        'creative_impact_score'=>$score,
        'emotional_score'=>max((int)($a['emotional_score']??0),76),
        'authority_score'=>max((int)($a['authority_score']??0),80),
        'lead_gen_score'=>max((int)($a['lead_gen_score']??0),($brand==='seller_authority'?82:65)),
        'local_relevance_score'=>max((int)($a['local_relevance_score']??0),78),
        'shareability_score'=>max((int)($a['shareability_score']??0),75),
        'evergreen_score'=>max((int)($a['evergreen_score']??0),75),
        'repurpose_score'=>max((int)($a['repurpose_score']??0),80),
        'recommendation'=>$rec,
        'calibrated_at'=>date('c'),
        'updated_at'=>date('c')
      ]);
      if($r['ok'])$updatedCreative++; else $errors[]=['creative'=>$a['id'],'body'=>$r['body']];
    }
  }

  $mine=rows160('content_mine_assets','select=*&status=eq.active&limit=2000');
  foreach($mine as $m){
    $brand=$m['brand_pillar']??'mark_pires';
    $use='repost'; $score=82;
    if($brand==='house_detective' || $brand==='discover_ct'){$use='short';$score=86;}
    elseif($brand==='seller_authority'){$use='ad';$score=88;}
    elseif($brand==='buyer_authority'){$use='blog';$score=80;}
    elseif($brand==='beatseat'){$use='repost';$score=78;}

    $r=sb160('PATCH','content_mine_assets?id=eq.'.rawurlencode($m['id']),[
      'total_content_mine_score'=>max((int)($m['total_content_mine_score']??0),$score),
      'repurpose_score'=>max((int)($m['repurpose_score']??0),82),
      'lead_gen_score'=>max((int)($m['lead_gen_score']??0),($brand==='seller_authority'?84:65)),
      'local_authority_score'=>max((int)($m['local_authority_score']??0),82),
      'emotional_score'=>max((int)($m['emotional_score']??0),78),
      'evergreen_score'=>max((int)($m['evergreen_score']??0),75),
      'recommended_use'=>$use,
      'recommended_plan'=>($brand==='seller_authority'
        ? 'Turn into seller ad creative, short video, blog/social caption, and valuation funnel CTA.'
        : 'Repurpose into 3 short clips, 3 social captions, 1 story post, and 1 authority-building post.'),
      'calibrated_at'=>date('c'),
      'updated_at'=>date('c')
    ]);
    if($r['ok'])$updatedMine++; else $errors[]=['mine'=>$m['id'],'body'=>$r['body']];
  }

  echo json_encode([
    'success'=>empty($errors),
    'updated_creative_assets'=>$updatedCreative,
    'updated_content_mine_assets'=>$updatedMine,
    'next_run_order'=>[
      '/lead-engine/build-creative-intelligence-director.php?key=...',
      '/lead-engine/build-content-mine-director.php?key=...',
      '/lead-engine/build-ad-launch-director.php?key=...',
      '/lead-engine/build-campaign-command-center.php?key=...',
      '/lead-engine/build-creative-generation-studio.php?key=...'
    ],
    'errors'=>$errors
  ], JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>