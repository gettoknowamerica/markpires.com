
(function(){
  const stationMap={
    Scout:[["Desk",0,0],["MLS Computer",72,-42],["Phone Search",-76,-34],["File Cabinet A",-82,42],["File Cabinet B",68,46],["Whiteboard",0,-58]],
    Jessica:[["Desk",0,0],["Phone",80,-22],["Door",92,48],["Email Station",-66,42],["Drip Desk",-46,-42]],
    Scorsese:[["Editing Bay",0,0],["Camera",-82,-38],["Audio Booth",86,-42],["Render Monitor",84,42],["Storyboard",-76,44]],
    Shakespeare:[["Writing Desk",0,0],["Blog Station",-86,8],["Social Wall",0,-54],["Schedule Desk",88,32],["SEO Board",-54,48]],
    Einstein:[["Analytics Wall",78,-42],["Whiteboard",-78,-34],["MLS Stats",0,0],["Charts",-72,46],["Trend Monitor",64,48]],
    Rockefeller:[["Executive Desk",0,0],["Conference Table",-66,-20],["ROI Board",74,-24],["Priority Wall",82,44],["Revenue Screen",-78,44]]
  };
  const routes={
    Scout:["Phone Search","File Cabinet A","MLS Computer","File Cabinet B","Desk"],
    Jessica:["Phone","Door","Email Station","Drip Desk","Desk"],
    Scorsese:["Storyboard","Editing Bay","Audio Booth","Render Monitor","Editing Bay"],
    Shakespeare:["Writing Desk","Blog Station","SEO Board","Social Wall","Schedule Desk"],
    Einstein:["MLS Stats","Analytics Wall","Whiteboard","Charts","Trend Monitor"],
    Rockefeller:["ROI Board","Conference Table","Priority Wall","Executive Desk"]
  };
  function stations(dept){return (stationMap[dept]||[]).map(a=>({name:a[0],x:a[1],y:a[2]}));}
  function dept(room,i){const h=room.querySelector('h3')?.textContent||'';return Object.keys(stationMap).find(n=>h.includes(n))||Object.keys(stationMap)[i]||"Scout";}
  function addDots(room,dept){if(room.querySelector('.v37Station'))return;stations(dept).forEach(s=>{let d=document.createElement('span');d.className='v37Station';d.title=s.name;d.style.left=`calc(50% + ${s.x}px)`;d.style.top=`calc(55% + ${s.y}px)`;room.appendChild(d);});}
  function choose(a){return a[Math.floor(Math.random()*a.length)]}
  function move(a,s){
    let dx=s.x-a.x,dy=s.y-a.y,ang=Math.atan2(dy,dx)*180/Math.PI+90;
    a.el.classList.add('walking');a.el.classList.remove('working');
    a.el.style.transform=`translate(-50%,-50%) translate(${s.x}px,${s.y}px) rotate(${ang}deg)`;
    a.x=s.x;a.y=s.y;a.current=s.name;
    a.room.querySelectorAll('.v37Station').forEach(d=>d.classList.remove('active'));
    let idx=stations(a.dept).findIndex(x=>x.name===s.name);
    let dot=a.room.querySelectorAll('.v37Station')[idx]; if(dot)dot.classList.add('active');
    clearTimeout(a.stopTimer);a.stopTimer=setTimeout(()=>{a.el.classList.remove('walking');a.el.classList.add('working')},1800);
  }
  function loop(a){
    let count=parseInt((a.room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0;
    let active=count>0||a.room.classList.contains('active');
    let st=stations(a.dept); if(!st.length)return;
    let target;
    if(active){
      let r=routes[a.dept]||st.map(x=>x.name); a.routeIndex=(a.routeIndex+1)%r.length;
      target=st.find(x=>x.name===r[a.routeIndex])||choose(st);
    }else{
      target=choose(st); let safe=0; while(target.name===a.current&&safe<5){target=choose(st);safe++;}
    }
    move(a,target);
    setTimeout(()=>loop(a), active ? 2600+Math.random()*1600 : 4200+Math.random()*4200);
  }
  function notify(msg){let n=document.createElement('div');n.className='v37Notify';n.innerHTML=msg;document.body.appendChild(n);setTimeout(()=>n.remove(),4000)}
  document.addEventListener('DOMContentLoaded',()=>{
    const agents=[];
    document.querySelectorAll('.hq .room').forEach((room,i)=>{
      let d=dept(room,i); addDots(room,d);
      let el=room.querySelector('.worker')||room.querySelector('.aiPerson'); if(!el)return;
      el.classList.add('livingAgent'); el.dataset.agent=d;
      let a={dept:d,room,el,x:0,y:0,current:'Desk',routeIndex:-1}; agents.push(a);
      setTimeout(()=>loop(a),700+i*380);
    });
    setTimeout(()=>notify('<strong>Goliath HQ</strong>Living navigation online. Agents now choose stations, turn, walk, pause, and work.'),1000);
  });
})();
