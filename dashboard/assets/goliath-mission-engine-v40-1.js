
/* V40.1 Mission Control Engine: force transform ownership + restore icons */
(function(){
  const stationDefs={
    Scout:[{name:'Desk',x:0,y:0},{name:'MLS Computer',x:78,y:-42},{name:'Phone Search',x:-84,y:-36},{name:'Cabinet A',x:-84,y:44},{name:'Cabinet B',x:74,y:48},{name:'Whiteboard',x:0,y:-60}],
    Jessica:[{name:'Desk',x:0,y:0},{name:'Phone',x:82,y:-24},{name:'Door',x:96,y:50},{name:'Email',x:-72,y:44},{name:'Drip Board',x:-50,y:-44}],
    Scorsese:[{name:'Edit Bay',x:0,y:0},{name:'Camera',x:-86,y:-40},{name:'Audio',x:90,y:-44},{name:'Render',x:88,y:44},{name:'Storyboard',x:-80,y:46}],
    Shakespeare:[{name:'Writing',x:0,y:0},{name:'Blog',x:-90,y:8},{name:'Social Wall',x:0,y:-56},{name:'Scheduler',x:92,y:34},{name:'SEO',x:-58,y:50}],
    Einstein:[{name:'MLS Stats',x:0,y:0},{name:'Analytics',x:82,y:-44},{name:'Whiteboard',x:-82,y:-36},{name:'Charts',x:-76,y:48},{name:'Trend Wall',x:68,y:50}],
    Rockefeller:[{name:'Desk',x:0,y:0},{name:'Conference',x:-70,y:-22},{name:'ROI Board',x:78,y:-26},{name:'Priority',x:86,y:46},{name:'Revenue',x:-82,y:46}]
  };
  const icons={Scout:'🕵️',Jessica:'📞',Scorsese:'🎬',Shakespeare:'✒️',Einstein:'📊',Rockefeller:'💰'};
  const routes={
    Scout:['Phone Search','Cabinet A','MLS Computer','Cabinet B','Desk'],
    Jessica:['Phone','Door','Email','Drip Board','Desk'],
    Scorsese:['Storyboard','Edit Bay','Audio','Render','Edit Bay'],
    Shakespeare:['Writing','Blog','SEO','Social Wall','Scheduler'],
    Einstein:['MLS Stats','Analytics','Whiteboard','Charts','Trend Wall'],
    Rockefeller:['ROI Board','Conference','Priority','Desk']
  };
  const agents=[];
  let last=performance.now();

  function deptFromRoom(room,i){
    const h=room.querySelector('h3')?.innerText || '';
    return Object.keys(stationDefs).find(d=>h.includes(d)) || Object.keys(stationDefs)[i] || 'Scout';
  }
  function choose(a){return a[Math.floor(Math.random()*a.length)];}
  function ensureHeaderIcon(room,dept){
    const h=room.querySelector('h3');
    if(!h) return;
    if(!h.querySelector('.deptIcon')){
      h.innerHTML='<span class="deptName">'+dept+'</span><span class="deptIcon">'+(icons[dept]||'⚡')+'</span>';
    }else{
      h.querySelector('.deptIcon').textContent=icons[dept]||'⚡';
    }
  }
  function addStations(room,dept){
    room.querySelectorAll('.v40Station,.v401Station').forEach(x=>x.remove());
    (stationDefs[dept]||[]).forEach(s=>{
      const dot=document.createElement('span');
      dot.className='v401Station';
      dot.title=s.name;
      dot.style.left=`calc(50% + ${s.x}px)`;
      dot.style.top=`calc(58% + ${s.y}px)`;
      room.appendChild(dot);
    });
  }
  function metricActive(room){
    return (parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0)>0;
  }
  function setTarget(a,s){
    a.target={x:s.x,y:s.y,name:s.name};
    a.state='walking';
    a.el.classList.add('walking');
    a.el.classList.remove('working');
    const dots=a.room.querySelectorAll('.v401Station');
    dots.forEach(d=>d.classList.remove('active'));
    const idx=(stationDefs[a.dept]||[]).findIndex(x=>x.name===s.name);
    if(dots[idx]) dots[idx].classList.add('active');
  }
  function pickNext(a){
    const st=stationDefs[a.dept]||[];
    if(!st.length) return;
    let target;
    if(metricActive(a.room)){
      const route=routes[a.dept]||st.map(x=>x.name);
      a.routeIndex=(a.routeIndex+1)%route.length;
      target=st.find(x=>x.name===route[a.routeIndex])||choose(st);
    }else{
      target=choose(st);
      let safety=0;
      while(target.name===a.currentName && safety<8){target=choose(st);safety++;}
    }
    setTarget(a,target);
  }
  function render(a){
    /* setProperty(...,'important') is critical because older CSS used !important transforms */
    a.el.style.setProperty('transform',`translate(-50%,-50%) translate(${a.x.toFixed(1)}px,${a.y.toFixed(1)}px) rotate(${a.angle.toFixed(1)}deg)`,'important');
  }
  function update(a,dt,now){
    if(a.state==='paused'){
      if(now>=a.pauseUntil) pickNext(a);
      return;
    }
    if(!a.target){pickNext(a);return;}
    const dx=a.target.x-a.x, dy=a.target.y-a.y, dist=Math.hypot(dx,dy);
    if(dist<1.2){
      a.x=a.target.x; a.y=a.target.y; a.currentName=a.target.name;
      a.state='paused';
      a.el.classList.remove('walking');
      a.el.classList.add('working');
      a.pauseUntil=now+(metricActive(a.room)?900+Math.random()*1300:1500+Math.random()*3200);
      render(a); return;
    }
    const desired=Math.atan2(dy,dx)*180/Math.PI+90;
    const delta=((desired-a.angle+540)%360)-180;
    a.angle+=delta*Math.min(1,dt*7);
    const step=Math.min(dist,a.speed*dt);
    a.x+=(dx/dist)*step;
    a.y+=(dy/dist)*step;
    render(a);
  }
  function loop(now){
    const dt=Math.min(.05,(now-last)/1000);
    last=now;
    agents.forEach(a=>update(a,dt,now));
    requestAnimationFrame(loop);
  }
  function toast(title,msg){
    const t=document.createElement('div');
    t.className='v401Toast';
    t.innerHTML='<strong>'+title+'</strong>'+msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),3900);
  }
  function boot(){
    document.querySelectorAll('.hq .room').forEach((room,i)=>{
      const dept=deptFromRoom(room,i);
      room.dataset.dept=dept;
      ensureHeaderIcon(room,dept);
      addStations(room,dept);
      let el=room.querySelector('.worker');
      if(!el) return;
      el.className='worker aiHuman';
      el.innerHTML='<i></i><b></b>';
      const a={dept,room,el,x:0,y:0,angle:0,speed:34+Math.random()*20,state:'paused',target:null,currentName:'Desk',routeIndex:Math.floor(Math.random()*3),pauseUntil:performance.now()+450+i*420+Math.random()*1400};
      agents.push(a);
      render(a);
    });
    requestAnimationFrame(loop);
    setTimeout(()=>toast('Goliath HQ V40.1','Movement fix installed. JS now overrides old transform locks and header icons are restored.'),900);
  }
  document.addEventListener('DOMContentLoaded',boot);
})();
