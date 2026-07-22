(function(){
  window.__GOLIATH_MOVEMENT_ENGINE_VERSION='46.5-square-bounded';
  const defs={
    Scout:[['Desk',0,0],['MLS Computer',48,-32],['Phone Search',-52,-28],['Cabinet A',-50,30],['Cabinet B',48,32],['Whiteboard',0,-44]],
    Jessica:[['Desk',0,0],['Email',50,-24],['Calendar',42,32],['Follow Up',-48,30],['Inbox',-38,-30]],
    Scorsese:[['Edit Bay',0,0],['Camera',-50,-32],['Audio',52,-30],['Render',50,32],['Storyboard',-46,32]],
    Shakespeare:[['Writing',0,0],['Blog',-50,2],['Social Wall',0,-42],['Scheduler',52,28],['SEO',-34,34]],
    Einstein:[['Analytics',0,0],['Charts',50,-32],['Whiteboard',-50,-28],['Signals',-44,32],['Trends',42,34]],
    Rockefeller:[['Desk',0,0],['ROI Board',50,-26],['Conference',-46,-20],['Priority',48,34],['Revenue',-48,34]],
    Prospector:[['Mine Shaft',0,0],['Owner Data',-50,-30],['Town Map',48,-32],['Opportunity Bin',48,34],['Referral Vault',-44,34]],
    Columbo:[['Case Desk',0,0],['Lead File',-50,-30],['Text Console',50,-30],['Appointment Board',44,34],['Evidence Wall',-46,34]]
  };
  const icons={Scout:'🕵️',Jessica:'✉️',Scorsese:'🎬',Shakespeare:'✒️',Einstein:'📊',Rockefeller:'💰',Prospector:'⛏️',Columbo:'🕵️‍♂️'};
  const routes={Scout:['Phone Search','Cabinet A','MLS Computer','Cabinet B','Desk'],Jessica:['Email','Calendar','Follow Up','Inbox','Desk'],Scorsese:['Storyboard','Edit Bay','Audio','Render','Edit Bay'],Shakespeare:['Writing','Blog','SEO','Social Wall','Scheduler'],Einstein:['Analytics','Charts','Whiteboard','Signals','Trends'],Rockefeller:['ROI Board','Conference','Priority','Desk'],Prospector:['Mine Shaft','Owner Data','Town Map','Opportunity Bin','Referral Vault'],Columbo:['Case Desk','Lead File','Text Console','Appointment Board','Evidence Wall']};
  const agents=[];let last=performance.now();
  function list(d){return (defs[d]||defs.Scout).map(a=>({name:a[0],x:a[1],y:a[2]}));}
  function dept(room,i){const data=room.dataset.dept||room.dataset.workqueue||'';if(defs[data])return data;let t=room.closest('[data-agent-tile]')?.getAttribute('data-agent-tile')||'';if(defs[t])return t;return Object.keys(defs)[i]||'Scout';}
  function active(room){return (parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0)>0}
  function dots(room,d){room.querySelectorAll('.v39Station,.v40Station,.v401Station,.v402Station,.v45Station').forEach(x=>x.remove());list(d).forEach(s=>{let dot=document.createElement('span');dot.className='v402Station v45Station';dot.title=s.name;dot.style.left=`calc(50% + ${s.x}px)`;dot.style.top=`calc(50% + ${s.y}px)`;room.appendChild(dot);});}
  function target(a,s){a.target=s;a.state='walking';a.el.classList.add('walking');a.el.classList.remove('working');a.room.querySelectorAll('.v45Station,.v402Station').forEach(d=>d.classList.remove('active'));let idx=list(a.dept).findIndex(x=>x.name===s.name);let ds=a.room.querySelectorAll('.v45Station,.v402Station');if(ds[idx])ds[idx].classList.add('active');}
  function pick(a){let st=list(a.dept);let r=routes[a.dept]||st.map(x=>x.name);a.ri=(a.ri+1)%r.length;target(a,st.find(x=>x.name===r[a.ri])||st[0]);}
  function clamp(v,min,max){return Math.max(min,Math.min(max,v));}
  function render(a){a.x=clamp(a.x,-54,54);a.y=clamp(a.y,-46,38);a.el.style.setProperty('transform',`translate(-50%,-50%) translate(${a.x.toFixed(1)}px,${a.y.toFixed(1)}px) rotate(${a.angle.toFixed(1)}deg)`,'important');}
  function step(a,dt,now){if(a.state==='paused'){if(now>=a.pauseUntil)pick(a);render(a);return;}if(!a.target){pick(a);render(a);return;}let dx=a.target.x-a.x,dy=a.target.y-a.y,dist=Math.hypot(dx,dy);if(dist<1.2){a.x=a.target.x;a.y=a.target.y;a.state='paused';a.el.classList.remove('walking');a.el.classList.add('working');a.pauseUntil=now+(active(a.room)?900+Math.random()*1400:1600+Math.random()*3000);render(a);return;}let desired=Math.atan2(dy,dx)*180/Math.PI+90;let delta=((desired-a.angle+540)%360)-180;a.angle+=delta*Math.min(1,dt*7);let move=Math.min(dist,a.speed*(active(a.room)?1.16:1)*dt);a.x+=(dx/dist)*move;a.y+=(dy/dist)*move;render(a);}
  function loop(now){let dt=Math.min(.05,(now-last)/1000);last=now;agents.forEach(a=>step(a,dt,now));requestAnimationFrame(loop);}
  window.openMissionDepartment=function(d){location.href='/dashboard/goliath-agent-detail.php?department='+encodeURIComponent(d||'Rockefeller');};
  document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.hq.gFourByFour .room').forEach((room,i)=>{let d=dept(room,i);room.dataset.dept=d;room.dataset.workqueue=d;let tile=room.closest('[data-agent-tile]'); if(tile) tile.setAttribute('data-agent-tile',d);dots(room,d);let el=room.querySelector('.worker')||document.createElement('span');if(!el.parentNode)room.appendChild(el);el.className='worker aiHuman';el.innerHTML='<i></i><b></b>';let a={dept:d,room,el,x:0,y:0,angle:0,speed:36+Math.random()*18,state:'paused',target:null,ri:Math.floor(Math.random()*3),pauseUntil:performance.now()+350+i*220+Math.random()*900};agents.push(a);render(a);});requestAnimationFrame(loop);});
})();
