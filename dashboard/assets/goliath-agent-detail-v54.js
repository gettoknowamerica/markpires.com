(function(){
  const agent = window.G54_AGENT || 'Goliath';
  let data={items:[],ready:[],queued:[]}, tab='ready', selected=null;
  const $=id=>document.getElementById(id);
  const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const val=(obj,keys,fb='')=>{for(const k of [].concat(keys)){if(obj&&obj[k]!==undefined&&obj[k]!==null&&obj[k]!=='' && !(typeof obj[k]==='object' && !Object.keys(obj[k]).length))return obj[k]}return fb};

  function payload(it){return it && it.payload ? it.payload : {};}
  function pretty(v){try{return JSON.stringify(v,null,2)}catch(e){return String(v)}}
  function isObj(v){return v && typeof v==='object' && !Array.isArray(v)}
  function isLeadKind(kind){return /lead|owner|hunter|research|appointment|communication|priority|opportunity|hot|valuation/i.test(kind||'')}
  function unescapeText(s){
    s=String(s??'');
    // If Supabase stored escaped line breaks inside JSON strings, make them readable.
    return s.replace(/\\r\\n/g,'\n').replace(/\\n/g,'\n').replace(/\\t/g,'  ').replace(/\\"/g,'"').replace(/\\\//g,'/');
  }
  function firstJson(text){
    if(!text || typeof text!=='string') return null;
    const raw=text.trim();
    try{const j=JSON.parse(raw); if(j && typeof j==='object') return j;}catch(e){}
    let m=raw.match(/```json\s*([\s\S]*?)```/i);
    if(m){try{const j=JSON.parse(m[1].trim()); if(j && typeof j==='object') return j;}catch(e){}}
    const s=raw.indexOf('{'), e=raw.lastIndexOf('}');
    if(s>=0 && e>s){try{const j=JSON.parse(raw.slice(s,e+1)); if(j && typeof j==='object') return j;}catch(err){}}
    const a=raw.indexOf('['), z=raw.lastIndexOf(']');
    if(a>=0 && z>a){try{const j=JSON.parse(raw.slice(a,z+1)); if(j && typeof j==='object') return j;}catch(err){}}
    return null;
  }
  function cleanMarkdown(s){
    s=unescapeText(s||'');
    s=s.replace(/^#+\s*/gm,'');
    s=s.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>');
    s=s.replace(/\n\s*[-•]\s+/g,'\n• ');
    const parts=s.split(/\n{2,}/).map(p=>p.trim()).filter(Boolean);
    if(!parts.length) return '<p>—</p>';
    return parts.map(p=>{
      if(p.startsWith('• ')) return '<ul>'+p.split(/\n/).filter(Boolean).map(x=>'<li>'+esc(x.replace(/^•\s*/,''))+'</li>').join('')+'</ul>';
      return '<p>'+p.replace(/\n/g,'<br>')+'</p>';
    }).join('');
  }
  function workData(it){
    const p=payload(it);
    let cj=val(p,['content_json'],null);
    if(typeof cj==='string') cj=firstJson(cj) || cj;
    if(isObj(cj) && Object.keys(cj).length) return cj;
    const fromText=firstJson(val(p,['content_text','output','message','summary','notes'],''));
    if(fromText) return fromText;
    const raw=val(p,['raw_payload','provider_response','metadata'],null);
    if(typeof raw==='string') return firstJson(raw)||raw;
    if(isObj(raw)) return raw;
    return p;
  }
  function outputText(it){
    const p=payload(it), wd=workData(it);
    if(isObj(wd)){
      const out=val(wd,['output','summary','markdown','message','notes','recommended_action'],'');
      if(out) return unescapeText(out);
      if(Array.isArray(wd.next_actions)) return wd.next_actions.join('\n');
    }
    return unescapeText(val(p,['content_text','output','summary','message','notes'],it.summary||''));
  }
  function titleFrom(it){return it.title || val(payload(it),['title','name','lead_name','owner_name'],'Work Item')}

  function parseMissionReportText(text){
    text=unescapeText(text||'');
    const nested=firstJson(text);
    const status=(text.match(/\*\*Status:\*\*\s*([^\n*]+)/i)||text.match(/Status:\s*([^\n]+)/i)||[])[1]||'';
    const summary=(text.match(/\*\*Summary:\*\*\s*([^\n]+)/i)||text.match(/Summary:\s*([^\n]+)/i)||[])[1]||'';
    const next=(text.match(/\*\*Next Action:\*\*\s*([^\n]+)/i)||text.match(/Next Action:\s*([^\n]+)/i)||[])[1]||'';
    const clean=text
      .replace(/```json[\s\S]*?```/ig,'')
      .replace(/\*\*Results:\*\*[\s\S]*?(?=\*\*Next Action:|\*\*Lead\/Content\/Opportunity Records:|$)/i,'')
      .replace(/\{\s*"[\s\S]*\}\s*$/,'')
      .trim();
    return {status:status.trim(), summary:summary.trim(), next_action:next.trim(), nested:nested, clean:clean};
  }
  function smartData(it){
    const wd=workData(it), text=outputText(it);
    const report=parseMissionReportText(text);
    if(report.nested && isObj(report.nested)){
      return Object.assign({}, wd && isObj(wd)?wd:{}, report.nested, {
        _report_status: report.status,
        _report_summary: report.summary,
        _report_next_action: report.next_action,
        _clean_report: report.clean
      });
    }
    return Object.assign({}, wd && isObj(wd)?wd:{}, {
      _report_status: report.status,
      _report_summary: report.summary,
      _report_next_action: report.next_action,
      _clean_report: report.clean
    });
  }
  function cleanSnippet(s, max=140){
    s=unescapeText(s||'');
    const j=firstJson(s);
    if(j && isObj(j)){
      const out=val(j,['summary','recommended_action','message','notes'],'') || val(j,['output'],'');
      if(out) s=unescapeText(out);
    }
    const report=parseMissionReportText(s);
    if(report.summary) s=report.summary;
    else if(report.clean) s=report.clean;
    s=s.replace(/```json[\s\S]*?```/ig,'')
       .replace(/\{\s*"[\s\S]*/,'')
       .replace(/[{}\[\]"\\]/g,' ')
       .replace(/\s+/g,' ')
       .trim();
    return s.length>max?s.slice(0,max-1)+'…':s;
  }
  function listSubtitle(it){
    const p=payload(it), sd=smartData(it), kind=(it.kind||'');
    if(isLeadKind(kind)){
      const phone=val(Object.assign({},p,sd),['phone','lead_phone','from_number','to_number'],'');
      const score=it.score?`Score ${it.score}`:'';
      return [cleanSnippet(it.subtitle||it.summary,90), phone?`Phone: ${phone}`:'', score].filter(Boolean).join(' · ');
    }
    return cleanSnippet(val(sd,['_report_summary','summary','recommended_action'],'') || it.subtitle || it.summary || outputText(it), 150);
  }

  function renderList(){
    const q=($('g54Search')?.value||'').toLowerCase();
    const arr=(tab==='ready'?data.ready:tab==='queued'?data.queued:data.items).filter(it=>(titleFrom(it)+' '+(it.subtitle||'')+' '+(it.kind||'')+' '+(it.source||'')).toLowerCase().includes(q));
    $('g54List').innerHTML=arr.length?arr.map(it=>`<div class="g54WorkItem ${selected&&selected.id===it.id&&selected.source===it.source?'active':''}" data-index="${data.items.indexOf(it)}"><strong>${esc(titleFrom(it))}</strong><small>${esc(listSubtitle(it)||'Open work')}</small><div class="g54Meta"><span class="g54Pill">${esc(it.kind)}</span><span class="g54Pill">${esc(it.status)}</span>${it.score?`<span class="g54Pill">Score ${esc(it.score)}</span>`:''}<span class="g54Pill">${esc(it.source)}</span></div></div>`).join(''):'<div class="g54Skeleton">No items in this view yet. When the Executive completes work, it appears here.</div>';
    document.querySelectorAll('.g54WorkItem').forEach(el=>el.onclick=()=>openItem(data.items[Number(el.dataset.index)]));
  }
  function renderFields(p, wd){
    const src=isObj(wd)?Object.assign({},p,wd):p;
    const fields=[['Name',['name','owner_name','lead_name','contact_name','title']],['Phone',['phone','lead_phone','from_number','to_number']],['Email',['email','lead_email']],['Address',['address','property_address']],['Town',['town','location']],['Source',['source','platform','lead_source']],['Status',['status','priority']],['Reason / Notes',['reason','notes','message','recommended_action','summary','jessica_summary']]];
    return `<div class="g54LeadGrid">`+fields.map(([label,keys])=>`<div class="g54Field"><b>${label}</b><span>${esc(unescapeText(val(src,keys,'—')))}</span></div>`).join('')+`</div>`;
  }
  function renderActions(p, wd){
    const src=isObj(wd)?Object.assign({},p,wd):p;
    const phone=val(src,['phone','lead_phone','from_number','to_number']); const email=val(src,['email','lead_email']); const address=val(src,['address','property_address']);
    let html='<div class="g54ActionBar">';
    if(phone) html+=`<a class="gold" href="tel:${esc(phone)}">Call</a>`;
    if(email) html+=`<a href="mailto:${esc(email)}">Email</a>`;
    if(address) html+=`<a target="_blank" href="https://www.google.com/maps/search/${encodeURIComponent(address)}">Map</a>`;
    html+=`<button onclick="navigator.clipboard.writeText(document.getElementById('g54Canvas').innerText)">Copy Work</button></div>`;
    return html;
  }
  function renderArrayCards(label, arr){
    if(!Array.isArray(arr) || !arr.length) return '';
    return `<h3>${esc(label)}</h3><div class="g54MiniCards">`+arr.map(x=>{
      if(isObj(x)) return `<div class="g54MiniCard">${Object.entries(x).slice(0,8).map(([k,v])=>`<b>${esc(k.replace(/_/g,' '))}</b><span>${esc(unescapeText(typeof v==='object'?pretty(v):v))}</span>`).join('')}</div>`;
      return `<div class="g54MiniCard"><span>${esc(unescapeText(x))}</span></div>`;
    }).join('')+`</div>`;
  }
  function renderStructured(it){
    const p=payload(it), wd=smartData(it), text=outputText(it);
    let html='';
    if(isObj(wd)){
      const status=val(wd,['_report_status','status'],'');
      const summary=val(wd,['_report_summary','summary','recommendation'],'');
      const next=val(wd,['_report_next_action','recommended_action'],'');
      if(status||summary||next){
        html+=`<div class="g54Summary">${status?`<p><strong>Status:</strong> ${esc(status)}</p>`:''}${summary?`<p><strong>Summary:</strong> ${esc(unescapeText(summary))}</p>`:''}${next?`<p><strong>Next Action:</strong> ${esc(unescapeText(next))}</p>`:''}</div>`;
      }
      const keys=['leads','emails','followups','notifications','scores','top_priorities','call_order','clips','titles','thumbnail_prompts','seo','hooks','best_verses','best_instrument_sections','opportunities','venues','directories','business_ideas','next_actions'];
      keys.forEach(k=>{ if(Array.isArray(wd[k])) html+=renderArrayCards(k.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()),wd[k]); });
      if(wd.projected_value) html+=`<div class="g54ValueCallout">Projected Value: ${esc(wd.projected_value)}</div>`;
    }
    if(!html && text) html=cleanMarkdown(parseMissionReportText(text).clean||text);
    if(!html) html='<p>No clean visual summary found yet. Raw file is available below.</p>';
    return html;
  }
  function renderRawDetails(p, wd){
    return `<details class="g54Details"><summary>Open full raw intelligence / source data</summary><pre>${esc(pretty(isObj(wd)&&Object.keys(wd).length?wd:p))}</pre></details>`;
  }
  function renderContent(it){
    const p=payload(it), wd=smartData(it), kind=(it.kind||'').toLowerCase();
    if(agent==='Shakespeare' || kind.includes('content') || kind.includes('blog')){
      const html=(isObj(wd)&&wd.html)||val(p,['html'],'');
      const md=(isObj(wd)&&wd.markdown)||val(p,['markdown'],'')||outputText(it);
      return `<article class="g54Doc g54Paper"><h1>${esc(titleFrom(it))}</h1>${html?html:cleanMarkdown(md)}${renderRawDetails(p,wd)}</article>`;
    }
    if(agent==='Scorsese' || kind.includes('video') || kind.includes('media') || kind.includes('creative')){
      const media=val(isObj(wd)?Object.assign({},p,wd):p,['media_url','video_url','source_url','url'],'');
      return `<div class="g54Doc"><h1>${esc(titleFrom(it))}</h1>${media?`<video controls playsinline style="width:100%;border-radius:14px;background:#000" src="${esc(media)}"></video>`:''}<h3>Production Package</h3>${renderActions(p,wd)}${renderStructured(it)}${renderRawDetails(p,wd)}</div>`;
    }
    if(isLeadKind(kind)){
      return `<div class="g54Doc"><h1>${esc(titleFrom(it))}</h1><p>${esc(listSubtitle(it)||'')}</p>${renderActions(p,wd)}<div class="g54LeadCard">${renderFields(p,wd)}</div><h3>Work Summary</h3>${renderStructured(it)}${renderRawDetails(p,wd)}</div>`;
    }
    return `<div class="g54Doc"><h1>${esc(titleFrom(it))}</h1><p>${esc(listSubtitle(it)||'')}</p>${renderActions(p,wd)}<h3>Work Product</h3>${renderStructured(it)}${renderRawDetails(p,wd)}</div>`;
  }
  function openItem(it){selected=it; $('g54Kind').textContent=(it.kind||'work')+' · '+(it.status||'ready'); $('g54Title').textContent=titleFrom(it)||'Work Item'; $('g54Score').textContent=it.score?it.score:'—'; $('g54Canvas').classList.remove('g54CanvasEmpty'); $('g54Canvas').innerHTML=renderContent(it); renderList();}
  async function load(){
    try{ const res=await fetch('/lead-engine/goliath-work-queues.php?department='+encodeURIComponent(agent)+'&v=541'); data=await res.json(); if(!data.items)data={items:[],ready:[],queued:[]}; $('g54Stats').innerHTML=`<div><strong>${data.ready_count||0}</strong><span>Finished Work</span></div><div><strong>${data.queued_count||0}</strong><span>Queued / Working</span></div><div><strong>${data.count||0}</strong><span>Total Office Items</span></div>`; renderList(); if(data.ready&&data.ready[0]) openItem(data.ready[0]); else if(data.items&&data.items[0]) openItem(data.items[0]); }
    catch(e){$('g54List').innerHTML='<div class="g54Skeleton">Could not load this office queue.</div>';}
  }
  document.addEventListener('click',e=>{const b=e.target.closest('.g54Tabs button'); if(b){document.querySelectorAll('.g54Tabs button').forEach(x=>x.classList.remove('active')); b.classList.add('active'); tab=b.dataset.tab; renderList();}});
  document.addEventListener('input',e=>{if(e.target&&e.target.id==='g54Search')renderList();});
  window.g54CopyPrompt=function(){const t=$('g54Prompt').value; navigator.clipboard.writeText(t||''); alert('Prompt copied.');}
  load();
})();
