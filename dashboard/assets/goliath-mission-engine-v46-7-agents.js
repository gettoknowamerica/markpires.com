(function(){
  window.__GOLIATH_MOVEMENT_ENGINE_VERSION='46.7-hard-reset';
  const defs={
    Scout:[['Desk',-28,-18],['Contact Search',38,-22],['Phone Match',38,30],['File Save',-38,30]],
    Jessica:[['Inbox',-34,-20],['Draft Email',34,-22],['Calendar',36,28],['Follow-Up',-36,28]],
    Scorsese:[['Timeline',-36,-22],['Preview',36,-22],['Render',34,28],['Clip Select',-34,28]],
    Shakespeare:[['Draft',-34,-22],['Blog',34,-20],['Caption',36,28],['SEO',-36,28]],
    Einstein:[['AEO',-34,-22],['Signals',34,-20],['Data',34,28],['Questions',-34,28]],
    Rockefeller:[['ROI Board',-34,-22],['Priority',34,-20],['Revenue',34,28],['Hot Sheet',-34,28]],
    Prospector:[['Map',-34,-22],['Owner Data',34,-20],['Campaign',34,28],['Opportunity',-34,28]],
    Columbo:[['Archive',-34,-22],['Gold Clip',34,-20],['Evidence',34,28],['Send Scorsese',-34,28]]
  };
  const labels={Scout:'finding contacts',Jessica:'emailing leads',Scorsese:'editing media',Shakespeare:'writing content',Einstein:'reading signals',Rockefeller:'ranking ROI',Prospector:'mining deals',Columbo:'finding gold'};
  const agents=[]; let last=performance.now();
  function points(d){return defs[d]||defs.Scout;}
  function getDept(room,i){const d=room.getAttribute('data-dept')||room.closest('.agentTile')?.getAttribute('data-agent-tile'); return defs[d]?d:Object.keys(defs)[i]||'Scout';}
  function active(room){return (parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0)>0;}
  function makeDots(room,d){room.querySelectorAll('.v45Station,.v402Station').forEach(x=>x.remove());points(d).forEach(p=>{const dot=document.createElement('span');dot.className='v45Station v402Station';dot.title=p[0];dot.style.left=`calc(50% + ${p[1]}px)`;dot.style.top=`calc(50% + ${p[2]}px)`;room.appendChild(dot);});}
  function render(a){const x=Math.max(-44,Math.min(44,a.x)),y=Math.max(-34,Math.min(34,a.y));a.el.style.setProperty('transform',`translate(-50%,-50%) translate(${x.toFixed(1)}px,${y.toFixed(1)}px) rotate(${a.angle.toFixed(1)}deg)`,'important');}
  function pick(a){a.ri=(a.ri+1)%points(a.dept).length;const p=points(a.dept)[a.ri];a.target={x:p[1],y:p[2],name:p[0]};a.state='walking';a.el.classList.add('walking');a.el.classList.remove('working');const bubble=a.room.querySelector('.agentWorkBubble');if(bubble)bubble.textContent=(active(a.room)?labels[a.dept]:'standing by');a.room.closest('.agentTile')?.classList.toggle('is-working',active(a.room));}
  function step(a,dt,now){if(a.state==='paused'){if(now>a.pauseUntil)pick(a);render(a);return;}if(!a.target){pick(a);return;}const dx=a.target.x-a.x,dy=a.target.y-a.y,dist=Math.hypot(dx,dy);if(dist<1.1){a.x=a.target.x;a.y=a.target.y;a.state='paused';a.el.classList.remove('walking');a.el.classList.add('working');a.pauseUntil=now+(active(a.room)?1000+Math.random()*1200:1800+Math.random()*2400);render(a);return;}const desired=Math.atan2(dy,dx)*180/Math.PI+90;let delta=((desired-a.angle+540)%360)-180;a.angle+=delta*Math.min(1,dt*7);const move=Math.min(dist,(34+Math.random()*0.3)*(active(a.room)?1.1:0.85)*dt);a.x+=(dx/dist)*move;a.y+=(dy/dist)*move;render(a);}
  function loop(now){const dt=Math.min(.05,(now-last)/1000);last=now;agents.forEach(a=>step(a,dt,now));requestAnimationFrame(loop);}
  window.openMissionDepartment=function(d){location.href='/dashboard/goliath-agent-detail.php?department='+encodeURIComponent(d||'Rockefeller');};
  document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.hq.gFourByFour .room').forEach((room,i)=>{const d=getDept(room,i);room.dataset.dept=d;room.dataset.workqueue=d;makeDots(room,d);let bubble=room.querySelector('.agentWorkBubble');if(!bubble){bubble=document.createElement('span');bubble.className='agentWorkBubble';room.insertBefore(bubble,room.firstChild);}bubble.textContent=active(room)?labels[d]:'standing by';let el=room.querySelector('.worker')||document.createElement('span');if(!el.parentNode)room.appendChild(el);el.className='worker aiHuman';el.innerHTML='<i></i><b></b>';agents.push({dept:d,room,el,x:0,y:0,angle:0,state:'paused',target:null,ri:i%4,pauseUntil:performance.now()+300+i*220});render(agents[agents.length-1]);});requestAnimationFrame(loop);});
})();
