(() => {
 const KEY=window.GOLIATH_V111_KEY||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

 async function refreshTruth(){
  try{
   const response=await fetch('/lead-engine/goliath-v112-truth.php?key='+encodeURIComponent(KEY)+'&_='+Date.now(),{cache:'no-store'});
   const data=await response.json();
   if(!response.ok||!data.ok)return;

   document.querySelectorAll('[data-v112-finished]').forEach(node=>node.textContent=String(data.counts.review_ready||0));

   const total=document.querySelector('.scoreboard .total');
   if(total)total.innerHTML='<span data-v112-finished>'+String(data.counts.review_ready||0)+'</span>';

   const brain=document.querySelector('.goliathMini p');
   if(brain)brain.innerHTML=
    `Ready for Review: <b>${data.counts.review_ready||0}</b><br>`+
    `Finished Today: <b>${data.counts.finished_today||0}</b><br>`+
    `Founder Priorities: <b>${data.counts.founder_priority||0}</b><br>`+
    `Active Missions: <b>${data.counts.active_missions||0}</b>`;

   const executiveMap={};
   for(const executive of data.executives||[])executiveMap[executive.executive_key]=executive;

   document.querySelectorAll('.agentCell[data-executive]').forEach(cell=>{
    const key=cell.dataset.executive;
    const executive=executiveMap[key]||{};
    const current=(data.active_stages||[]).find(stage=>stage.executive_key===key);
    const working=Number(executive.working||0);
    const ready=Number(executive.ready||0);
    const waiting=Number(executive.waiting||0);

    const metric=cell.querySelector('.metric');
    if(metric)metric.textContent=working?'WORK':(ready?'NEXT':(waiting?'WAIT':'0'));

    const task=cell.querySelector('.taskText');
    if(task){
     if(current){
      task.innerHTML=`<strong>${esc(current.status)}:</strong> ${esc(current.mission_title||current.title)}`;
     }else if(waiting){
      task.innerHTML=`<strong>waiting:</strong> ${waiting} mission stage${waiting===1?'':'s'} in the collaboration ring`;
     }else{
      task.innerHTML='<strong>ready:</strong> Finding the next real assignment';
     }
    }

    const percent=current?
      (current.status==='working'?55:(current.status==='queued_local'?20:8)):
      (waiting?3:0);
    const bar=cell.querySelector('.battery b');
    const label=cell.querySelector('.meterLine em');
    if(bar)bar.style.width=percent+'%';
    if(label)label.textContent=percent+'%';
   });

   const list=document.querySelector('.activityList');
   if(list){
    const rows=[];
    for(const mission of (data.founder_priority_missions||[]).slice(0,5)){
     rows.push(
      `<a class="activityItem monitor-link" href="${esc(mission.url||'#')}">`+
      `<span class="aiIcon">🚨</span><span><b>${esc(mission.title)}</b>`+
      `<small>Founder priority · ${esc(mission.current_executive)} · ${esc(mission.stage_title)}</small></span>`+
      `<em>PRIORITY</em></a>`
     );
    }
    for(const asset of (data.review_assets||[]).slice(0,7)){
     rows.push(
      `<a class="activityItem monitor-link" href="${esc(asset.review_url)}">`+
      `<span class="aiIcon">✅</span><span><b>${esc(asset.title||asset.mission_title||'Completed asset')}</b>`+
      `<small>${esc(asset.originator_key||asset.executive_key)} · ${esc(asset.artifact_type)} · click for deliverable</small></span>`+
      `<em>REVIEW</em></a>`
     );
    }
    if(rows.length)list.innerHTML=rows.slice(0,9).join('');
   }

   const scoreboard=document.querySelector('.scoreboard');
   if(scoreboard){
    scoreboard.querySelectorAll('.scoreRow').forEach(row=>row.remove());
    const grouped={};
    for(const asset of data.review_assets||[]){
     const key=asset.originator_key||asset.executive_key||'goliath';
     grouped[key]=(grouped[key]||0)+1;
    }
    for(const [key,count] of Object.entries(grouped)){
     scoreboard.insertAdjacentHTML(
      'beforeend',
      `<a class="scoreRow monitor-link" href="/dashboard/goliath-review-center.php?exec=${encodeURIComponent(key)}&embed=1">`+
      `<b>${esc(key.charAt(0).toUpperCase()+key.slice(1))}</b><span>${count}</span></a>`
     );
    }
   }

   window.dispatchEvent(new CustomEvent('goliath:v118-truth',{detail:data}));
  }catch(error){
   console.error('V118 truth refresh',error);
  }
 }

 window.refreshV118Truth=refreshTruth;
 document.addEventListener('DOMContentLoaded',()=>{
  refreshTruth();
  setInterval(refreshTruth,5000);
 });
})();