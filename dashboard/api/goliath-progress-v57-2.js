(function(){
  const agent=window.G54_AGENT||'Goliath';
  const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  function pct(n){n=parseInt(n||0,10);return Math.max(0,Math.min(100,n));}
  function statusLabel(s){s=String(s||'working');return s.replace(/_/g,' ');}
  function ago(iso){if(!iso)return '—';const d=new Date(iso);if(isNaN(d))return iso;const sec=Math.floor((Date.now()-d.getTime())/1000);if(sec<60)return sec+'s ago';const min=Math.floor(sec/60);if(min<60)return min+'m ago';const hr=Math.floor(min/60);if(hr<24)return hr+'h ago';return d.toLocaleString();}
  function panelHtml(data){
    const latest=data.latest||{}; const p=pct(latest.progress||0); const active=data.active||[]; const messages=data.messages||[];
    const blocked=latest.blocked?true:false;
    return `<section class="g57ProgressPanel ${blocked?'g57Blocked':''}" id="g57ProgressPanel">
      <div class="g57ProgressHead"><div><p>Executive Progress</p><h3>${esc(latest.title||agent+' is standing by')}</h3></div><div class="g57StatusPill">${esc(statusLabel(latest.status||'available'))}</div></div>
      <div class="g57ProgressTrack"><div class="g57ProgressFill" style="width:${p}%"></div></div>
      <div class="g57ProgressMeta"><span>${esc(latest.phase||'No active commission currently reporting progress.')}</span><strong>${p}%</strong></div>
      <div class="g57ProgressGrid">
        <div class="g57ProgressMetric"><b>Next Milestone</b><span>${esc(latest.next||'Waiting for next directive')}</span></div>
        <div class="g57ProgressMetric"><b>Updated</b><span>${esc(ago(latest.updated_at))}</span></div>
        <div class="g57ProgressMetric"><b>Assets</b><span>${esc(latest.asset_count||0)}</span></div>
        <div class="g57ProgressMetric"><b>Handoff</b><span>${esc(latest.handoff_to||'—')}</span></div>
      </div>
      ${latest.blocked?`<div class="g57ProgressTimeline"><strong>Blocking Issue:</strong> ${esc(latest.blocked)}</div>`:''}
      <div class="g57ProgressTimeline"><strong>Recent Executive Timeline</strong>
        ${(active.length?active.slice(0,4):messages.slice(0,4)).map(x=>`<div class="g57TimelineItem"><span class="g57Dot"></span><div><b>${esc(x.title||x.current_phase||x.message||'Executive update')}</b><br><span class="g57Muted">${esc(x.phase||x.current_phase||x.acknowledgement||x.status||'')}${x.updated_at||x.created_at?' · '+esc(ago(x.updated_at||x.created_at)):''}</span></div></div>`).join('')||'<div class="g57Muted">No progress updates yet.</div>'}
      </div>
    </section>`;
  }
  async function loadProgress(){
    try{
      const res=await fetch('/dashboard/api/executive-progress.php?executive='+encodeURIComponent(agent)+'&t='+(Date.now()));
      const data=await res.json(); if(!data.success) return;
      const stats=document.getElementById('g54Stats'); if(!stats) return;
      let existing=document.getElementById('g57ProgressPanel');
      if(existing) existing.outerHTML=panelHtml(data); else stats.insertAdjacentHTML('afterend',panelHtml(data));
    }catch(e){/* keep page quiet */}
  }
  window.g57LoadProgress=loadProgress;
  document.addEventListener('DOMContentLoaded',()=>{loadProgress(); setInterval(loadProgress,20000);});
})();
