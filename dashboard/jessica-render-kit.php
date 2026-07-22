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
$renders=sb('GET','media_render_queue?select=*&order=created_at.desc&limit=200')['data'];
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$files=[];
$dir=__DIR__.'/../uploads/media/renders';
if(is_dir($dir)){
  foreach(array_reverse(glob($dir.'/*')) as $f){
    $files[]=basename($f);
    if(count($files)>80) break;
  }
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V17.2 Render Kit</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:24px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:10px 13px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;text-decoration:none;display:inline-block}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:12px;border-radius:10px;max-height:360px;overflow:auto}</style></head><body><div class="header"><div class="brand">V17.2 Jessica Render Kit</div><div>FFmpeg render instructions, vertical exports, logo/caption/CTA overlays, and Ken Burns-ready effect stack.</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-render-kit.php?key=<?=h($key)?>">Build Render Instructions</a><a class="btn light" href="/dashboard/jessica-shorts-factory.php">Shorts Factory</a><a class="btn light" href="/dashboard/jessica-media-director.php">Media Director</a></p>
<section class="panel"><h2>Open Source Render Pipeline</h2><div class="inner"><pre>V17.2 creates the render instructions needed for an Opus-style workflow.

Included pipeline:
1. FFmpeg vertical crop/export
2. Bold captions
3. Logo overlay
4. CTA card text
5. Punch-in / contrast / saturation enhancements
6. Ken Burns-ready flags for photo/B-roll
7. JSON render recipes for each clip
8. Shell command files for server rendering

Next:
V17.3 adds actual caption transcript generation and word-level hook scoring.
V17.4 adds Canva/Blotato approval bridge.</pre></div></section>
<section class="panel"><h2>Render Queue</h2><table><tr><th>Status</th><th>Export</th><th>Instructions</th><th>Effect Stack</th></tr><?php foreach($renders as $r):?><tr><td><strong><?=h($r['render_status'])?></strong><div class="muted"><?=h($r['render_type'])?><br><?=h($r['output_format'])?></div></td><td><?=h($r['export_file'])?></td><td><?=h($r['render_instructions'])?></td><td><pre><?=h(json_encode($r['effect_stack'],JSON_PRETTY_PRINT))?></pre></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Generated Render Files</h2><div class="inner"><?php foreach($files as $f):?><div><a href="/uploads/media/renders/<?=h($f)?>" target="_blank"><?=h($f)?></a></div><?php endforeach;?></div></section>
</main></body></html>