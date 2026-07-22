/* V75.6.2 — Randomized top-down agent movement based on actual task state */
(function(){
  window.__GOLIATH_MOVEMENT_ENGINE_VERSION='75.6.2-random-task-aware';

  const defs={
    Goliath:[['Council',-35,-22],['Review',35,-22],['Dispatch',36,26],['Command',-36,26]],
    Jessica:[['Inbox',-34,-20],['Draft Email',34,-22],['Calendar',36,28],['Follow-Up',-36,28]],
    Scout:[['Desk',-28,-18],['Contact Search',38,-22],['Phone Match',38,30],['File Save',-38,30]],
    Scorsese:[['Timeline',-36,-22],['Preview',36,-22],['Render',34,28],['Clip Select',-34,28]],
    Mozart:[['Piano',-34,-22],['Waveform',34,-20],['Hook',34,28],['Conductor',-36,28]],
    Shakespeare:[['Draft',-34,-22],['Blog',34,-20],['Caption',36,28],['SEO',-36,28]],
    Einstein:[['AEO',-34,-22],['Signals',34,-20],['Data',34,28],['Questions',-34,28]],
    Columbo:[['Archive',-34,-22],['Gold Clip',34,-20],['Evidence',34,28],['Send Scorsese',-34,28]],
    Prospector:[['Map',-34,-22],['Owner Data',34,-20],['Campaign',34,28],['Opportunity',-34,28]],
    Rockefeller:[['ROI Board',-34,-22],['Priority',34,-20],['Revenue',34,28],['Hot Sheet',-34,28]],
    Pandora:[['Partners',-34,-22],['Booking',34,-20],['Brands',34,28],['Expansion',-34,28]]
  };

  const labels={
    queued:'walking to task',
    working:'working the room',
    review:'presenting work',
    complete:'filing completed work',
    blocked:'waiting on tool',
    idle:'thinking'
  };

  const agents=[];
  let last=performance.now();

  function summaryFor(dept){
    return (window.GOLIATH_AGENT_SUMMARIES && window.GOLIATH_AGENT_SUMMARIES[dept]) || {};
  }
  function bucketFor(dept){
    const s=summaryFor(dept);
    if((+s.blocked||0)>0) return 'blocked';
    if((+s.working||0)>0) return 'working';
    if((+s.review||0)>0) return 'review';
    if((+s.queued||0)>0) return 'queued';
    if((+s.today_complete||0)>0 || (+s.complete||0)>0) return 'complete';
    return 'idle';
  }
  function points(dept){return defs[dept]||defs.Scout;}
  function rand(min,max){return min+Math.random()*(max-min);}
  function shuffleIndex(len){return Math.max(0,Math.floor(Math.random()*len));}

  function getDept(room,i){
    const d=room.getAttribute('data-dept') || room.closest('.agentTile')?.getAttribute('data-agent-tile');
    if(defs[d]) return d;
    const keys=Object.keys(defs);
    return keys[i%keys.length] || 'Scout';
  }

  function activeCount(room){
    return parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0;
  }

  function makeDots(room,dept){
    room.querySelectorAll('.v45Station,.v402Station').forEach(x=>x.remove());
    points(dept).forEach(p=>{
      const dot=document.createElement('span');
      dot.className='v45Station v402Station';
      dot.title=p[0];
      dot.style.left=`calc(50% + ${p[1]}px)`;
      dot.style.top=`calc(50% + ${p[2]}px)`;
      room.appendChild(dot);
    });
  }

  function ensureBubble(room){
    let bubble=room.querySelector('.agentWorkBubble');
    if(!bubble){
      bubble=document.createElement('span');
      bubble.className='agentWorkBubble';
      room.insertBefore(bubble, room.firstChild);
    }
    return bubble;
  }

  function render(a){
    const x=Math.max(-45,Math.min(45,a.x));
    const y=Math.max(-35,Math.min(35,a.y));
    a.el.style.setProperty('transform',`translate(-50%,-50%) translate(${x.toFixed(1)}px,${y.toFixed(1)}px) rotate(${a.angle.toFixed(1)}deg)`,'important');
  }

  function chooseTarget(a){
    const pts=points(a.dept);
    const bucket=bucketFor(a.dept);

    let targetName=null;
    if(bucket==='queued') targetName=/Desk|Inbox|Draft|AEO|Timeline|Archive|Map|Partners|Council/i;
    if(bucket==='working') targetName=/Search|Render|Waveform|Blog|Signals|Gold|Campaign|Revenue|Booking|Dispatch/i;
    if(bucket==='review') targetName=/Preview|Review|Evidence|Priority|Command|Follow-Up/i;
    if(bucket==='complete') targetName=/File|Save|Hot Sheet|Conductor|Command/i;
    if(bucket==='blocked') targetName=/Questions|Evidence|Preview|Review/i;

    let candidates=targetName?pts.filter(p=>targetName.test(p[0])):[];
    if(!candidates.length) candidates=pts;

    const pick=candidates[shuffleIndex(candidates.length)];
    a.target={x:pick[1]+rand(-5,5),y:pick[2]+rand(-5,5),name:pick[0]};
    a.state='walking';
    a.speed=rand(bucket==='working'?42:26,bucket==='working'?64:42);
    a.el.classList.add('walking');
    a.el.classList.remove('working');
    a.room.closest('.agentTile')?.classList.toggle('is-working',bucket!=='idle');

    const bubble=ensureBubble(a.room);
    const task=(summaryFor(a.dept).title||'').replace(/^No active task$/,'');
    bubble.textContent=task ? `${labels[bucket]} · ${task}` : labels[bucket];
  }

  function step(a,dt,now){
    if(a.state==='paused'){
      if(now>a.pauseUntil) chooseTarget(a);
      render(a);
      return;
    }
    if(!a.target){ chooseTarget(a); return; }

    const dx=a.target.x-a.x, dy=a.target.y-a.y, dist=Math.hypot(dx,dy);
    if(dist<1.1){
      a.x=a.target.x; a.y=a.target.y;
      a.state='paused';
      a.el.classList.remove('walking');
      a.el.classList.add('working');
      const bucket=bucketFor(a.dept);
      a.pauseUntil=now+rand(bucket==='idle'?1600:650,bucket==='idle'?4200:2100);
      render(a);
      return;
    }

    const desired=Math.atan2(dy,dx)*180/Math.PI+90;
    let delta=((desired-a.angle+540)%360)-180;
    a.angle+=delta*Math.min(1,dt*7.5);
    const move=Math.min(dist,a.speed*dt);
    a.x+=(dx/dist)*move; a.y+=(dy/dist)*move;
    render(a);
  }

  function loop(now){
    const dt=Math.min(.05,(now-last)/1000); last=now;
    agents.forEach(a=>step(a,dt,now));
    requestAnimationFrame(loop);
  }

  function boot(){
    const rooms=document.querySelectorAll('.agentTile .room');
    rooms.forEach((room,i)=>{
      if(room.closest('.scoreboard')) return;
      const dept=getDept(room,i);
      room.dataset.dept=dept;
      room.dataset.workqueue=dept;
      const tile=room.closest('.agentTile');
      if(tile) tile.setAttribute('data-agent-tile',dept);
      makeDots(room,dept);
      ensureBubble(room);

      let el=room.querySelector('.worker.aiHuman');
      if(!el){
        el=document.createElement('span');
        el.className='worker aiHuman';
        room.appendChild(el);
      }
      el.innerHTML='<i></i><b></b>';
      const pts=points(dept);
      const start=pts[shuffleIndex(pts.length)];
      const a={
        dept,room,el,
        x:start[1]+rand(-6,6),y:start[2]+rand(-6,6),
        angle:rand(-180,180),
        state:'paused',target:null,
        pauseUntil:performance.now()+rand(200,1600),
        speed:rand(28,48)
      };
      agents.push(a);
      render(a);
    });
    requestAnimationFrame(loop);
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot);
  else boot();
})();
