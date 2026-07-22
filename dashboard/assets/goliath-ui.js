
/* ==========================================================
   GOLIATH OS UI KIT V34
   Shared drawer, toast, search, and command behavior.
   ========================================================== */

(function(){
  function ensureDrawer(){
    if(document.getElementById('gDrawer')) return;
    const back=document.createElement('div');
    back.className='g-drawerBackdrop';
    back.id='gDrawerBackdrop';
    back.onclick=closeGoliathDrawer;

    const panel=document.createElement('div');
    panel.className='g-drawer';
    panel.id='gDrawer';
    panel.innerHTML=`<div class="g-drawerHead">
      <h2 id="gDrawerTitle">Opportunity Intelligence</h2>
      <div class="g-drawerActions"><button class="g-btn g-btn-dark" onclick="closeGoliathDrawer()">Close</button></div>
    </div><div class="g-drawerBody" id="gDrawerBody"></div>`;

    document.body.appendChild(back);
    document.body.appendChild(panel);
  }

  window.closeGoliathDrawer=function(){
    document.getElementById('gDrawerBackdrop')?.classList.remove('open');
    document.getElementById('gDrawer')?.classList.remove('open');
  }

  window.openGoliathDrawer=function(data){
    ensureDrawer();
    data=data||{};
    const title=data.name||data.title||'Opportunity';
    const phone=(data.phone||'').replace(/[^\d+]/g,'');
    const email=data.email||'';
    document.getElementById('gDrawerTitle').textContent=title;
    document.getElementById('gDrawerBody').innerHTML=`
      <div class="g-drawerBox"><h3>Overview</h3><div class="g-drawerValue">${safe(title)}<br>${safe(data.address||data.property||data.venue||'No location attached yet.')}</div></div>
      <div class="g-drawerBox"><h3>Primary Contact</h3><div class="g-drawerValue">${safe(email||'No email')}<br>${safe(data.phone||'No phone yet')}</div>
        <div class="g-drawerActions">
          ${phone?`<a class="g-btn g-btn-blue" href="tel:${phone}">☎ Call</a>`:''}
          ${phone?`<a class="g-btn g-btn-green" href="sms:${phone}">💬 Text</a>`:''}
          ${email?`<a class="g-btn g-btn-blue" href="mailto:${safe(email)}">✉ Email</a>`:''}
          <button class="g-btn g-btn-gold" onclick="gToast('Follow-up queued','Jessica has been assigned this opportunity.')">⭐ Commit Follow-Up</button>
        </div>
      </div>
      <div class="g-drawerBox"><h3>Recommended Action</h3><div class="g-drawerValue">${safe(data.recommended_action||'Review, enrich, and assign the next highest-value action.')}</div></div>
      <div class="g-drawerBox"><h3>Goliath Content Angle</h3><div class="g-drawerValue">${safe(data.content_angle||'Create value-first personalized content, then repurpose it to blog, social, email, and follow-up.')}</div></div>
      <div class="g-drawerBox"><h3>Notes / Intelligence</h3><div class="g-drawerValue">${safe(data.notes||data.detail||data.summary||'No additional intelligence yet.')}</div></div>
      <div class="g-drawerBox"><h3>Status</h3><div class="g-drawerValue">${safe(data.drip_status||data.status||'Not Set')}</div></div>
    `;
    document.getElementById('gDrawerBackdrop').classList.add('open');
    document.getElementById('gDrawer').classList.add('open');
  }

  window.openGoliathIntel=window.openGoliathDrawer;

  window.gToast=function(title,detail){
    let box=document.getElementById('gVisualResult');
    if(!box){
      box=document.createElement('div');
      box.id='gVisualResult';
      box.className='g-visualResult';
      document.body.appendChild(box);
    }
    box.innerHTML='<strong>'+safe(title)+'</strong><br><span>'+safe(detail||'')+'</span>';
    box.style.display='block';
    setTimeout(()=>box.style.display='none',4200);
  }

  window.gFilterRows=function(inputId, rowSelector){
    const q=(document.getElementById(inputId)?.value||'').toLowerCase();
    document.querySelectorAll(rowSelector||'[data-search]').forEach(r=>{
      r.style.display=(r.dataset.search||'').toLowerCase().includes(q)?'':'none';
    });
  }

  function safe(str){
    return String(str||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
})();
