<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scorsese-chunk-upload.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scorsese Chunked Upload</title>
<style>
body{margin:0;background:#050812;color:#f8fafc;font-family:Inter,system-ui,Segoe UI,Arial}.wrap{max-width:1100px;margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;gap:14px;align-items:center}.btn{border:0;border-radius:12px;padding:11px 14px;background:#f5c85d;color:#111827;font-weight:900;text-decoration:none;cursor:pointer}.btn.dark{background:#111827;color:#fff;border:1px solid #334155}.panel{border:1px solid rgba(255,255,255,.12);background:#0b1020;border-radius:22px;padding:20px;margin-top:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field label{display:block;color:#f5c85d;font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;box-sizing:border-box;border:1px solid #334155;background:#050812;color:#fff;border-radius:12px;padding:12px}.field textarea{min-height:120px}.drop{border:2px dashed #475569;border-radius:22px;padding:30px;text-align:center;background:#07111f}.drop.drag{border-color:#f5c85d;background:#1a1404}.progress{height:18px;background:#172033;border-radius:999px;overflow:hidden;margin:16px 0;border:1px solid #334155}.bar{height:100%;width:0%;background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e);transition:width .25s}.status{color:#cbd5e1;white-space:pre-wrap}.checks{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px}.checks label{border:1px solid #334155;background:#07111f;border-radius:12px;padding:10px;color:#e5e7eb}@media(max-width:800px){.grid,.checks{grid-template-columns:1fr}.top{flex-direction:column;align-items:flex-start}}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Scorsese Chunked Upload</h1><p style="color:#cbd5e1">Large video intake for Discover CT, House Detective, BeatSeat, and LegacySaved.</p></div><div><a class="btn dark" href="/dashboard/scorsese-media-vault.php">Media Vault</a> <a class="btn dark" href="/dashboard/goliath-agent-detail.php?department=Scorsese">Scorsese Office</a></div></div>
<div class="panel">
  <div class="grid">
    <div class="field"><label>Project Name</label><input id="project" value="Scorsese Project"></div>
    <div class="field"><label>Brand</label><select id="brand"><option value="mark_pires">Mark Pires Real Estate</option><option value="discover_ct">Discover CT</option><option value="legacy_saved">LegacySaved</option><option value="beatseat">BeatSeat</option><option value="house_detective">House Detective</option></select></div>
    <div class="field"><label>Storage Target</label><select id="target"><option value="scorsese">Scorsese Raw</option><option value="legacy">LegacySaved Raw</option></select></div>
    <div class="field"><label>Chunk Size</label><select id="chunkSize"><option value="52428800">50 MB</option><option value="104857600" selected>100 MB</option><option value="262144000">250 MB</option></select></div>
  </div>
  <br>
  <div id="drop" class="drop"><h2>Drop video here</h2><p>or choose a file</p><input id="file" type="file" accept="video/*,audio/*"></div>
  <div class="progress"><div id="bar" class="bar"></div></div>
  <div id="status" class="status">Waiting for media...</div>
</div>
<div class="panel">
  <div class="field"><label>Director Notes</label><textarea id="notes" placeholder="Tell Scorsese what to make: multiple shorts, story arc, tone, hooks, captions, target platforms..."></textarea></div>
  <div class="checks">
    <label><input type="checkbox" value="youtube_master" checked> YouTube Master</label>
    <label><input type="checkbox" value="shorts" checked> Multiple Shorts</label>
    <label><input type="checkbox" value="instagram_reels" checked> Instagram Reels</label>
    <label><input type="checkbox" value="tiktok"> TikTok</label>
    <label><input type="checkbox" value="facebook"> Facebook</label>
    <label><input type="checkbox" value="legacy_film"> LegacySaved Film</label>
  </div>
  <br><button id="complete" class="btn" disabled>Finalize Upload & Commission Scorsese</button>
</div>
</div>
<script>
const KEY=<?=json_encode($key)?>;
let uploadId=null, selectedFile=null, uploaded=false;
const $=id=>document.getElementById(id); const status=(t)=>$('status').textContent=t; const pct=(n)=>$('bar').style.width=Math.max(0,Math.min(100,n))+'%';
function uid(){return 'scor_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,10)}
async function uploadFile(file){
  selectedFile=file; uploadId=uid(); uploaded=false; $('complete').disabled=true;
  const chunkSize=parseInt($('chunkSize').value,10); const total=Math.ceil(file.size/chunkSize);
  status(`Starting upload: ${file.name}\nSize: ${(file.size/1048576).toFixed(1)} MB\nChunks: ${total}`);
  for(let i=0;i<total;i++){
    const start=i*chunkSize, end=Math.min(file.size,start+chunkSize); const blob=file.slice(start,end);
    const fd=new FormData(); fd.append('key',KEY); fd.append('action','chunk'); fd.append('upload_id',uploadId); fd.append('chunk_index',i); fd.append('chunks_total',total); fd.append('filename',file.name); fd.append('project_name',$('project').value||'Scorsese Project'); fd.append('brand',$('brand').value); fd.append('target',$('target').value); fd.append('chunk',blob,'chunk_'+i);
    const r=await fetch('/lead-engine/scorsese-chunk-upload.php',{method:'POST',body:fd}); const j=await r.json();
    if(!j.success){status('Upload failed:\n'+JSON.stringify(j,null,2)); throw new Error(j.error||'upload failed');}
    pct(((i+1)/total)*100); status(`Uploading ${file.name}\nChunk ${i+1} of ${total}\n${Math.round(((i+1)/total)*100)}% complete`);
  }
  uploaded=true; $('complete').disabled=false; status(`✓ Upload chunks stored. Add director notes, then finalize.\nUpload ID: ${uploadId}`);
}
$('file').addEventListener('change',e=>{if(e.target.files[0]) uploadFile(e.target.files[0]).catch(console.error)});
const drop=$('drop'); drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('drag')}); drop.addEventListener('dragleave',()=>drop.classList.remove('drag')); drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('drag'); if(e.dataTransfer.files[0]) uploadFile(e.dataTransfer.files[0]).catch(console.error)});
$('complete').addEventListener('click',async()=>{
  if(!uploaded||!uploadId){status('No completed upload to finalize.');return;}
  const outputs=[...document.querySelectorAll('.checks input:checked')].map(x=>x.value);
  const fd=new FormData(); fd.append('key',KEY); fd.append('action','complete'); fd.append('upload_id',uploadId); fd.append('director_notes',$('notes').value); fd.append('desired_outputs',JSON.stringify(outputs));
  status('Finalizing and assembling file...'); const r=await fetch('/lead-engine/scorsese-chunk-upload.php',{method:'POST',body:fd}); const j=await r.json();
  if(j.success){status('✓ Stored successfully.\nURL: '+j.stored_url+'\nScorsese is ready to work.');} else {status('Finalize failed:\n'+JSON.stringify(j,null,2));}
});
</script></body></html>
