(function(){
const $=s=>document.querySelector(s);
const file=$('#mediaFile'), picked=$('#filePicked'), fill=$('#uploadFill'), pct=$('#uploadPct'), text=$('#uploadText'), start=$('#startUpload'), res=$('#result');
let template='Discover CT';
document.querySelectorAll('.templateBtn').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.templateBtn').forEach(x=>x.classList.remove('active'));b.classList.add('active');template=b.dataset.template;}));
file && file.addEventListener('change',()=>{if(file.files[0]){picked.textContent=file.files[0].name+' · '+formatBytes(file.files[0].size); pct.textContent='0%'; text.textContent='Ready to upload'; fill.style.width='0%';}});
function formatBytes(bytes){if(!bytes)return '0 B';const u=['B','KB','MB','GB','TB'];let i=0,n=bytes;while(n>=1024&&i<u.length-1){n/=1024;i++;}return n.toFixed(n>=10?1:2)+' '+u[i];}
function setProgress(n,msg){n=Math.max(0,Math.min(100,Math.round(n)));fill.style.width=n+'%';pct.textContent=n+'%';if(msg)text.textContent=msg;}
async function uploadChunked(f){
 const chunkSize=8*1024*1024; const total=Math.ceil(f.size/chunkSize); const uploadId=(Date.now()+'-'+Math.random().toString(16).slice(2));
 let stored=null;
 for(let i=0;i<total;i++){
  const chunk=f.slice(i*chunkSize, Math.min(f.size,(i+1)*chunkSize));
  const fd=new FormData(); fd.append('key',window.SCM_KEY||''); fd.append('upload_id',uploadId); fd.append('chunk_index',i); fd.append('total_chunks',total); fd.append('filename',f.name); fd.append('chunk',chunk); fd.append('project_name',$('#projectName').value||f.name); fd.append('brand',$('#brand').value||'Mark Pires'); fd.append('town',$('#town').value||''); fd.append('aspect_ratio',$('#aspect').value||'9:16'); fd.append('template',template); fd.append('director_notes',$('#directorNotes').value||'');
  const r=await fetch('/lead-engine/scorsese-media-chunk-upload.php',{method:'POST',body:fd}); const j=await r.json(); if(!j.success) throw new Error(j.error||'Upload failed'); stored=j; setProgress(((i+1)/total)*100, 'Uploaded '+(i+1)+' of '+total+' chunks');
 }
 return stored;
}
start && start.addEventListener('click',async()=>{try{if(!file.files[0]){res.innerHTML='⚠️ Choose a file first.';return;} start.disabled=true; res.innerHTML='Creating production...'; setProgress(1,'Starting upload'); const j=await uploadChunked(file.files[0]); setProgress(100,'Stored successfully'); res.innerHTML='✅ Production stored and Scorsese commissioned. '+(j.project_id?('Project ID: '+j.project_id):''); setTimeout(()=>location.reload(),1200);}catch(e){res.innerHTML='⚠️ '+e.message; text.textContent='Upload issue';}finally{start.disabled=false;}});
})();
