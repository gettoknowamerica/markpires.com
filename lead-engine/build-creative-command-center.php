<?php
/**
 * V17.6 Creative Command Center OS Builder
 * Upload: /public_html/lead-engine/build-creative-command-center.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function cc_sb($method,$endpoint,$payload=null){
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
function cc_rows($t,$q){$r=cc_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $clips=cc_rows('media_clip_intelligence','select=*&status=in.(needs_review,approved)&order=viral_score.desc,created_at.desc&limit=200');
  $created=0; $errors=[];

  foreach($clips as $c){
    $existing=cc_rows('media_editor_reviews','select=id&media_clip_intelligence_id=eq.'.rawurlencode($c['id']).'&limit=1');
    if(!empty($existing)) continue;

    $row=[
      'media_clip_intelligence_id'=>$c['id'],
      'media_project_id'=>$c['media_project_id'],
      'review_status'=>'needs_review',
      'editor_notes'=>'Jessica selected this clip for review. Check hook, caption accuracy, logo placement, CTA, and final brand feel.',
      'corrected_caption'=>$c['caption_text'] ?? '',
      'corrected_cta'=>$c['cta_text'] ?? '',
      'lower_third_text'=>'Mark Pyres | Discover CT',
      'title_card_text'=>$c['on_screen_text'] ?? $c['hook_line'] ?? '',
      'top_title_text'=>$c['hook_line'] ?? '',
      'logo_url'=>'/uploads/media/assets/mark-logo.png',
      'overlay_asset_url'=>'',
      'music_notes'=>$c['music_direction'] ?? '',
      'render_notes'=>$c['edit_direction'] ?? '',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    $r=cc_sb('POST','media_editor_reviews',[$row]);
    if($r['ok']) $created++; else $errors[]=$r['body'];
  }

  echo json_encode([
    'success'=>empty($errors),
    'clips_found'=>count($clips),
    'editor_reviews_created'=>$created,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>