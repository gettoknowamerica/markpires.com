<?php
/**
 * V17.0 Jessica Media Director Processor
 * Upload: /public_html/lead-engine/build-media-director.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function md_sb($method,$endpoint,$payload=null){
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
function md_rows($t,$q){$r=md_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $projects=md_rows('media_projects','select=*&status=in.(uploaded,analyzed)&order=created_at.desc&limit=100');
  $created=0; $updated=0; $errors=[];

  foreach($projects as $p){
    $id=$p['id'];
    $title=$p['title'] ?: 'Untitled Media Project';
    $brand=$p['brand_pillar'] ?: 'discover_ct';
    $town=$p['town'] ?: '';
    $notes=strtolower(($p['notes']??'').' '.$title.' '.$brand.' '.$town);

    $baseScore=55;
    if(str_contains($notes,'discover')) $baseScore+=12;
    if(str_contains($notes,'house detective') || str_contains($notes,'noir')) $baseScore+=14;
    if(str_contains($notes,'seller') || str_contains($notes,'valuation')) $baseScore+=10;
    if(str_contains($notes,'food') || str_contains($notes,'restaurant') || str_contains($notes,'local')) $baseScore+=8;
    if($town) $baseScore+=5;
    $viral=min(98,$baseScore);

    $angle = 'Local curiosity hook';
    if($brand==='house_detective') $angle='Noir mystery hook: make the home feel like a case worth solving';
    elseif($brand==='seller_authority') $angle='Seller pain-point hook: what local homeowners need to know now';
    elseif($brand==='discover_ct') $angle='Community-first hook: why locals love this place';

    $cta = 'Call or text Mark Pyres at 203-247-2655 for local real estate guidance.';
    if($brand==='house_detective') $cta='Want your listing to stand out? Call The House Detective, Mark Pyres: 203-247-2655.';
    if($brand==='seller_authority') $cta='Curious what your home is worth? Request a private value review with Mark Pyres.';

    md_sb('PATCH','media_projects?id=eq.'.rawurlencode($id),[
      'status'=>'analyzed',
      'viral_score'=>$viral,
      'lead_score'=>min(100,$viral-5),
      'story_score'=>min(100,$viral+3),
      'recommended_angle'=>$angle,
      'recommended_cta'=>$cta,
      'updated_at'=>date('c')
    ]);
    $updated++;

    $existing=md_rows('media_clip_plans','select=id&media_project_id=eq.'.rawurlencode($id).'&limit=1');
    if(!empty($existing)) continue;

    $clipRows=[
      [
        'media_project_id'=>$id,
        'clip_title'=>$title.' — Hook Clip',
        'clip_type'=>'short',
        'start_seconds'=>0,
        'end_seconds'=>35,
        'hook'=>'Start with the strongest local curiosity or emotional moment.',
        'caption_style'=>'bold kinetic captions',
        'overlay_text'=>'Fairfield County locals know this...',
        'cta_text'=>$cta,
        'soundtrack_mood'=>'upbeat cinematic local',
        'visual_style'=>$brand==='house_detective'?'cinema noir, film grain, detective title cards':'premium local media, warm, polished, social-first',
        'ken_burns'=>true,
        'viral_score'=>$viral,
        'status'=>'needs_review',
        'raw_payload'=>['generated_by'=>'v17_media_director']
      ],
      [
        'media_project_id'=>$id,
        'clip_title'=>$title.' — Lead Magnet Clip',
        'clip_type'=>'reel',
        'start_seconds'=>35,
        'end_seconds'=>75,
        'hook'=>'Connect the story to a real estate question or local lifestyle decision.',
        'caption_style'=>'clean premium captions',
        'overlay_text'=>'Thinking about a move in Fairfield County?',
        'cta_text'=>$cta,
        'soundtrack_mood'=>'confident modern real estate',
        'visual_style'=>'editorial, clean logo placement, strong CTA ending',
        'ken_burns'=>true,
        'viral_score'=>max(1,$viral-4),
        'status'=>'needs_review',
        'raw_payload'=>['generated_by'=>'v17_media_director']
      ],
      [
        'media_project_id'=>$id,
        'clip_title'=>$title.' — Episode Outline',
        'clip_type'=>'episode',
        'start_seconds'=>0,
        'end_seconds'=>600,
        'hook'=>'Build a 5-10 minute story arc with intro, best moments, local context, and CTA.',
        'caption_style'=>'lower-third captions, chapter cards',
        'overlay_text'=>'Discover Connecticut / House Detective Episode',
        'cta_text'=>$cta,
        'soundtrack_mood'=>'cinematic documentary',
        'visual_style'=>'Ken Burns photos, B-roll, branded title cards, chapter structure',
        'ken_burns'=>true,
        'viral_score'=>max(1,$viral-8),
        'status'=>'planned',
        'raw_payload'=>['generated_by'=>'v17_media_director']
      ]
    ];
    $r=md_sb('POST','media_clip_plans',$clipRows);
    if($r['ok']) $created+=count($clipRows); else $errors[]=$r['body'];
  }

  echo json_encode([
    'success'=>empty($errors),
    'projects_processed'=>count($projects),
    'projects_updated'=>$updated,
    'clip_plans_created'=>$created,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>