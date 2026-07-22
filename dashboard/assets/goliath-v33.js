
(function(){
  function ensurePanel(){
    if(document.getElementById('gIntelPanel')) return;
    const back=document.createElement('div'); back.className='gIntelBackdrop'; back.id='gIntelBackdrop'; back.onclick=closeGoliathIntel;
    const panel=document.createElement('div'); panel.className='gIntelPanel'; panel.id='gIntelPanel';
    panel.innerHTML=`<div class="gIntelHead"><h2 id="gIntelTitle">Opportunity Intelligence</h2><div class="gIntelActions"><button class="gClose" onclick="closeGoliathIntel()">Close</button></div></div><div class="gIntelBody" id="gIntelBody"></div>`;
    document.body.appendChild(back); document.body.appendChild(panel);
  }
  window.closeGoliathIntel=function(){document.getElementById('gIntelBackdrop')?.classList.remove('open');document.getElementById('gIntelPanel')?.classList.remove('open');}
  window.openGoliathIntel=function(data){
    ensurePanel(); data=data||{};
    const name=data.name||data.title||'Opportunity';
    const phone=(data.phone||'').replace(/[^\d+]/g,'');
    const email=data.email||'';
    document.getElementById('gIntelTitle').textContent=name;
    document.getElementById('gIntelBody').innerHTML=`
      <div class="gIntelBox"><h3>Primary Contact</h3><div class="gIntelValue">${name}<br>${email||'No email'}<br>${data.phone||'No phone yet'}</div><div class="gIntelActions">${phone?`<a class="gCall" href="tel:${phone}">📞 Call</a>`:''}${phone?`<a class="gText" href="sms:${phone}">💬 Text</a>`:''}<button class="gDrip" onclick="alert('Commit Follow-Up queued for Goliath/Jessica')">⭐ Commit Follow-Up</button></div></div>
      <div class="gIntelBox"><h3>Drip Status</h3><div class="gIntelValue">${data.drip_status||'Not Set'}</div></div>
      <div class="gIntelBox"><h3>What We Know</h3><div class="gIntelValue">${data.notes||data.detail||data.summary||'No enriched notes yet. Goliath should research this opportunity and fill this record.'}</div></div>
      <div class="gIntelBox"><h3>Property / Venue / Opportunity</h3><div class="gIntelValue">${data.address||data.property||data.venue||'No property/venue attached yet.'}</div></div>
      <div class="gIntelBox"><h3>Recommended Action</h3><div class="gIntelValue">${data.recommended_action||'Research, enrich, then decide whether this is a call, content, or campaign opportunity.'}</div></div>
      <div class="gIntelBox"><h3>Goliath Content Angle</h3><div class="gIntelValue">${data.content_angle||'Create personalized content around the person’s pain point, then repurpose it to blog/social/SEO.'}</div></div>`;
    document.getElementById('gIntelBackdrop').classList.add('open'); document.getElementById('gIntelPanel').classList.add('open');
  }
})();
