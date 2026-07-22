function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function pct(rows){if(!rows||!rows.length)return 0;let d=rows.filter(r=>r.status==='complete').length;return Math.round((d/rows.length)*100)}
async function api(url,opt={}){const r=await fetch(url,opt);let j={};try{j=await r.json()}catch(e){}if(!r.ok||j.success===false)throw new Error(j.error||'Request failed');return j}
function selectLead(card){
  const lead=JSON.parse(card.dataset.lead||'{}');
  const journey=JSON.parse(card.dataset.journey||'[]');
  const artifacts=JSON.parse(card.dataset.artifacts||'[]');
  const commands=JSON.parse(card.dataset.commands||'[]');
  const towns=Array.isArray(lead.target_towns)?lead.target_towns.join(', '):'';
  const score=Number(lead.heat_score||0);
  const panel=document.getElementById('brainPanel');
  panel.innerHTML=`<div class="panelInner">
    <div class="selectedHero"><b>${esc(lead.name)}</b><span>${esc(lead.current_state||'Unknown')} → ${esc(towns||'Needs town')} · ${esc(lead.lead_type||'lead')}</span></div>
    <div class="evidence"><div><strong>${score}</strong><span>Heat Score</span></div><div><strong>$${Number(lead.pipeline_value||0).toLocaleString()}</strong><span>Pipeline</span></div><div><strong>${esc(lead.budget||'?')}</strong><span>Budget</span></div><div><strong>${esc(lead.timeline||'?')}</strong><span>Timeline</span></div></div>
    <p><b>Next Action:</b> ${esc(lead.next_action||'Waiting for Goliath recommendation.')}</p>
    <div class="pipeline"><i style="width:${pct(journey)}%"></i></div>
    <div class="journey"><h3>Lead Journey</h3>${journey.map(r=>`<div class="jRow ${esc(r.status)}"><div class="jDot">${r.status==='complete'?'✓':r.status==='running'?'…':'•'}</div><div><b>${esc(r.title||r.stage)}</b><small>${esc(r.agent||'Goliath')} · ${esc(r.detail||'Waiting')}</small></div><button onclick="markStage('${esc(lead.id)}','${esc(r.stage)}',this)">Complete</button></div>`).join('')}</div>
    <div class="artifactList"><h3>Artifacts</h3>${artifacts.length?artifacts.map(a=>`<div class="pillLine">${esc(a.agent||'Goliath')} · ${esc(a.artifact_type)} · ${esc(a.title||'Untitled')} · ${esc(a.status||'draft')}</div>`).join(''):'<div class="pillLine">No artifacts yet. Shakespeare, Columbo and Scorsese will fill this.</div>'}</div>
    <div class="commandList"><h3>Agent Commands</h3>${commands.length?commands.slice(0,8).map(c=>`<div class="pillLine">${esc(c.agent)} · ${esc(c.status)} · ${esc(c.command).slice(0,120)}</div>`).join(''):'<div class="pillLine">No queued commands for this lead yet.</div>'}</div>
  </div>`;
}
async function markStage(lead,stage,btn){btn.disabled=true;btn.textContent='Saving…';try{await api('/lead-engine/lead-brain-stage-update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:window.GOLIATH_KEY,lead_id:lead,stage:stage,status:'complete',detail:'Commissioning stage manually verified by Mark.'})});btn.textContent='Done';setTimeout(()=>location.reload(),550)}catch(e){btn.textContent='Error';alert(e.message)}}
document.addEventListener('DOMContentLoaded',()=>{const b=document.getElementById('createTestLead');if(b)b.onclick=async()=>{b.disabled=true;b.textContent='Creating…';try{const j=await api('/lead-engine/lead-brain-test-lead.php?key='+encodeURIComponent(window.GOLIATH_KEY));b.textContent='Created';setTimeout(()=>location.reload(),700)}catch(e){b.disabled=false;b.textContent='Create Test Lead';alert(e.message)}}});
function openBrainHelp(){alert('Lead Brain proof run:\n1. Create test lead.\n2. Watch journey rows appear.\n3. Agent commands queue.\n4. Mark each stage complete as systems pass.\n5. When all stages are green, the lead journey is commissioned.');}
