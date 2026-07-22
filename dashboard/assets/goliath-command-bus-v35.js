
/* V35 Goliath Command Bus */
(function(){
  window.goliathCommand=async function(payload){
    payload=payload||{};
    if(!payload.title) payload.title='Goliath command';
    if(!payload.department) payload.department='Executive';
    if(!payload.command_type) payload.command_type='general';

    if(window.gToast) gToast('Goliath received the mission', payload.title);

    try{
      const r=await fetch('/lead-engine/goliath-event-bus.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(Object.assign({action:'command'},payload))
      });
      const j=await r.json();
      if(j.success){
        if(window.gToast) gToast('Mission queued', (j.command&&j.command.department?j.command.department:payload.department)+' is taking ownership.');
      }else{
        if(window.gToast) gToast('Mission not queued', j.error||'Event bus rejected the command.');
      }
      return j;
    }catch(e){
      if(window.gToast) gToast('Event bus issue','Command could not reach Goliath event bus.');
      return {success:false,error:String(e)};
    }
  }

  window.goliathSellerPackage=function(data){
    return goliathCommand({
      command_type:'seller_package',
      department:'Executive',
      title:'Experience Department activated',
      prompt:`Create a complete seller opportunity package for ${data.name||'Owner'} at ${data.address||''}. Include call script, text, email, door knock letter, House Detective pitch, and three marketing pieces.`,
      priority:115,
      roi_estimate:data.roi_estimate||25000,
      metadata:data||{}
    });
  }

  window.goliathScoutSearch=function(detail){
    return goliathCommand({
      command_type:'scout_search',
      department:'Scout',
      title:'Scout Search started',
      prompt:detail||'Search and enrich the highest-value real estate opportunities.',
      priority:105,
      roi_estimate:12000
    });
  }
})();
