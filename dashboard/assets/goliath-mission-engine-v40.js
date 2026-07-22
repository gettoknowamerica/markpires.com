
/* V40 Mission Control Engine: tiny game loop, no moonwalking */
(function(){
  const stationDefs={
    Scout:[
      {name:'Desk',x:0,y:0},{name:'MLS Computer',x:78,y:-42},{name:'Phone Search',x:-84,y:-36},
      {name:'Cabinet A',x:-84,y:44},{name:'Cabinet B',x:74,y:48},{name:'Whiteboard',x:0,y:-60}
    ],
    Jessica:[
      {name:'Desk',x:0,y:0},{name:'Phone',x:82,y:-24},{name:'Door',x:96,y:50},
      {name:'Email',x:-72,y:44},{name:'Drip Board',x:-50,y:-44}
    ],
    Scorsese:[
      {name:'Edit Bay',x:0,y:0},{name:'Camera',x:-86,y:-40},{name:'Audio',x:90,y:-44},
      {name:'Render',x:88,y:44},{name:'Storyboard',x:-80,y:46}
    ],
    Shakespeare:[
      {name:'Writing',x:0,y:0},{name:'Blog',x:-90,y:8},{name:'Social Wall',x:0,y:-56},
      {name:'Scheduler',x:92,y:34},{name:'SEO',x:-58,y:50}
    ],
    Einstein:[
      {name:'MLS Stats',x:0,y:0},{name:'Analytics',x:82,y:-44},{name:'Whiteboard',x:-82,y:-36},
      {name:'Charts',x:-76,y:48},{name:'Trend Wall',x:68,y:50}
    ],
    Rockefeller:[
      {name:'Desk',x:0,y:0},{name:'Conference',x:-70,y:-22},{name:'ROI Board',x:78,y:-26},
      {name:'Priority',x:86,y:46},{name:'Revenue',x:-82,y:46}
    ]
  };

  const activeRoutes={
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

  function choose(arr){return arr[Math.floor(Math.random()*arr.length)];}

  function addStations(room,dept){
    if(room.querySelector('.v40Station')) return;
    (stationDefs[dept]||[]).forEach(s=>{
      const dot=document.createElement('span');
      dot.className='v40Station';
      dot.title=s.name;
      dot.style.left=`calc(50% + ${s.x}px)`;
      dot.style.top=`calc(58% + ${s.y}px)`;
      room.appendChild(dot);
    });
  }

  function metricActive(room){
    const n=parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0;
    return n>0;
  }

  function setTarget(agent,station){
    agent.target={x:station.x,y:station.y,name:station.name};
    agent.state='walking';
    agent.el.classList.add('walking');
    agent.el.classList.remove('working');
    agent.pauseUntil=0;

    agent.room.querySelectorAll('.v40Station').forEach(d=>d.classList.remove('active'));
    const idx=(stationDefs[agent.dept]||[]).findIndex(s=>s.name===station.name);
    const dot=agent.room.querySelectorAll('.v40Station')[idx];
    if(dot) dot.classList.add('active');
  }

  function pickNext(agent){
    const stations=stationDefs[agent.dept]||[];
    if(!stations.length) return;

    let target;
    if(metricActive(agent.room)){
      const route=activeRoutes[agent.dept] || stations.map(s=>s.name);
      agent.routeIndex=(agent.routeIndex+1)%route.length;
      target=stations.find(s=>s.name===route[agent.routeIndex]) || choose(stations);
    }else{
      target=choose(stations);
      let safety=0;
      while(target.name===agent.currentName && safety<8){
        target=choose(stations);
        safety++;
      }
    }
    setTarget(agent,target);
  }

  function render(agent){
    agent.el.style.transform=`translate(-50%,-50%) translate(${agent.x.toFixed(1)}px,${agent.y.toFixed(1)}px) rotate(${agent.angle.toFixed(1)}deg)`;
  }

  function updateAgent(agent,dt,now){
    if(agent.state==='paused'){
      if(now>=agent.pauseUntil){
        pickNext(agent);
      }
      return;
    }

    if(!agent.target){
      pickNext(agent);
      return;
    }

    const dx=agent.target.x-agent.x;
    const dy=agent.target.y-agent.y;
    const dist=Math.hypot(dx,dy);

    if(dist<1.2){
      agent.x=agent.target.x;
      agent.y=agent.target.y;
      agent.currentName=agent.target.name;
      agent.state='paused';
      agent.el.classList.remove('walking');
      agent.el.classList.add('working');
      agent.pauseUntil=now + (metricActive(agent.room) ? 900+Math.random()*1300 : 1500+Math.random()*3200);
      render(agent);
      return;
    }

    const desiredAngle=Math.atan2(dy,dx)*180/Math.PI+90;
    let delta=((desiredAngle-agent.angle+540)%360)-180;
    agent.angle += delta * Math.min(1, dt*7);

    const step=Math.min(dist, agent.speed*dt);
    agent.x += (dx/dist)*step;
    agent.y += (dy/dist)*step;
    render(agent);
  }

  function loop(now){
    const dt=Math.min(0.05,(now-last)/1000);
    last=now;
    agents.forEach(a=>updateAgent(a,dt,now));
    requestAnimationFrame(loop);
  }

  function toast(title,msg){
    const t=document.createElement('div');
    t.className='v40Toast';
    t.innerHTML=`<strong>${title}</strong>${msg}`;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),3900);
  }

  function boot(){
    document.querySelectorAll('.hq .room').forEach((room,i)=>{
      const dept=deptFromRoom(room,i);
      addStations(room,dept);

      let el=room.querySelector('.worker');
      if(!el) return;

      el.classList.add('aiHuman');
      el.innerHTML='<i></i><b></b>';

      const agent={
        dept,room,el,
        x:0,y:0,angle:0,
        speed:34 + Math.random()*18,
        state:'paused',
        target:null,
        currentName:'Desk',
        routeIndex:Math.floor(Math.random()*3),
        pauseUntil:performance.now()+400+i*350+Math.random()*1200
      };
      agents.push(agent);
      render(agent);
    });

    requestAnimationFrame(loop);
    setTimeout(()=>toast('Goliath HQ V40','Mission Control Engine is online. Agents now navigate with coordinates instead of moonwalking.'),900);
  }

  document.addEventListener('DOMContentLoaded',boot);
})();
