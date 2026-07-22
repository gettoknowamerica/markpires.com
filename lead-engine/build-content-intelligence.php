<?php
/**
 * V17.4 Jessica Content Intelligence Engine
 * Upload: /public_html/lead-engine/build-content-intelligence.php
 *
 * Converts uploaded project notes/transcripts into ranked clip intelligence,
 * captions, CTAs, thumbnail prompts, and episode storylines.
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function ci_sb($method,$endpoint,$payload=null){
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
function ci_rows($t,$q){$r=ci_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
function ci_has($text,$term){return stripos((string)$text,(string)$term)!==false;}
function ci_words($text){return preg_split('/\s+/', trim(strip_tags((string)$text)));}

function ci_score_text($text,$brand){
  $t=strtolower($text);
  $score=45;
  $curiosity=['secret','hidden','nobody','mistake','truth','surprise','before','after','why','how','best','worst','locals','never','always','case','clue','value','worth'];
  $emotion=['love','family','dream','fear','stress','excited','beautiful','story','community','home','move','memory','legacy','local','favorite'];
  $authority=['market','value','seller','buyer','realtor','strategy','negotiation','pricing','fairfield','greenwich','westport','darien','new canaan','stamford'];
  foreach($curiosity as $w){ if(str_contains($t,$w)) $score+=3; }
  foreach($emotion as $w){ if(str_contains($t,$w)) $score+=2; }
  foreach($authority as $w){ if(str_contains($t,$w)) $score+=2; }
  if($brand==='house_detective') $score+=8;
  if($brand==='discover_ct') $score+=7;
  if($brand==='seller_authority') $score+=6;
  return min(100,$score);
}

function ci_brand_style($brand){
  if($brand==='house_detective'){
    return [
      'audience'=>'homeowners, listing viewers, local real estate fans',
      'emotion'=>'mystery and curiosity',
      'cta'=>'Want your home marketed like a case worth solving? Call or text Mark Pyres at 203-247-2655.',
      'logo'=>'House Detective logo bottom right, noir case-file end card',
      'music'=>'cinematic noir, subtle suspense, detective rhythm',
      'edit'=>'film grain, punch-ins, black-and-white title cards, case-file overlays'
    ];
  }
  if($brand==='seller_authority'){
    return [
      'audience'=>'Fairfield County homeowners and future sellers',
      'emotion'=>'clarity and urgency',
      'cta'=>'Curious what your home is worth? Request a private local value review with Mark Pyres.',
      'logo'=>'Mark Pyres logo bottom right, clean luxury end card',
      'music'=>'confident modern luxury real estate bed',
      'edit'=>'clean cuts, premium captions, data callouts, strong CTA card'
    ];
  }
  return [
    'audience'=>'locals, relocation buyers, community followers, Fairfield County fans',
    'emotion'=>'local pride and curiosity',
    'cta'=>'Want the local advantage? Follow Discover CT and call or text Mark Pyres at 203-247-2655.',
    'logo'=>'Discover CT / Mark Pyres logo bottom right',
    'music'=>'warm upbeat social documentary',
    'edit'=>'fast street energy, warm local B-roll, kinetic captions, quick smiles and reveals'
  ];
}

function ci_make_clips($project,$intel_id,$transcript){
  $brand=$project['brand_pillar'] ?? 'discover_ct';
  $style=ci_brand_style($brand);
  $title=$project['title'] ?? 'Jessica Clip';
  $town=$project['town'] ?? 'Fairfield County';
  $score=ci_score_text($transcript.' '.$title,$brand);

  if($brand==='house_detective'){
    $templates=[
      ['This house was hiding a clue buyers would remember.', 'The clue was hiding in plain sight.', 'CASE FILE: What makes this home different?', 'mystery'],
      ['The listing didn’t need more noise. It needed a story.', 'Most homes are listed. This one becomes a case.', 'THE HOUSE DETECTIVE METHOD', 'authority'],
      ['Before a buyer falls in love, they need a reason to remember.', 'The story is what makes the home stick.', 'EVERY HOME HAS A CASE', 'emotion']
    ];
  } elseif($brand==='seller_authority'){
    $templates=[
      ['Most homeowners guess value. Mark looks for signals.', 'Online estimates miss what local buyers actually pay for.', 'WHAT IS YOUR HOME REALLY WORTH?', 'urgency'],
      ['Before you sell, understand this Fairfield County signal.', 'This is where pricing strategy starts.', 'SELLER SIGNALS', 'authority'],
      ['The number is only part of the story.', 'Condition, timing, buyer demand, and presentation all matter.', 'PRIVATE VALUE REVIEW', 'trust']
    ];
  } else {
    $templates=[
      ['This is why locals love '.$town.'.', 'A local moment says more than any listing description.', 'DISCOVER '.$town, 'belonging'],
      ['Save this for your next Fairfield County day.', 'The best local stories are hiding in plain sight.', 'DISCOVER CT', 'curiosity'],
      ['If you are moving to Connecticut, this is the kind of detail that matters.', 'Lifestyle is not just a search filter.', 'LOCAL ADVANTAGE', 'relocation']
    ];
  }

  $rows=[];
  $rank=1;
  foreach($templates as $tpl){
    $rows[]=[
      'media_project_id'=>$project['id'],
      'content_intelligence_id'=>$intel_id,
      'clip_rank'=>$rank,
      'clip_title'=>$title.' — Clip '.$rank,
      'platform'=>$rank===1?'TikTok / Reels / Shorts':'Instagram Reels / Facebook / Shorts',
      'start_seconds'=>($rank-1)*20,
      'end_seconds'=>($rank*20)+15,
      'hook_line'=>$tpl[0],
      'caption_text'=>$tpl[1]."\n\n".$style['cta'],
      'on_screen_text'=>$tpl[2],
      'cta_text'=>$style['cta'],
      'hashtags'=>'#DiscoverCT #FairfieldCounty #ConnecticutRealEstate #MarkPyres #LocalConnecticut',
      'thumbnail_prompt'=>'Create a premium vertical thumbnail with bold text: "'.$tpl[2].'". Use '.$style['edit'].'.',
      'logo_direction'=>$style['logo'],
      'music_direction'=>$style['music'],
      'edit_direction'=>$style['edit'].' Add bold captions, safe margins, quick first-frame hook, and CTA end card.',
      'emotional_driver'=>$tpl[3],
      'curiosity_gap'=>$tpl[0],
      'viral_score'=>min(100,$score+(4-$rank)*3),
      'retention_score'=>min(100,$score+(4-$rank)*4),
      'conversion_score'=>min(100,$score-3+($brand==='seller_authority'?8:0)),
      'status'=>'needs_review',
      'raw_payload'=>['generated_by'=>'v17_4_content_intelligence'],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    $rank++;
  }
  return $rows;
}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $projects=ci_rows('media_projects','select=*&status=in.(uploaded,analyzed,clip_planned)&order=created_at.desc&limit=100');
  $intelCreated=0; $clipsCreated=0; $episodesCreated=0; $errors=[];

  foreach($projects as $p){
    $pid=$p['id'];
    $existing=ci_rows('media_content_intelligence','select=id&media_project_id=eq.'.rawurlencode($pid).'&limit=1');
    if(!empty($existing)) continue;

    $brand=$p['brand_pillar'] ?? 'discover_ct';
    $style=ci_brand_style($brand);
    $transcript=trim(($p['notes'] ?? '')."\n".($p['recommended_angle'] ?? '')."\n".($p['recommended_cta'] ?? ''));
    if(strlen($transcript)<20) $transcript='No full transcript yet. Use the uploaded media title, brand pillar, town, and director notes to create first-pass clip strategy.';
    $score=ci_score_text($transcript.' '.($p['title']??''),$brand);

    $summary='Jessica identified the core content opportunity as '.$style['emotion'].' for '.$style['audience'].'.';
    $story='Hook the viewer immediately, build context quickly, reveal the local/real estate insight, then close with a soft Mark Pyres CTA.';
    if($brand==='house_detective') $story='Open as a case file, reveal the strongest property clue, explain why it matters emotionally, then close with the House Detective CTA.';

    $payload=[
      'media_project_id'=>$pid,
      'project_title'=>$p['title'] ?? '',
      'brand_pillar'=>$brand,
      'transcript_text'=>$transcript,
      'transcript_source'=>'project_notes_or_manual_transcript',
      'content_summary'=>$summary,
      'strongest_storyline'=>$story,
      'audience_persona'=>$style['audience'],
      'primary_emotion'=>$style['emotion'],
      'viral_score'=>$score,
      'lead_conversion_score'=>min(100,$score+($brand==='seller_authority'?8:2)),
      'episode_score'=>min(100,$score-2),
      'recommended_episode_title'=>($p['title'] ?? 'Discover CT Episode').' — Jessica Director Cut',
      'recommended_episode_outline'=>"Cold open: strongest hook.\nAct 1: context and location.\nAct 2: best emotional or educational moments.\nAct 3: why it matters to buyers/sellers/locals.\nClose: ".$style['cta'],
      'director_summary'=>'Cut this like a premium Mark Pyres content piece: unmistakable hook, fast captions, strong local angle, logo consistency, and a soft but clear CTA.',
      'status'=>'analyzed',
      'raw_payload'=>['style'=>$style,'generated_by'=>'v17_4_content_intelligence'],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    $r=ci_sb('POST','media_content_intelligence',[$payload]);
    if(!$r['ok']){ $errors[]=$r['body']; continue; }
    $intelCreated++;
    $intel_id=$r['data'][0]['id'] ?? null;

    if($intel_id){
      $clips=ci_make_clips($p,$intel_id,$transcript);
      $cr=ci_sb('POST','media_clip_intelligence',$clips);
      if($cr['ok']) $clipsCreated+=count($clips); else $errors[]=$cr['body'];

      $episode=[
        'media_project_id'=>$pid,
        'content_intelligence_id'=>$intel_id,
        'episode_title'=>$payload['recommended_episode_title'],
        'episode_length_target'=>'5-10 minutes',
        'cold_open'=>'Start with the highest curiosity moment or most emotional visual. No slow intro.',
        'act_one'=>'Establish the place, property, person, or community story.',
        'act_two'=>'Layer in the best moments, emotional beats, and educational context.',
        'act_three'=>'Connect the story to Mark Pyres authority, local trust, or real estate insight.',
        'closing_cta'=>$style['cta'],
        'broll_notes'=>'Use town signs, storefronts, drone clips, street details, smiling faces, property details, map shots, and branded title cards.',
        'music_notes'=>$style['music'],
        'caption_notes'=>'Use bold readable captions. Highlight curiosity words and local names. Keep safe margins for Reels/TikTok/Shorts.',
        'director_notes'=>$payload['director_summary'],
        'status'=>'planned',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ];
      $er=ci_sb('POST','media_storyline_episodes',[$episode]);
      if($er['ok']) $episodesCreated++; else $errors[]=$er['body'];
    }
  }

  echo json_encode([
    'success'=>empty($errors),
    'projects_found'=>count($projects),
    'intelligence_created'=>$intelCreated,
    'clips_created'=>$clipsCreated,
    'episodes_created'=>$episodesCreated,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>