<?php
/**
 * V17.7 Jessica Audio Command Center Builder
 * Upload: /public_html/lead-engine/build-audio-command-center.php
 */
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function ac_sb($method,$endpoint,$payload=null){
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
  if($payload!==null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
function ac_rows($t,$q){$r=ac_sb('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}

try{
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  $clips=ac_rows('media_clip_intelligence','select=*&status=in.(needs_review,approved)&order=viral_score.desc,created_at.desc&limit=200');
  $created=0; $errors=[];

  foreach($clips as $c){
    $existing=ac_rows('media_audio_reviews','select=id&media_clip_intelligence_id=eq.'.rawurlencode($c['id']).'&limit=1');
    if(!empty($existing)) continue;

    $effectStack=[
      'opensource_tools'=>[
        'ffmpeg'=>'extract audio, normalize, EQ, compression, loudness, music ducking',
        'rnnoise'=>'real-time voice denoise / background noise reduction',
        'demucs'=>'vocal isolation / music separation',
        'sox'=>'audio cleanup, filters, trim, fades',
        'rubberband'=>'time stretch / pitch-safe timing',
        'loudnorm'=>'broadcast/social loudness normalization',
        'whisper/faster-whisper'=>'transcript alignment and caption correction'
      ],
      'recommended_chain'=>[
        'extract_audio',
        'voice_activity_detect',
        'rnnoise_denoise',
        'hum_removal_60hz',
        'de_reverb_light',
        'eq_voice_presence',
        'compressor_voice',
        'de_esser',
        'normalize_-16_lufs',
        'music_ducking_under_voice',
        'export_wav_and_aac'
      ],
      'manual_controls'=>[
        'isolate_vocals',
        'reduce_background_noise',
        'boost_voice',
        'remove_hum',
        'de_ess',
        'compress_voice',
        'normalize_loudness',
        'duck_music',
        'trim_start_end',
        'fade_in_out'
      ]
    ];

    $row=[
      'media_project_id'=>$c['media_project_id'],
      'media_clip_intelligence_id'=>$c['id'],
      'audio_status'=>'needs_review',
      'source_audio_url'=>'',
      'processed_audio_url'=>'',
      'vocal_isolation'=>false,
      'noise_reduction'=>true,
      'hum_removal'=>true,
      'normalize_loudness'=>true,
      'de_esser'=>true,
      'compressor'=>true,
      'eq_preset'=>'voice_lapel_quality',
      'target_lufs'=>'-16 LUFS',
      'music_ducking'=>true,
      'room_reverb_reduction'=>true,
      'human_notes'=>'Review voice clarity, background noise, music balance, and caption accuracy.',
      'jessica_audio_notes'=>'Jessica recommends lapel-quality cleanup: isolate voice if needed, reduce background noise, compress lightly, normalize to social platform loudness, and duck music under dialogue.',
      'effect_stack'=>$effectStack,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    $r=ac_sb('POST','media_audio_reviews',[$row]);
    if($r['ok']) $created++; else $errors[]=$r['body'];
  }

  echo json_encode([
    'success'=>empty($errors),
    'clips_found'=>count($clips),
    'audio_reviews_created'=>$created,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>