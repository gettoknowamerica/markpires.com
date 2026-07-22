(function(){
const $=s=>document.querySelector(s);
const file=$('#mediaFile'), picked=$('#filePicked'), fill=$('#uploadFill'), pct=$('#uploadPct'), text=$('#uploadText'), start=$('#startUpload'), res=$('#result');
let template='Discover CT';
document.querySelectorAll('.templateBtn').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.templateBtn').forEach(x=>x.classList.remove('active'));b.classList.add('active');template=b.dataset.template;const s=$('#templateSummary'); if(s)s.textContent='Production Template: '+template;}));
file && file.addEventListener('change',()=>{if(file.files[0]){picked.textContent=file.files[0].name+' · '+formatBytes(file.files[0].size); pct.textContent='0%'; text.textContent='Ready to upload'; fill.style.width='0%';}});
document.querySelectorAll('.project').forEach(p=>p.addEventListener('click',e=>{if(e.target.tagName==='A')return;const u=p.dataset.url;if(u&&u!=='#'){const v=$('#mainPreview');v.src=u;v.load();const empty=$('#screenEmpty');if(empty)empty.style.display='none';}}));
function formatBytes(bytes){if(!bytes)return '0 B';const u=['B','KB','MB','GB','TB'];let i=0,n=bytes;while(n>=1024&&i<u.length-1){n/=1024;i++;}return n.toFixed(n>=10?1:2)+' '+u[i];}
function setProgress(n,msg){n=Math.max(0,Math.min(100,Math.round(n)));fill.style.width=n+'%';pct.textContent=n+'%';if(msg)text.textContent=msg;}
function chunkSizeFor(f){
  // V58.6: size-based chunks, not time-based. Big enough to avoid 20 pieces for a short clip, conservative enough for mobile/Hostinger.
  if(f.size > 8*1024*1024*1024) return 256*1024*1024;
  if(f.size > 1024*1024*1024) return 192*1024*1024;
  return 96*1024*1024;
}
async function uploadChunked(f){
 const chunkSize=chunkSizeFor(f); const total=Math.ceil(f.size/chunkSize); const uploadId=(Date.now()+'-'+Math.random().toString(16).slice(2));
 let stored=null;
 for(let i=0;i<total;i++){
  const chunk=f.slice(i*chunkSize, Math.min(f.size,(i+1)*chunkSize));
  const fd=new FormData(); fd.append('key',window.SCM_KEY||''); fd.append('upload_id',uploadId); fd.append('chunk_index',i); fd.append('total_chunks',total); fd.append('filename',f.name); fd.append('chunk',chunk); fd.append('project_name',$('#projectName').value||f.name); fd.append('brand',$('#brand').value||'Mark Pires'); fd.append('town',$('#town').value||''); fd.append('aspect_ratio',$('#aspect').value||'9:16'); fd.append('template',template); fd.append('director_notes',$('#directorNotes').value||'');
  const r=await fetch('/lead-engine/scorsese-media-chunk-upload.php',{method:'POST',body:fd}); const j=await r.json(); if(!j.success) throw new Error(j.error||'Upload failed'); stored=j; setProgress(((i+1)/total)*100, 'Uploaded '+(i+1)+' of '+total+' chunks · '+formatBytes(chunkSize)+' chunks');
 }
 return stored;
}
start && start.addEventListener('click',async()=>{try{if(!file.files[0]){res.innerHTML='⚠️ Choose media first, or use the Video Prompt box under the screen for no-footage creations.';return;} start.disabled=true; res.innerHTML='Creating production...'; setProgress(1,'Starting upload'); const j=await uploadChunked(file.files[0]); setProgress(100,'Stored successfully'); res.innerHTML='✅ Production stored and Scorsese commissioned. '+(j.project_id?('Project ID: '+j.project_id):''); setTimeout(()=>location.reload(),1200);}catch(e){res.innerHTML='⚠️ '+e.message; text.textContent='Upload issue';}finally{start.disabled=false;}});
const promptBtn=$('#commissionPrompt');
promptBtn && promptBtn.addEventListener('click',async()=>{const prompt=($('#videoPrompt').value||'').trim(); if(!prompt){$('#promptResult').innerHTML='⚠️ Add the video concept first.';return;} promptBtn.disabled=true; $('#promptResult').innerHTML='Commissioning Scorsese prompt video...'; try{const r=await fetch('/lead-engine/scorsese-prompt-commission.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:window.SCM_KEY||'',project_name:$('#promptProjectName').value||'Prompt Video',brand:'LegacySaved',template:'Video Prompt',aspect_ratio:'9:16',prompt:prompt})}); const j=await r.json(); if(!j.success) throw new Error(j.error||'Prompt commission failed'); $('#promptResult').innerHTML='✅ Prompt video commissioned. Scorsese will use the Director Video pipeline and plugin registry.'; setTimeout(()=>location.reload(),1000);}catch(e){$('#promptResult').innerHTML='⚠️ '+e.message;}finally{promptBtn.disabled=false;}});
})();
