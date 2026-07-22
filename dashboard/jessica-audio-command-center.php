<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'data'=>is_array($d)?$d:[],'body'=>$b,'http'=>$h];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';
  $patch=[
    'audio_status'=>$_POST['audio_status']??'needs_review',
    'source_audio_url'=>$_POST['source_audio_url']??'',
    'processed_audio_url'=>$_POST['processed_audio_url']??'',
    'vocal_isolation'=>!empty($_POST['vocal_isolation']),
    'noise_reduction'=>!empty($_POST['noise_reduction']),
    'hum_removal'=>!empty($_POST['hum_removal']),
    'normalize_loudness'=>!empty($_POST['normalize_loudness']),
    'de_esser'=>!empty($_POST['de_esser']),
    'compressor'=>!empty($_POST['compressor']),
    'music_ducking'=>!empty($_POST['music_ducking']),
    'room_reverb_reduction'=>!empty($_POST['room_reverb_reduction']),
    'eq_preset'=>$_POST['eq_preset']??'voice_lapel_quality',
    'target_lufs'=>$_POST['target_lufs']??'-16 LUFS',
    'human_notes'=>$_POST['human_notes']??'',
    'jessica_audio_notes'=>$_POST['jessica_audio_notes']??'',
    'updated_at'=>date('c')
  ];
  sb('PATCH','media_audio_reviews?id=eq.'.rawurlencode($id),$patch);
  $msg='Audio review updated.';
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$reviews=sb('GET','media_audio_reviews?select=*&order=updated_at.desc,created_at.desc&limit=200')['data'];
$clips=sb('GET','media_clip_intelligence?select=*&order=viral_score.desc,created_at.desc&limit=200')['data'];
function clipById($clips,$id){foreach($clips as $c){if(($c['id']??'')===$id)return $c;}return [];}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V17.7 Audio Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.topbar{position:sticky;top:0;z-index:10;background:#111827;color:#fff;border-bottom:2px solid #c8a96e}.menu{display:flex;align-items:center;max-width:1900px;margin:auto}.logo{font-family:Georgia,serif;color:#c8a96e;font-size:22px;font-weight:900;padding:13px 18px}.navitem{position:relative;padding:15px 14px;font-size:13px;font-weight:800}.navitem:hover{background:#1f2937}.drop{display:none;position:absolute;top:48px;left:0;background:#fff;color:#111;min-width:280px;box-shadow:0 8px 30px #0003;border-radius:0 0 12px 12px;overflow:hidden}.navitem:hover .drop{display:block}.drop a{display:block;color:#111;text-decoration:none;padding:10px 14px;border-bottom:1px solid #eee;font-weight:700}.drop a:hover{background:#f5f3ef}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1900px;margin:auto;padding:24px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 12px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;text-decoration:none;display:inline-block;cursor:pointer}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}input,select,textarea{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0 8px}.muted{color:#777;font-size:13px}.audioBox{background:#111;color:#fff;border-radius:14px;padding:14px}.switches{display:grid;grid-template-columns:repeat(2,1fr);gap:6px}.switches label{background:#f5f3ef;border-radius:8px;padding:7px;font-size:12px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:12px;border-radius:10px;max-height:280px;overflow:auto}@media(max-width:1000px){.menu{overflow-x:auto}.wrap{padding:14px}.switches{grid-template-columns:1fr}}</style></head><body>
<div class="topbar"><div class="menu"><div class="logo">Jessica OS</div><div class="navitem">Creator Center<div class="drop"><a href="/dashboard/jessica-creative-command-center.php">Creative Command Center</a><a href="/dashboard/jessica-audio-command-center.php">Audio Command Center</a><a href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a><a href="/dashboard/jessica-render-kit.php">Render Kit</a><a href="/dashboard/jessica-shorts-factory.php">Shorts Factory</a></div></div><div class="navitem">Executive Assistant<div class="drop"><a href="/dashboard/jessica-mcp-server.php">MCP Server</a><a href="/dashboard/internal-learning-brain.php">Learning Brain</a></div></div></div></div>
<div class="header"><div class="brand">V17.7 Audio Command Center</div><div>Pro Tools / Adobe Audition-style audio review: isolate voice, denoise, level, EQ, compress, de-ess, music ducking.</div></div>
<main class="wrap"><p><a class="btn" target="_blank" href="/lead-engine/build-audio-command-center.php?key=<?=h($key)?>">Build Audio Reviews</a><a class="btn light" href="/dashboard/jessica-creative-command-center.php">Creative Command Center</a></p><?php if($msg):?><div class="panel"><div class="inner"><?=h($msg)?></div></div><?php endif;?>
<section class="panel"><h2>Audio Review Console</h2><table><tr><th>Playback</th><th>Clip</th><th>Audio Controls</th><th>Open Source Chain</th></tr><?php foreach($reviews as $r): $c=clipById($clips,$r['media_clip_intelligence_id']); ?><tr><td><div class="audioBox"><strong><?=h($r['audio_status'])?></strong><br><br><?php if($r['source_audio_url']):?><audio controls src="<?=h($r['source_audio_url'])?>" style="width:100%"></audio><?php endif; ?><?php if($r['processed_audio_url']):?><p>Processed:</p><audio controls src="<?=h($r['processed_audio_url'])?>" style="width:100%"></audio><?php endif; ?><p class="muted">Use this for A/B review once processed audio exists.</p></div></td><td><strong><?=h($c['clip_title']??'Clip')?></strong><div class="muted"><?=h($c['hook_line']??'')?><br><?=h($c['platform']??'')?></div><p><?=h($r['jessica_audio_notes'])?></p></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><label>Status</label><select name="audio_status"><option <?=($r['audio_status']==='needs_review'?'selected':'')?>>needs_review</option><option <?=($r['audio_status']==='cleanup_requested'?'selected':'')?>>cleanup_requested</option><option <?=($r['audio_status']==='ready_to_process'?'selected':'')?>>ready_to_process</option><option <?=($r['audio_status']==='processed'?'selected':'')?>>processed</option><option <?=($r['audio_status']==='approved'?'selected':'')?>>approved</option><option <?=($r['audio_status']==='sent_to_render'?'selected':'')?>>sent_to_render</option></select><label>Source Audio/Video URL</label><input name="source_audio_url" value="<?=h($r['source_audio_url'])?>"><label>Processed Audio URL</label><input name="processed_audio_url" value="<?=h($r['processed_audio_url'])?>"><div class="switches"><label><input type="checkbox" name="vocal_isolation" <?=!empty($r['vocal_isolation'])?'checked':''?>> Isolate vocals</label><label><input type="checkbox" name="noise_reduction" <?=!empty($r['noise_reduction'])?'checked':''?>> Reduce noise</label><label><input type="checkbox" name="hum_removal" <?=!empty($r['hum_removal'])?'checked':''?>> Remove hum</label><label><input type="checkbox" name="room_reverb_reduction" <?=!empty($r['room_reverb_reduction'])?'checked':''?>> Reduce room echo</label><label><input type="checkbox" name="de_esser" <?=!empty($r['de_esser'])?'checked':''?>> De-ess</label><label><input type="checkbox" name="compressor" <?=!empty($r['compressor'])?'checked':''?>> Compress voice</label><label><input type="checkbox" name="normalize_loudness" <?=!empty($r['normalize_loudness'])?'checked':''?>> Normalize loudness</label><label><input type="checkbox" name="music_ducking" <?=!empty($r['music_ducking'])?'checked':''?>> Duck music</label></div><label>EQ Preset</label><select name="eq_preset"><option>voice_lapel_quality</option><option>warm_podcast_voice</option><option>street_interview_cleanup</option><option>noir_narration_voice</option><option>phone_call_cleanup</option></select><label>Target Loudness</label><input name="target_lufs" value="<?=h($r['target_lufs'])?>"><label>Human Audio Notes</label><textarea name="human_notes" rows="4"><?=h($r['human_notes'])?></textarea><label>Jessica Audio Notes</label><textarea name="jessica_audio_notes" rows="4"><?=h($r['jessica_audio_notes'])?></textarea><button class="btn">Save Audio Review</button></form></td><td><pre><?=h(json_encode($r['effect_stack'],JSON_PRETTY_PRINT))?></pre></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Audio Kit Command</h2><div class="inner"><pre>bash /public_html/audio-kit/process-audio.sh input.mp4 output.wav

Included baseline FFmpeg chain:
highpass → lowpass → noise reduction → dynamic normalization → compressor → loudness normalization

Optional future tools:
Demucs = vocal isolation
RNNoise = AI denoise
SoX = advanced filters
Rubber Band = pitch-safe timing
Whisper = transcript/caption correction</pre></div></section>
</main></body></html>