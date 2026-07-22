<?php
/**
 * V17.5 Canva + Approval + Blotato Bridge
 * Upload: /public_html/lead-engine/build-canva-bridge.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function cb_sb($method,$endpoint,$payload=null){
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
    CURLOPT_TIMEOUT=>60
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function cb_rows($t,$q){$r=cb_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $clips=cb_rows('media_clip_intelligence','select=*&status=in.(needs_review,approved)&order=viral_score.desc,created_at.desc&limit=100');
  $created=0; $errors=[];

  foreach($clips as $c){
    $existing=cb_rows('media_canva_briefs','select=id&media_clip_intelligence_id=eq.'.rawurlencode($c['id']).'&limit=1');
    if(!empty($existing)) continue;

    $brand='discover_ct';
    $project=[];
    if(!empty($c['media_project_id'])){
      $p=cb_rows('media_projects','select=*&id=eq.'.rawurlencode($c['media_project_id']).'&limit=1');
      $project=$p[0]??[];
      $brand=$project['brand_pillar']??$brand;
    }

    $color='black, white, gold, premium Connecticut media style';
    if($brand==='house_detective') $color='black, white, smoky gray, gold, cinema-noir detective style';
    if($brand==='seller_authority') $color='black, ivory, gold, luxury real estate editorial style';

    $prompt="Create a vertical 1080x1920 social video cover/card and end-card concept for {$brand}. ".
      "Headline: {$c['on_screen_text']}. Hook: {$c['hook_line']}. ".
      "Use bold readable mobile-first typography, strong safe margins, premium logo placement, and a CTA end card. ".
      "Make it unmistakably Mark Pyres / Discover CT / House Detective content.";

    $row=[
      'media_project_id'=>$c['media_project_id'],
      'media_clip_intelligence_id'=>$c['id'],
      'brief_title'=>$c['clip_title'].' — Canva Brief',
      'design_type'=>'vertical_short',
      'canva_prompt'=>$prompt,
      'brand_pillar'=>$brand,
      'headline'=>$c['on_screen_text'],
      'subheadline'=>$c['hook_line'],
      'cta_text'=>$c['cta_text'],
      'logo_direction'=>$c['logo_direction'],
      'color_direction'=>$color,
      'asset_notes'=>"Thumbnail prompt: {$c['thumbnail_prompt']}\nEdit direction: {$c['edit_direction']}\nMusic: {$c['music_direction']}",
      'approval_status'=>'needs_review',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    $r=cb_sb('POST','media_canva_briefs',[$row]);
    if($r['ok']) $created++; else $errors[]=$r['body'];
  }

  echo json_encode([
    'success'=>empty($errors),
    'clips_found'=>count($clips),
    'canva_briefs_created'=>$created,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>