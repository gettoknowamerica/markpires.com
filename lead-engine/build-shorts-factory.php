<?php
/**
 * V17.1 Jessica Shorts Factory
 * Upload: /public_html/lead-engine/build-shorts-factory.php
 *
 * Creates Opus-style hook moments, director notes, effects stack, and render queue.
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function sf_sb($method,$endpoint,$payload=null){
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
function sf_rows($t,$q){$r=sf_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
function has($hay,$needle){return stripos((string)$hay,(string)$needle)!==false;}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $projects=sf_rows('media_projects','select=*&status=in.(uploaded,analyzed,clip_planned)&order=created_at.desc&limit=100');
  $hooksCreated=0; $notesCreated=0; $rendersCreated=0; $updated=0; $errors=[];

  foreach($projects as $p){
    $pid=$p['id'];
    $title=$p['title'] ?: 'Untitled';
    $brand=$p['brand_pillar'] ?: 'discover_ct';
    $town=$p['town'] ?: 'Fairfield County';
    $blob=strtolower($title.' '.$brand.' '.$town.' '.($p['notes']??'').' '.($p['recommended_angle']??''));

    $existingHooks=sf_rows('media_hook_moments','select=id&media_project_id=eq.'.rawurlencode($pid).'&limit=1');
    if(empty($existingHooks)){
      $hookTemplates=[];

      if($brand==='house_detective' || has($blob,'noir') || has($blob,'house detective')){
        $hookTemplates=[
          ['0','12','mystery','This house had a secret hiding in plain sight.','curiosity','sellers / listing viewers',92,'Open like a noir case file. Add film grain, typewriter card, detective sting.'],
          ['12','28','reveal','The clue that changes how buyers see this home.','surprise','buyers / sellers',88,'Cut on the reveal. Use zoom punch + evidence-card overlay.'],
          ['28','45','cta','Case file: what makes this property impossible to ignore.','authority','homeowners',84,'Close with House Detective logo and value review CTA.']
        ];
      } elseif($brand==='seller_authority' || has($blob,'seller') || has($blob,'valuation')){
        $hookTemplates=[
          ['0','10','pain_point','Most Fairfield County homeowners are missing this value signal.','urgency','sellers',89,'Open with bold text over clean real estate B-roll.'],
          ['10','30','education','Online estimates miss the details buyers actually pay for.','trust','sellers',86,'Show 3 fast bullets. Keep expert but not salesy.'],
          ['30','50','cta','Before you guess, get a local read from Mark.','action','homeowners',82,'End with private value review CTA.']
        ];
      } else {
        $hookTemplates=[
          ['0','8','curiosity','This is why locals love '.$town.'.','curiosity','local audience / relocation buyers',90,'Start with a face, food, street moment, or visual surprise.'],
          ['8','25','emotion','The moment that makes this feel like Connecticut.','belonging','relocation / community',86,'Use warm captions and quick local context.'],
          ['25','45','identity','Save this for your next '.$town.' day.','shareability','locals / visitors',83,'End with Discover CT logo + soft Mark CTA.']
        ];
      }

      $hookRows=[];
      foreach($hookTemplates as $h){
        $hookRows[]=[
          'media_project_id'=>$pid,
          'hook_time_seconds'=>(int)$h[0],
          'hook_end_seconds'=>(int)$h[1],
          'hook_type'=>$h[2],
          'hook_text'=>$h[3],
          'emotional_driver'=>$h[4],
          'audience_target'=>$h[5],
          'viral_score'=>$h[6],
          'retention_score'=>min(100,$h[6]+3),
          'director_note'=>$h[7],
          'created_at'=>date('c')
        ];
      }
      $r=sf_sb('POST','media_hook_moments',$hookRows);
      if($r['ok']) $hooksCreated+=count($hookRows); else $errors[]=$r['body'];
    }

    $existingNotes=sf_rows('media_director_notes','select=id&media_project_id=eq.'.rawurlencode($pid).'&limit=1');
    if(empty($existingNotes)){
      $style='Discover CT signature: local, warm, kinetic captions, strong hook, logo bottom right.';
      if($brand==='house_detective') $style='House Detective signature: noir title cards, film grain, detective evidence overlays, dramatic zooms.';
      if($brand==='seller_authority') $style='Seller authority signature: premium, concise, expert, trust-building, private valuation CTA.';

      $notes=[
        [
          'media_project_id'=>$pid,
          'note_type'=>'director_cut',
          'note_title'=>'Jessica Director Cut',
          'note_body'=>"Create 3 vertical shorts and 1 episode outline. Use the strongest emotional hook first. Keep every clip unmistakably Mark Pyres / {$brand}. Style: {$style}",
          'priority'=>95,
          'status'=>'active',
          'created_at'=>date('c')
        ],
        [
          'media_project_id'=>$pid,
          'note_type'=>'caption',
          'note_title'=>'Caption Direction',
          'note_body'=>'Use large readable captions. Emphasize the first 2 seconds. Add micro-pauses, punch-in moments, and CTA card at the end.',
          'priority'=>90,
          'status'=>'active',
          'created_at'=>date('c')
        ],
        [
          'media_project_id'=>$pid,
          'note_type'=>'script',
          'note_title'=>'Storyline Script',
          'note_body'=>'Story arc: Hook → local emotional detail → why it matters → Mark/Discover CT authority → soft CTA.',
          'priority'=>85,
          'status'=>'active',
          'created_at'=>date('c')
        ]
      ];
      $r=sf_sb('POST','media_director_notes',$notes);
      if($r['ok']) $notesCreated+=count($notes); else $errors[]=$r['body'];
    }

    $clips=sf_rows('media_clip_plans','select=*&media_project_id=eq.'.rawurlencode($pid).'&status=in.(planned,needs_review,approved)&limit=20');
    foreach($clips as $c){
      $existingRender=sf_rows('media_render_queue','select=id&media_clip_plan_id=eq.'.rawurlencode($c['id']).'&limit=1');
      if(!empty($existingRender)) continue;

      $effectStack=[
        'format'=>'vertical_1080x1920',
        'captions'=>[
          'style'=>$c['caption_style'] ?: 'bold kinetic',
          'highlight_keywords'=>true,
          'max_words_per_line'=>5,
          'safe_area'=>true
        ],
        'logos'=>[
          'placement'=>$c['logo_placement'] ?: 'bottom_right',
          'intro_logo'=>true,
          'outro_logo'=>true
        ],
        'effects'=>[
          'ken_burns'=>(bool)($c['ken_burns'] ?? true),
          'auto_punch_in'=>true,
          'beat_cuts'=>true,
          'subtitle_pop'=>true,
          'broll_slots'=>true,
          'title_cards'=>$brand==='house_detective'
        ],
        'cta'=>[
          'text'=>$c['cta_text'] ?: 'Call or text Mark Pyres at 203-247-2655',
          'end_card'=>true
        ],
        'opensource_pipeline'=>[
          'ffmpeg',
          'whisper.cpp or faster-whisper for transcription',
          'opencv scene detection',
          'remotion optional for html-based templates',
          'imagemagick optional for title cards'
        ]
      ];

      $r=sf_sb('POST','media_render_queue',[[
        'media_project_id'=>$pid,
        'media_clip_plan_id'=>$c['id'],
        'render_type'=>$c['clip_type'] ?: 'short',
        'render_status'=>'ready_for_render',
        'output_format'=>'vertical_1080x1920',
        'caption_preset'=>'bold_kinetic',
        'logo_preset'=>'mark_pyres_bottom_right',
        'effect_stack'=>$effectStack,
        'render_instructions'=>"Cut from {$c['start_seconds']}s to {$c['end_seconds']}s. Hook: {$c['hook']}. Overlay: {$c['overlay_text']}. CTA: {$c['cta_text']}.",
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]]);
      if($r['ok']) $rendersCreated++; else $errors[]=$r['body'];
    }

    sf_sb('PATCH','media_projects?id=eq.'.rawurlencode($pid),[
      'status'=>'clip_planned',
      'updated_at'=>date('c')
    ]);
    $updated++;
  }

  echo json_encode([
    'success'=>empty($errors),
    'projects_processed'=>count($projects),
    'projects_updated'=>$updated,
    'hooks_created'=>$hooksCreated,
    'director_notes_created'=>$notesCreated,
    'render_queue_created'=>$rendersCreated,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>