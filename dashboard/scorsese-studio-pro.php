<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scorsese-studio-pro.php'));exit;}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$params=[]):array{try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}}
function one($sql,$params=[]):array{try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

$selectedJob=(int)($_GET['job']??0);
$directorProjectId=(int)($_GET['director_project']??0);
$videos=rows("SELECT * FROM scorsese_comfy_jobs WHERE status IN ('complete','completed','ready') AND COALESCE(output_url,'')<>'' ORDER BY completed_at DESC,updated_at DESC,id DESC LIMIT 40");
$current=$selectedJob?one("SELECT * FROM scorsese_comfy_jobs WHERE id=? LIMIT 1",[$selectedJob]):($videos[0]??[]);
$queue=rows("SELECT id,title,status,progress,error_message,updated_at FROM scorsese_comfy_jobs WHERE status IN ('queued','working','rendering','failed','error') ORDER BY CASE WHEN status IN ('rendering','working') THEN 0 WHEN status='queued' THEN 1 ELSE 2 END,updated_at DESC LIMIT 20");
$projects=rows("SELECT * FROM scorsese_director_projects ORDER BY id DESC LIMIT 30");
$directorProject=$directorProjectId?one("SELECT * FROM scorsese_director_projects WHERE id=? LIMIT 1",[$directorProjectId]):($projects[0]??[]);
$sources=$directorProject?rows("SELECT * FROM scorsese_media_sources WHERE project_id=? ORDER BY id",[(int)$directorProject['id']]):[];
$scenes=$directorProject?rows("SELECT * FROM scorsese_scenes WHERE project_id=? ORDER BY scene_no",[(int)$directorProject['id']]):[];
$renders=$directorProject?rows("SELECT * FROM scorsese_renders WHERE project_id=? ORDER BY id DESC",[(int)$directorProject['id']]):[];
$notes=$directorProject?rows("SELECT * FROM scorsese_director_notes WHERE project_id=? ORDER BY id DESC",[(int)$directorProject['id']]):[];

function viral_score(array $job):int{
 $m=json_decode((string)($job['metadata']??'[]'),true)?:[];if(isset($m['viral_score']))return (int)$m['viral_score'];
 $score=10;$title=strtolower((string)($job['title']??''));
 foreach(['luxury'=>15,'waterfront'=>15,'modern'=>10,'expired'=>10,'seller'=>8,'buyer'=>8,'connecticut'=>8,'greenwich'=>8] as $kw=>$pts)if(str_contains($title,$kw))$score+=$pts;
 return max(10,min(100,$score));
}
usort($videos,fn($a,$b)=>viral_score($b)<=>viral_score($a));$top10=array_slice($videos,0,10);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover"><title>Scorsese Studio Pro</title>
<style>
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 0,rgba(168,85,247,.16),transparent 32%),repeating-linear-gradient(45deg,#070b12 0,#070b12 8px,#0a1019 8px,#0a1019 16px);color:#fff;font-family:Arial,sans-serif}.wrap{max-width:1600px;margin:auto;padding:12px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.logo{font-weight:1000;color:#d8b4fe;letter-spacing:.13em;text-transform:uppercase}.muted{color:#94a3b8;font-size:12px;line-height:1.45}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #ffffff24;background:#101827;color:#fff;border-radius:13px;padding:9px 11px;font-weight:900;font-size:12px;cursor:pointer}.gold{background:linear-gradient(135deg,#f6d679,#9f7418);color:#111}.purple{background:linear-gradient(135deg,#9333ea,#581c87)}.green{background:linear-gradient(135deg,#16a34a,#064e3b)}.red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}.panel{background:linear-gradient(135deg,#0b1120,#050914);border:1px solid #ffffff1a;border-radius:22px;padding:13px;box-shadow:0 20px 60px #0008}.panel h2{margin:0 0 10px;color:#d8b4fe;font-size:16px;text-transform:uppercase;letter-spacing:.06em}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.tab{border:1px solid #ffffff22;background:#101827;color:#fff;border-radius:999px;padding:9px 13px;font-weight:900;cursor:pointer}.tab.active{background:#7e22ce}.tabPane{display:none}.tabPane.active{display:block}.grid{display:grid;grid-template-columns:260px minmax(0,1fr) 340px;gap:14px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.player,.monitor{background:#000;border:1px solid #a855f740;border-radius:20px;overflow:hidden;min-height:440px;display:flex;align-items:center;justify-content:center}.player video,.monitor video{width:100%;max-height:680px;background:#000}.empty{text-align:center;color:#94a3b8;padding:35px}.queueItem,.item,.projectCard{display:block;background:#050914;border:1px solid #ffffff18;border-radius:14px;padding:10px;margin-bottom:8px;color:#fff;text-decoration:none}.bar{height:8px;background:#020617;border-radius:999px;overflow:hidden;margin-top:7px}.bar span{display:block;height:100%;background:linear-gradient(90deg,#9333ea,#22c55e)}.feed{display:grid;gap:8px;max-height:650px;overflow:auto}.score{display:inline-block;background:#d8b4fe;color:#111;border-radius:999px;padding:3px 7px;font-weight:1000;font-size:10px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}.fields .wide{grid-column:1/-1}.fields input,.fields select,.fields textarea{width:100%;background:#050914;color:#fff;border:1px solid #ffffff22;border-radius:12px;padding:11px}.fields textarea{min-height:100px}.drop{border:2px dashed #475569;border-radius:20px;padding:24px;text-align:center;background:#07111f}.drop.drag{border-color:#f5c85d;background:#1a1404}.progress{height:18px;background:#172033;border-radius:999px;overflow:hidden;margin:12px 0;border:1px solid #334155}.progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e)}.checks{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.checks label{border:1px solid #334155;background:#07111f;border-radius:10px;padding:9px}.projectList{max-height:500px;overflow:auto}.timeline{display:grid;gap:7px}.timeline div{padding:9px;border-left:4px solid #9333ea;background:#070d17;border-radius:8px}.cloneSteps{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.cloneStep{background:#070d17;border:1px solid #ffffff18;border-radius:12px;padding:11px}.warning{border:1px solid #b45309;background:#261805;color:#fcd34d;border-radius:12px;padding:11px;margin-top:10px}@media(max-width:1100px){.grid,.two{grid-template-columns:1fr}.fields{grid-template-columns:1fr}.fields .wide{grid-column:auto}.checks,.cloneSteps{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.player,.monitor{min-height:280px}}
</style></head><body><div class="wrap">
<header class="top"><div><div class="logo">🎬 Scorsese Studio Pro</div><div class="muted">One production home: long-form upload, Automatic Director, Human Director, ComfyUI renders, finished media, AI likeness capture and social handoff.</div></div><div><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a> <a class="btn green" target="_blank" href="/lead-engine/v100-scorsese-diagnostic.php?key=<?=h($key)?>">System Status</a></div></header>
<nav class="tabs">
<button class="tab active" data-tab="director">Director Workstation</button>
<button class="tab" data-tab="upload">Long-Form Upload</button>
<button class="tab" data-tab="renders">Render Center</button>
<button class="tab" data-tab="clone">Mark AI Twin Capture</button>
</nav>

<section id="tab-director" class="tabPane active">
<div class="two">
<aside class="panel"><h2>Director Projects</h2><a class="btn gold" href="#new-project">New Project + Upload</a><div class="projectList" style="margin-top:10px"><?php foreach($projects as $p):?><a class="projectCard" href="?director_project=<?=(int)$p['id']?>#director-workstation"><b><?=h($p['title'])?></b><div class="muted"><?=h($p['production_mode'])?> · <?=h($p['production_type'])?> · <?=h($p['status'])?> · <?=(int)$p['progress']?>%</div></a><?php endforeach;?><?php if(!$projects):?><div class="empty">No Director projects yet. Create one in Long-Form Upload.</div><?php endif;?></div></aside>
<main class="panel" id="director-workstation"><h2>Ultimate AI Director</h2>
<?php if($directorProject):?>
<div class="top"><div><h1 style="margin:0"><?=h($directorProject['title'])?></h1><div class="muted"><?=h($directorProject['production_mode'])?> · <?=h($directorProject['production_type'])?></div></div><div class="score"><?=h($directorProject['status'])?> · <?=(int)$directorProject['progress']?>%</div></div>
<div class="monitor"><?php if(!empty($directorProject['output_url'])):?><video controls src="<?=h($directorProject['output_url'])?>"></video><?php else:?><div class="empty">Program monitor<br><b><?=h($directorProject['current_phase']??'Awaiting source media')?></b></div><?php endif;?></div>
<div class="two" style="margin-top:12px"><div><h3>Script / Story</h3><pre style="white-space:pre-wrap"><?=h($directorProject['supplied_script']?:'WhisperX and Story Architect will generate or align the script after ingest.')?></pre></div><div><h3>Director Notes</h3><?php foreach($notes as $n):?><div class="item"><?=nl2br(h($n['note_text']))?></div><?php endforeach;?><?php if(!$notes):?><div class="muted">No notes yet.</div><?php endif;?></div></div>
<div class="timeline"><div>1. Source ingest and checksum</div><div>2. Proxy creation</div><div>3. WhisperX transcript and diarization</div><div>4. Scene/take detection and scoring</div><div>5. Script alignment</div><div>6. Editable EDL and selected takes</div><div>7. Mozart audio pass</div><div>8. Review cut</div><div>9. Director revision</div><div>10. Final, shorts and social package</div></div>
<?php else:?><div class="empty">Create a project and upload footage in the Long-Form Upload tab.</div><?php endif;?>
</main></div></section>

<section id="tab-upload" class="tabPane"><div class="panel" id="new-project"><h2>New Project + Resumable Long-Form Upload</h2>
<div class="fields"><input id="project" value="Scorsese Project" placeholder="Project name"><select id="mode"><option value="automatic_director">Automatic Director</option><option value="human_director">Human Director</option></select><select id="type"><option value="episode">Episode</option><option value="documentary">Documentary</option><option value="interview">Interview</option><option value="music_video">Music Video</option><option value="legacy_film">LegacySaved Film</option></select><select id="brand"><option value="mark_pires">Mark Pires Real Estate</option><option value="discover_ct">Discover CT</option><option value="house_detective">House Detective</option><option value="legacy_saved">LegacySaved</option><option value="beatseat">BeatSeat</option><option value="mark_inspires">Mark insPires</option></select><input id="goal" class="wide" placeholder="Goal, audience and desired final runtime"><textarea id="script" class="wide" placeholder="Optional supplied script or story outline"></textarea><select id="target"><option value="scorsese">Scorsese Raw Vault</option><option value="legacy">LegacySaved Raw Vault</option></select><select id="chunkSize"><option value="52428800">50 MB chunks</option><option value="104857600" selected>100 MB chunks</option><option value="201326592">192 MB chunks</option></select></div>
<div id="drop" class="drop" style="margin-top:12px"><h2>Drop long-form video or audio here</h2><p>Files are uploaded in safe resumable chunks and assembled before Scorsese begins.</p><input id="file" type="file" accept="video/*,audio/*"></div><div class="progress"><span id="uploadBar"></span></div><div id="uploadStatus" class="muted">Waiting for media...</div>
<div class="fields" style="margin-top:12px"><textarea id="directorNotes" class="wide" placeholder="Director notes: best takes, scene instructions, tone, pacing, shots that must remain, sections to remove..."></textarea></div>
<div class="checks"><label><input type="checkbox" value="youtube_master" checked> Master Episode</label><label><input type="checkbox" value="shorts" checked> Shorts</label><label><input type="checkbox" value="captions" checked> Captions</label><label><input type="checkbox" value="thumbnail" checked> Thumbnail</label><label><input type="checkbox" value="social_package" checked> Social Package</label><label><input type="checkbox" value="documentary_cut"> Documentary Cut</label></div>
<button id="finalizeUpload" class="btn gold" style="margin-top:12px" disabled>Finalize Upload & Create Director Project</button></div></section>

<section id="tab-renders" class="tabPane"><section class="grid">
<aside class="panel"><h2>Render Queue</h2><?php foreach($queue as $q):?><div class="queueItem"><b><?=h($q['title'])?></b><div class="muted"><?=h($q['status'])?> · <?=h($q['updated_at'])?></div><div class="bar"><span style="width:<?=max(2,min(100,(int)$q['progress']))?>%"></span></div><?php if(!empty($q['error_message'])):?><div class="muted"><?=h(substr($q['error_message'],0,160))?></div><?php endif;?></div><?php endforeach;?><?php if(!$queue):?><div class="empty">No active renders.</div><?php endif;?></aside>
<main><div class="player"><?php if($current&&!empty($current['output_url'])):?><video controls src="<?=h($current['output_url'])?>"></video><?php else:?><div class="empty">Finished media player</div><?php endif;?></div><h2><?=h($current['title']??'Scorsese Media Player')?></h2></main>
<aside class="panel"><h2>Scorsese Top 10</h2><div class="feed"><?php foreach($top10 as $v):?><a class="item" href="?job=<?=(int)$v['id']?>#tab-renders"><span class="score"><?=viral_score($v)?> viral</span><br><b><?=h($v['title'])?></b></a><?php endforeach;?><?php if(!$top10):?><div class="empty">No scored outputs yet.</div><?php endif;?></div></aside>
</section></section>

<section id="tab-clone" class="tabPane"><div class="panel"><h2>Mark AI Twin — Guided Capture Foundation</h2><p>This will be a consent-controlled internal likeness and voice profile for Mark’s approved marketing, real-estate and creative productions.</p><div class="cloneSteps"><div class="cloneStep"><b>1. Voice</b><br>Neutral, excited, serious, whisper, projection, numbers, names and emotional range.</div><div class="cloneStep"><b>2. Face</b><br>Front, 45° left/right, profile left/right, up/down, expressions and lighting variations.</div><div class="cloneStep"><b>3. Full Body</b><br>Turn slowly, walk, stop, gesture, sit, point, present and natural conversation movement.</div><div class="cloneStep"><b>4. Wardrobe</b><br>Realtor, House Detective, speaking, music and casual approved looks.</div><div class="cloneStep"><b>5. Quality Gate</b><br>Scorsese checks framing, blur, exposure, audio and missing angles before completing capture.</div><div class="cloneStep"><b>6. Approved Uses</b><br>Every generated use remains tied to Mark’s consent profile and review workflow.</div></div><div class="warning">The capture tables are installed in V118.4. The guided camera wizard, quality checker and local model-training worker are the dedicated clone sprint after the long-form editor workers are operational.</div></div></section>
</div>
<script>
const KEY=<?=json_encode($key)?>;
document.querySelectorAll('.tab').forEach(btn=>btn.onclick=()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.tabPane').forEach(x=>x.classList.remove('active'));btn.classList.add('active');document.getElementById('tab-'+btn.dataset.tab).classList.add('active')});
if(location.hash.includes('upload')||location.hash.includes('new-project'))document.querySelector('[data-tab="upload"]').click();
if(location.hash.includes('renders'))document.querySelector('[data-tab="renders"]').click();

let uploadId='',uploaded=false;
const $=id=>document.getElementById(id);
function uid(){return 'scor_'+Date.now().toString(36)+'_'+crypto.getRandomValues(new Uint32Array(2)).join('_')}
function setProgress(n){$('uploadBar').style.width=Math.max(0,Math.min(100,n))+'%'}
async function uploadFile(file){
 uploadId=uid();uploaded=false;$('finalizeUpload').disabled=true;
 const chunkSize=parseInt($('chunkSize').value,10),total=Math.ceil(file.size/chunkSize);
 $('uploadStatus').textContent=`Starting ${file.name} — ${(file.size/1048576).toFixed(1)} MB in ${total} chunks.`;
 for(let i=0;i<total;i++){
  const fd=new FormData();fd.append('key',KEY);fd.append('action','chunk');fd.append('upload_id',uploadId);fd.append('chunk_index',i);fd.append('chunks_total',total);fd.append('filename',file.name);fd.append('project_name',$('project').value||'Scorsese Project');fd.append('brand',$('brand').value);fd.append('target',$('target').value);fd.append('production_mode',$('mode').value);fd.append('production_type',$('type').value);fd.append('source_goal',$('goal').value);fd.append('supplied_script',$('script').value);fd.append('chunk',file.slice(i*chunkSize,Math.min(file.size,(i+1)*chunkSize)),'chunk_'+i);
  const r=await fetch('/lead-engine/scorsese-chunk-upload-v118-4.php',{method:'POST',body:fd});const j=await r.json();if(!j.success)throw new Error(j.error||'Upload failed');
  setProgress((i+1)/total*100);$('uploadStatus').textContent=`Uploading ${file.name}: chunk ${i+1}/${total} — ${Math.round((i+1)/total*100)}%`;
 }
 uploaded=true;$('finalizeUpload').disabled=false;$('uploadStatus').textContent='All chunks stored safely. Add notes and finalize the Director project.';
}
$('file').onchange=e=>{if(e.target.files[0])uploadFile(e.target.files[0]).catch(err=>$('uploadStatus').textContent='Upload error: '+err.message)};
const drop=$('drop');drop.ondragover=e=>{e.preventDefault();drop.classList.add('drag')};drop.ondragleave=()=>drop.classList.remove('drag');drop.ondrop=e=>{e.preventDefault();drop.classList.remove('drag');if(e.dataTransfer.files[0])uploadFile(e.dataTransfer.files[0]).catch(err=>$('uploadStatus').textContent='Upload error: '+err.message)};
$('finalizeUpload').onclick=async()=>{
 if(!uploaded)return;
 const outputs=[...document.querySelectorAll('.checks input:checked')].map(x=>x.value),fd=new FormData();
 fd.append('key',KEY);fd.append('action','complete');fd.append('upload_id',uploadId);fd.append('director_notes',$('directorNotes').value);fd.append('desired_outputs',JSON.stringify(outputs));
 $('uploadStatus').textContent='Assembling file and creating Scorsese Director project...';
 const r=await fetch('/lead-engine/scorsese-chunk-upload-v118-4.php',{method:'POST',body:fd});const j=await r.json();
 if(j.success){$('uploadStatus').textContent='Director project created. Opening workstation...';location=j.project_url}else $('uploadStatus').textContent='Finalize error: '+(j.error||JSON.stringify(j));
};
</script></body></html>