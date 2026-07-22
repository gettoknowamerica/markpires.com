/* V35.8 Mission Brain client */
(function(){
  window.createGoliathMission=async function(payload){
    payload=payload||{};
    if(window.gToast) gToast('Rockefeller is creating mission',payload.title||'Goliath Mission');
    const r=await fetch('/lead-engine/goliath-mission-create.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify(Object.assign({key:'timetomakethedonuts'},payload))
    });
    const j=await r.json();
    if(j.success){
      if(window.gToast) gToast('Mission created',j.mission_id);
      return j;
    }
    if(window.gToast) gToast('Mission issue',j.error||'Could not create mission');
    return j;
  }
})();