(async function(){
 const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
 const agents=['Scout','Jessica','Shakespeare','Scorsese','Mozart','Einstein','Rockefeller','Columbo','Prospector','Pandora','Goliath'];
 const box=document.getElementById('g54BriefGrid');
 function unescapeText(s){return String(s??'').replace(/\\r\\n/g,' ').replace(/\\n/g,' ').replace(/\\t/g,'  ').replace(/\\"/g,'"').replace(/\\\//g,'/');}
 function firstJson(text){ if(!text||typeof text!=='string')return null; const raw=text.trim(); try{const j=JSON.parse(raw); if(j&&typeof j==='object')return j;}catch(e){} const m=raw.match(/```json\s*([\s\S]*?)```/i); if(m){try{return JSON.parse(m[1].trim())}catch(e){}} const s=raw.indexOf('{'), e=raw.lastIndexOf('}'); if(s>=0&&e>s){try{return JSON.parse(raw.slice(s,e+1))}catch(err){}} return null; }
 function cleanSmall(s){s=unescapeText(s||'').replace(/```json[\s\S]*?```/gi,'').replace(/\*\*/g,'').replace(/\s+/g,' ').trim(); return s.length>130?s.slice(0,127)+'...':s;}
 function summary(it){
   const p=it.payload||{};
   let s=it.subtitle||it.summary||'Open work';
   let wd=p.content_json||p.content_text||p.output||p.summary||'';
   if(typeof wd==='string'){
     const j=firstJson(wd);
     if(j&&typeof j==='object'){
       if(j.summary) s=j.summary;
       else if(j.output){ const m=unescapeText(j.output).match(/\*\*Summary:\*\*\s*([^\n]+)/i); if(m) s=m[1]; }
       else if(Array.isArray(j.leads)) s=j.leads.length+' lead file(s) prepared.';
       else if(Array.isArray(j.hooks)) s=j.hooks.length+' music hook(s) identified.';
       else if(Array.isArray(j.opportunities)) s=j.opportunities.length+' opportunity item(s) prepared.';
     }
   } else if(wd&&typeof wd==='object'&&wd.summary) s=wd.summary;
   return cleanSmall(s);
 }
 async function get(a){try{return await (await fetch('/lead-engine/goliath-work-queues.php?department='+encodeURIComponent(a)+'&v=542')).json()}catch(e){return {ready:[],queued:[]}}}
 const all=await Promise.all(agents.map(async a=>[a,await get(a)]));
 box.innerHTML=all.map(([a,d])=>{
   const top=(d.ready||d.items||[]).slice(0,4);
   return `<div class="g54BriefCard"><h2>${esc(a)}</h2><p>${d.ready_count||0} finished · ${d.queued_count||0} queued</p>${top.length?top.map(it=>`<a href="/dashboard/goliath-agent-detail.php?department=${encodeURIComponent(a)}&item=${encodeURIComponent(it.id||'')}"><strong>${esc(it.title||'Work Item')}</strong><br><small>${esc(summary(it))}</small></a>`).join(''):'<p>No finished work yet.</p>'}<a class="btn" href="/dashboard/goliath-agent-detail.php?department=${encodeURIComponent(a)}">Open ${esc(a)} Office</a></div>`
 }).join('');
})();
