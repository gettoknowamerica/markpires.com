/* V35.1 Director Quick Commands */
(function(){
  window.directorCreateVideo=async function(prompt, metadata){
    if(!prompt) prompt=prompt || window.prompt("What should Director create as a video?");
    if(!prompt) return;
    if(window.gToast) gToast("Director received video mission", prompt.substring(0,100));
    const r=await fetch('/lead-engine/director-create.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({key:'timetomakethedonuts',type:'director_video',prompt,metadata:metadata||{},source:'dashboard'})
    });
    const j=await r.json();
    if(window.gToast) gToast(j.success?'Director queued video':'Director issue', j.success?'Wan/Comfy job queued.':'Check director-create endpoint.');
    return j;
  }
  window.directorCreateImage=async function(prompt, metadata){
    if(!prompt) prompt=prompt || window.prompt("What should Director create as an image?");
    if(!prompt) return;
    if(window.gToast) gToast("Director received image mission", prompt.substring(0,100));
    const r=await fetch('/lead-engine/director-create.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({key:'timetomakethedonuts',type:'director_image',prompt,metadata:metadata||{},source:'dashboard'})
    });
    const j=await r.json();
    if(window.gToast) gToast(j.success?'Director queued image':'Director issue', j.success?'Flux/Comfy job queued.':'Check director-create endpoint.');
    return j;
  }
})();