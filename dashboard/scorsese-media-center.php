<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scorsese-media-center.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}

$key = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';

$videos = rows("SELECT id,title,status,progress,media_type,output_url,output_path,thumbnail_url,prompt,error_message,created_at,updated_at,completed_at
  FROM scorsese_comfy_jobs
  WHERE status IN ('complete','completed','ready')
    AND output_url IS NOT NULL
    AND output_url <> ''
    AND output_url NOT LIKE 'http://127.0.0.1:%'
  ORDER BY completed_at DESC, updated_at DESC, id DESC
  LIMIT 80");

$localOnly = rows("SELECT id,title,status,progress,media_type,output_url,output_path,thumbnail_url,prompt,error_message,created_at,updated_at,completed_at
  FROM scorsese_comfy_jobs
  WHERE status IN ('complete','completed','ready')
    AND output_url LIKE 'http://127.0.0.1:%'
  ORDER BY updated_at DESC, id DESC
  LIMIT 25");

$queue = rows("SELECT id,title,status,progress,media_type,output_url,output_path,thumbnail_url,prompt,error_message,created_at,updated_at
  FROM scorsese_comfy_jobs
  WHERE status IN ('queued','retry','working','rendering','needs_revision','failed','error')
  ORDER BY updated_at DESC, id DESC
  LIMIT 60");

$counts = one("SELECT
  SUM(CASE WHEN status IN ('queued','retry') THEN 1 ELSE 0 END) queued,
  SUM(CASE WHEN status IN ('working','rendering') THEN 1 ELSE 0 END) working,
  SUM(CASE WHEN status IN ('complete','completed') THEN 1 ELSE 0 END) complete,
  SUM(CASE WHEN status IN ('failed','error') THEN 1 ELSE 0 END) failed
  FROM scorsese_comfy_jobs");

$brandAssets = [];
try{
  gdb_exec("CREATE TABLE IF NOT EXISTS scorsese_brand_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    brand_key VARCHAR(90) NOT NULL,
    title VARCHAR(255) NULL,
    file_url VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(40) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_brand (brand_key),
    KEY idx_status (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $brandAssets = rows("SELECT * FROM scorsese_brand_assets WHERE status='active' ORDER BY created_at DESC LIMIT 40");
}catch(Throwable $e){}
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scorsese Media Center</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33">
<link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
<style>
body{background:#030712;color:#fff}
.hero{background:radial-gradient(circle at 20% 0%,rgba(147,51,234,.35),transparent 30%),linear-gradient(135deg,#16041f,#0f172a);border:1px solid rgba(168,85,247,.55);border-radius:24px;padding:22px;margin-bottom:14px}
.hero h1{margin:0;color:#fff}.hero p{color:#cbd5e1;max-width:900px}
.topGrid{display:grid;grid-template-columns:minmax(0,1.25fr) 430px;gap:14px;align-items:start}
.player,.promptBox,.logoBox{background:#07111f;border:1px solid rgba(255,255,255,.12);border-radius:22px;padding:16px;box-shadow:0 30px 80px rgba(0,0,0,.25)}
.player{background:#020617}
.screen{width:100%;aspect-ratio:16/9;background:#000;border-radius:18px;overflow:hidden;border:1px solid rgba(212,175,55,.35);display:flex;align-items:center;justify-content:center}
.screen video{width:100%;height:100%;object-fit:contain;background:#000}
.emptyScreen{text-align:center;color:#94a3b8;padding:30px}.emptyScreen strong{display:block;color:#f5d48b;font-size:22px;margin:8px 0}
.promptBox h2,.logoBox h2{margin:0 0 8px;color:#f5d48b}
textarea,input,select{width:100%;box-sizing:border-box;background:#020617;color:#fff;border:1px solid rgba(255,255,255,.16);border-radius:13px;padding:11px;margin:7px 0;font-family:inherit}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.btn.small{padding:8px 10px;font-size:12px}
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0}.kpi{background:#07111f;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:12px}.kpi b{display:block;color:#f5d48b;font-size:24px}.kpi span{color:#94a3b8;font-size:12px;text-transform:uppercase;font-weight:900}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px}.card{background:#07111f;border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:12px}.thumb{width:100%;aspect-ratio:16/9;background:#020617;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center}.thumb video{width:100%;height:100%;object-fit:cover}.card h3{color:#f5d48b;margin:10px 0 6px}.meta{color:#94a3b8;font-size:12px}.badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:1000;background:#14532d;color:#dcfce7}.warn{background:#451a03;color:#fed7aa}.result{color:#cbd5e1;margin-top:8px;white-space:pre-wrap}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.tab{background:#0f172a;border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:8px 12px;color:#fff;text-decoration:none;font-weight:900}
.sideStack{display:grid;gap:14px}.assetGrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px}.asset{background:#020617;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:7px;text-align:center}.asset img{max-width:100%;height:54px;object-fit:contain}.asset small{display:block;color:#94a3b8;font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.reviseBox{margin-top:10px;background:#020617;border:1px solid rgba(212,175,55,.22);border-radius:14px;padding:10px}
@media(max-width:1050px){.topGrid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.kpis{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?>
<main class="main">
<section class="hero">
  <h1>🎬 Scorsese Media Center</h1>
  <p>Create new ComfyUI videos, play finished MP4s, upload brand assets, and revise a specific video directly.</p>
  <div class="actions">
    <a class="btn dark" href="/dashboard/goliath-missions.php">Team Missions</a>
    <a class="btn" target="_blank" href="/lead-engine/scorsese-comfy-status.php?key=<?=h($key)?>">Render Status JSON</a>
    <a class="btn" target="_blank" href="/lead-engine/scorsese-comfy-pull.php?key=<?=h($key)?>">Test Pull</a>
  </div>
</section>

<section class="kpis">
  <div class="kpi"><b><?=h($counts['queued']??0)?></b><span>Queued</span></div>
  <div class="kpi"><b><?=h($counts['working']??0)?></b><span>Working</span></div>
  <div class="kpi"><b><?=h($counts['complete']??0)?></b><span>Complete</span></div>
  <div class="kpi"><b><?=h($counts['failed']??0)?></b><span>Failed</span></div>
</section>

<section class="topGrid">
  <div class="player">
    <h2>Video Player</h2>
    <div class="screen" id="mainScreen">
      <?php if(count($videos)): $first=$videos[0]; ?>
        <video id="mainVideo" controls src="<?=h($first['output_url'])?>"></video>
      <?php else: ?>
        <div class="emptyScreen"><div style="font-size:46px">🎥</div><strong>No public Hostinger MP4s yet</strong><span>Queue a prompt on the right, run the local Comfy worker, and completed videos will appear here.</span></div>
      <?php endif; ?>
    </div>
    <?php if(count($videos)): $first=$videos[0]; ?>
      <h3 id="mainTitle"><?=h($first['title'])?></h3>
      <p class="meta" id="mainMeta">Job #<?=h($first['id'])?> · <?=h($first['completed_at'] ?: $first['updated_at'])?></p>
      <div class="actions"><a class="btn small" id="mainOpen" href="<?=h($first['output_url'])?>" target="_blank">Open MP4</a><button class="btn small" onclick="document.getElementById('mainRevision').focus()">Ask Scorsese to Improve This</button></div>
      <div class="reviseBox">
        <strong>Improve Current Video</strong>
        <textarea id="mainRevision" rows="4" placeholder="Example: Make this longer, remove the fake text on the house, improve the grass and waterfront realism, smoother drone descent, more premium luxury."></textarea>
        <button class="btn small" onclick="reviseCurrentVideo()">Send Revision for This Video</button>
        <div id="mainRevisionResult" class="result"></div>
      </div>
    <?php endif; ?>
  </div>

  <aside class="sideStack">
    <div class="promptBox">
      <h2>Create New Video</h2>
      <p class="meta">This queues a real Scorsese ComfyUI job.</p>
      <input id="title" value="Scorsese Prompt Video" placeholder="Video title">
      <div class="row">
        <select id="aspect">
          <option value="9:16">Vertical 9:16 Reel / TikTok / Shorts</option>
          <option value="16:9">Horizontal 16:9 YouTube / Website</option>
          <option value="1:1">Square 1:1 Feed</option>
        </select>
        <select id="category">
          <option value="discover_ct">Discover Connecticut</option>
          <option value="real_estate">Real Estate</option>
          <option value="house_detective">House Detective</option>
          <option value="beatseat">BeatSeat</option>
          <option value="legacysaved">LegacySaved</option>
          <option value="general">General Goliath Omni</option>
        </select>
      </div>
      <div class="row">
        <select id="style">
          <option>Cinematic luxury</option>
          <option>Noir detective</option>
          <option>Warm emotional documentary</option>
          <option>High-energy viral social</option>
          <option>Premium real estate commercial</option>
        </select>
        <select id="duration">
          <option>5 seconds</option>
          <option>8 seconds</option>
          <option>10 seconds</option>
        </select>
      </div>
      <textarea id="prompt" rows="6" placeholder="Example: Drone descending on a $15M Fairfield County waterfront estate, golden hour, premium commercial realism, no fake text, no warped architecture, smooth cinematic move."></textarea>
      <button class="btn" onclick="queueVideo()">🎬 Queue Video</button>
      <div id="queueResult" class="result"></div>
    </div>

    <div class="logoBox">
      <h2>Logo & Graphic Assets</h2>
      <p class="meta">Upload House Detective, Discover CT, BeatSeat, LegacySaved, contact cards, lower thirds, logos, and overlays. Scorsese can reference them in future video prompts.</p>
      <select id="brand">
        <option value="house-detective">House Detective</option>
        <option value="discover-ct">Discover CT</option>
        <option value="beatseat">BeatSeat</option>
        <option value="legacysaved">LegacySaved</option>
        <option value="mark-pires">Mark Pires</option>
        <option value="general">General</option>
      </select>
      <input id="assetTitle" placeholder="Asset name, e.g. House Detective Logo">
      <input id="assetFile" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,.gif,.mp4,.mov,.pdf">
      <button class="btn small" onclick="uploadBrandAsset()">Upload Asset</button>
      <div id="assetResult" class="result"></div>
      <div class="assetGrid">
        <?php foreach($brandAssets as $a): ?>
          <div class="asset">
            <?php if(in_array(strtolower($a['file_type']),['png','jpg','jpeg','webp','gif','svg'])): ?><img src="<?=h($a['file_url'])?>"><?php else: ?><div style="font-size:28px">📎</div><?php endif;?>
            <small><?=h($a['brand_key'])?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>
</section>

<div class="tabs">
  <a class="tab" href="#finished">Finished Videos</a>
  <a class="tab" href="#queue">Render Queue</a>
  <a class="tab" href="#local">Local-only Outputs</a>
</div>

<section class="panel" id="finished">
  <h2>Finished Hostinger Videos <span><?=count($videos)?> public MP4s</span></h2>
  <div class="inner">
    <?php if(!count($videos)): ?><p class="meta">No public MP4 URLs are saved yet. If Comfy finished locally, run the register/upload worker.</p><?php endif; ?>
    <div class="grid">
    <?php foreach($videos as $v): ?>
      <div class="card">
        <div class="thumb"><video muted preload="metadata" src="<?=h($v['output_url'])?>"></video></div>
        <h3><?=h($v['title'])?></h3>
        <div class="meta">Job #<?=h($v['id'])?> · <?=h($v['completed_at'] ?: $v['updated_at'])?></div>
        <div class="actions">
          <button class="btn small" onclick='playVideo(<?=json_encode((int)$v['id'])?>,<?=json_encode($v['output_url'])?>,<?=json_encode($v['title'])?>,<?=json_encode('Job #'.$v['id'].' · '.($v['completed_at'] ?: $v['updated_at']))?>)'>Play in Main Player</button>
          <a class="btn small" href="<?=h($v['output_url'])?>" target="_blank">Open MP4</a>
          <button class="btn small" onclick='focusRevision(<?=json_encode((int)$v['id'])?>,<?=json_encode($v['title'])?>)'>Revise</button>
        </div>
        <div class="reviseBox">
          <strong>Revise this video</strong>
          <textarea rows="3" id="revise_<?=h($v['id'])?>" placeholder="Tell Scorsese exactly what to improve in this specific video..."></textarea>
          <button class="btn small" onclick="reviseVideo(<?=h((int)$v['id'])?>)">Send Revision</button>
          <div id="reviseResult_<?=h($v['id'])?>" class="result"></div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="panel" id="queue">
  <h2>Render Queue / Issues <span><?=count($queue)?> jobs</span></h2>
  <div class="inner"><div class="grid">
  <?php foreach($queue as $q): ?>
    <div class="card">
      <h3><?=h($q['title'])?></h3>
      <p><span class="badge <?=in_array($q['status'],['failed','error','needs_revision'])?'warn':''?>"><?=h($q['status'])?> · <?=h($q['progress'])?>%</span></p>
      <p class="meta">Job #<?=h($q['id'])?> · <?=h($q['updated_at'])?></p>
      <?php if(!empty($q['error_message'])):?><p class="meta"><?=h($q['error_message'])?></p><?php endif;?>
    </div>
  <?php endforeach; ?>
  </div></div>
</section>

<section class="panel" id="local">
  <h2>Local-only Comfy Outputs <span><?=count($localOnly)?> need upload/register</span></h2>
  <div class="inner"><p class="meta">These point to 127.0.0.1 and are not public yet. Run the Comfy/register worker to upload them to Hostinger.</p><div class="grid">
  <?php foreach($localOnly as $l): ?>
    <div class="card"><h3><?=h($l['title'])?></h3><p class="meta">Job #<?=h($l['id'])?> · <?=h($l['updated_at'])?></p><p class="meta"><?=h($l['output_path'])?></p></div>
  <?php endforeach; ?>
  </div></div>
</section>

</main></div>
<script>
const KEY = <?=json_encode($key)?>;
let currentJobId = <?=count($videos)?(int)$videos[0]['id']:'null'?>;

function playVideo(jobId,url,title,meta){
  currentJobId = jobId;
  const screen=document.getElementById('mainScreen');
  const safe=url.replace(/"/g,'&quot;');
  screen.innerHTML='<video id="mainVideo" controls autoplay src="'+safe+'"></video>';
  const t=document.getElementById('mainTitle'); if(t)t.textContent=title;
  const m=document.getElementById('mainMeta'); if(m)m.textContent=meta;
  const o=document.getElementById('mainOpen'); if(o)o.href=url;
  const box=document.getElementById('mainRevision'); if(box){box.value=''; box.placeholder='Revision for Job #'+jobId+': make it more realistic, longer, cleaner, more premium...';}
  window.scrollTo({top:0,behavior:'smooth'});
}

function focusRevision(jobId,title){
  const box=document.getElementById('revise_'+jobId);
  if(box){box.focus(); box.scrollIntoView({behavior:'smooth',block:'center'});}
}

async function queueVideo(){
  const payload={
    key:KEY,
    title:document.getElementById('title').value.trim()||'Scorsese Prompt Video',
    aspect_ratio:document.getElementById('aspect').value,
    category:document.getElementById('category').value,
    style:document.getElementById('style').value,
    duration:document.getElementById('duration').value,
    platform:'dashboard',
    prompt:document.getElementById('prompt').value.trim()
  };
  if(!payload.prompt){alert('Add a video prompt first.');return;}
  const out=document.getElementById('queueResult');
  out.textContent='Queuing video job...';
  try{
    const res=await fetch('/lead-engine/scorsese-comfy-direct-queue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const json=await res.json();
    if(json.success||json.ok){out.textContent='Queued job #'+json.job_id+'. Local Comfy worker will pull it.'; setTimeout(()=>location.reload(),1200);}
    else out.textContent='Could not queue: '+(json.error||'unknown error');
  }catch(e){out.textContent='Queue error: '+e.message;}
}

async function reviseVideo(jobId){
  const box=document.getElementById('revise_'+jobId);
  const out=document.getElementById('reviseResult_'+jobId);
  const text=box?box.value.trim():'';
  if(!text){alert('Add revision instructions first.');return;}
  out.textContent='Queuing revision...';
  try{
    const res=await fetch('/lead-engine/scorsese-comfy-video-revision.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,job_id:jobId,revision_prompt:text})});
    const json=await res.json();
    if(json.success||json.ok){out.textContent='Revision queued as job #'+json.new_job_id+'.'; setTimeout(()=>location.reload(),1200);}
    else out.textContent='Could not queue revision: '+(json.error||'unknown error');
  }catch(e){out.textContent='Revision error: '+e.message;}
}

async function reviseCurrentVideo(){
  if(!currentJobId){alert('No current video selected.');return;}
  const box=document.getElementById('mainRevision');
  const text=box?box.value.trim():'';
  if(!text){alert('Add revision instructions first.');return;}
  const out=document.getElementById('mainRevisionResult');
  out.textContent='Queuing revision...';
  try{
    const res=await fetch('/lead-engine/scorsese-comfy-video-revision.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,job_id:currentJobId,revision_prompt:text})});
    const json=await res.json();
    if(json.success||json.ok){out.textContent='Revision queued as job #'+json.new_job_id+'.'; setTimeout(()=>location.reload(),1200);}
    else out.textContent='Could not queue revision: '+(json.error||'unknown error');
  }catch(e){out.textContent='Revision error: '+e.message;}
}

async function uploadBrandAsset(){
  const f=document.getElementById('assetFile').files[0];
  if(!f){alert('Choose a logo or graphic first.');return;}
  const fd=new FormData();
  fd.append('key',KEY);
  fd.append('brand',document.getElementById('brand').value);
  fd.append('title',document.getElementById('assetTitle').value.trim()||f.name);
  fd.append('asset',f);
  const out=document.getElementById('assetResult');
  out.textContent='Uploading...';
  try{
    const res=await fetch('/lead-engine/scorsese-brand-asset-upload.php',{method:'POST',body:fd});
    const json=await res.json();
    if(json.success||json.ok){out.textContent='Uploaded: '+json.file_url; setTimeout(()=>location.reload(),1000);}
    else out.textContent='Upload failed: '+(json.error||'unknown error');
  }catch(e){out.textContent='Upload error: '+e.message;}
}
</script>
</body></html>