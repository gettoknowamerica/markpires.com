(function(){
  window.__GOLIATH_MOVEMENT_ENGINE_VERSION='45.2-8-agents';
  const defs={
    Scout:[['Desk',0,0],['MLS Computer',78,-42],['Phone Search',-84,-36],['Cabinet A',-84,44],['Cabinet B',74,48],['Whiteboard',0,-60]],
    Jessica:[['Desk',0,0],['Phone',82,-24],['Door',96,50],['Email',-72,44],['Drip Board',-50,-44]],
    Scorsese:[['Edit Bay',0,0],['Camera',-86,-40],['Audio',90,-44],['Render',88,44],['Storyboard',-80,46]],
    Shakespeare:[['Writing',0,0],['Blog',-90,8],['Social Wall',0,-56],['Scheduler',92,34],['SEO',-58,50]],
    Einstein:[['MLS Stats',0,0],['Analytics',82,-44],['Whiteboard',-82,-36],['Charts',-76,48],['Trend Wall',68,50]],
    Rockefeller:[['Desk',0,0],['Conference',-70,-22],['ROI Board',78,-26],['Priority',86,46],['Revenue',-82,46]],
    Prospector:[['Mine Shaft',0,0],['Owner Data',-86,-38],['Town Map',78,-40],['Opportunity Bin',84,48],['Referral Vault',-76,48]],
    Columbo:[['Case Desk',0,0],['Lead File',-86,-38],['Text Console',86,-34],['Appointment Board',76,48],['Evidence Wall',-78,48]]
  };
  const icons={Scout:'🕵️',Jessica:'✉️',Scorsese:'🎬',Shakespeare:'✒️',Einstein:'📊',Rockefeller:'💰',Prospector:'⛏️',Columbo:'🕵️‍♂️'};
  const routes={Scout:['Phone Search','Cabinet A','MLS Computer','Cabinet B','Desk'],Jessica:['Phone','Door','Email','Drip Board','Desk'],Scorsese:['Storyboard','Edit Bay','Audio','Render','Edit Bay'],Shakespeare:['Writing','Blog','SEO','Social Wall','Scheduler'],Einstein:['MLS Stats','Analytics','Whiteboard','Charts','Trend Wall'],Rockefeller:['ROI Board','Conference','Priority','Desk'],Prospector:['Mine Shaft','Owner Data','Town Map','Opportunity Bin','Referral Vault'],Columbo:['Case Desk','Lead File','Text Console','Appointment Board','Evidence Wall']};
  const agents=[];let last=performance.now();
  function list(d){return (defs[d]||defs.Scout).map(a=>({name:a[0],x:a[1],y:a[2]}));}
  function dept(room,i){const data=room.dataset.dept||room.dataset.workqueue||'';if(defs[data])return data;let h=room.querySelector('.deptName')?.textContent||room.querySelector('h3')?.innerText||'';return Object.keys(defs).find(d=>h.includes(d))||Object.keys(defs)[i]||'Scout';}
  function header(room,d){let h=room.querySelector('h3');if(h)h.innerHTML='<span class="deptName">'+d+'</span><span class="deptIcon">'+(icons[d]||'⚡')+'</span>';}
  function choose(a){return a[Math.floor(Math.random()*a.length)]}
  function active(room){return (parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0)>0}
  function dots(room,d){room.querySelectorAll('.v39Station,.v40Station,.v401Station,.v402Station,.v45Station').forEach(x=>x.remove());list(d).forEach(s=>{let dot=document.createElement('span');dot.className='v402Station v45Station';dot.title=s.name;dot.style.left=`calc(50% + ${s.x}px)`;dot.style.top=`calc(58% + ${s.y}px)`;room.appendChild(dot);});}
  function target(a,s){a.target=s;a.state='walking';a.el.classList.add('walking');a.el.classList.remove('working');let ds=a.room.querySelectorAll('.v45Station,.v402Station');ds.forEach(d=>d.classList.remove('active'));let idx=list(a.dept).findIndex(x=>x.name===s.name);if(ds[idx])ds[idx].classList.add('active');}
  function pick(a){let st=list(a.dept);let r=routes[a.dept]||st.map(x=>x.name);a.ri=(a.ri+1)%r.length;target(a,st.find(x=>x.name===r[a.ri])||choose(st));}
  function render(a){a.el.style.setProperty('transform',`translate(-50%,-50%) translate(${a.x.toFixed(1)}px,${a.y.toFixed(1)}px) rotate(${a.angle.toFixed(1)}deg)`,'important');}
  function step(a,dt,now){if(a.state==='paused'){if(now>=a.pauseUntil)pick(a);render(a);return;}if(!a.target){pick(a);render(a);return;}let dx=a.target.x-a.x,dy=a.target.y-a.y,dist=Math.hypot(dx,dy);if(dist<1.2){a.x=a.target.x;a.y=a.target.y;a.state='paused';a.el.classList.remove('walking');a.el.classList.add('working');a.pauseUntil=now+(active(a.room)?900+Math.random()*1400:1500+Math.random()*3400);render(a);return;}let desired=Math.atan2(dy,dx)*180/Math.PI+90;let delta=((desired-a.angle+540)%360)-180;a.angle+=delta*Math.min(1,dt*7);let move=Math.min(dist,a.speed*(active(a.room)?1.18:1)*dt);a.x+=(dx/dist)*move;a.y+=(dy/dist)*move;render(a);}
  function loop(now){let dt=Math.min(.05,(now-last)/1000);last=now;agents.forEach(a=>step(a,dt,now));requestAnimationFrame(loop);}
  window.openMissionDepartment=function(d){location.href='/dashboard/goliath-agent-detail.php?department='+encodeURIComponent(d||'Rockefeller');};
  document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.hq .room').forEach((room,i)=>{let d=dept(room,i);room.dataset.dept=d;room.dataset.workqueue=d;header(room,d);dots(room,d);let el=room.querySelector('.worker')||document.createElement('span');if(!el.parentNode)room.appendChild(el);el.className='worker aiHuman';el.innerHTML='<i></i><b></b>';let a={dept:d,room,el,x:0,y:0,angle:0,speed:38+Math.random()*22,state:'paused',target:null,ri:Math.floor(Math.random()*3),pauseUntil:performance.now()+400+i*260+Math.random()*1200};agents.push(a);render(a);});requestAnimationFrame(loop);});
})();
