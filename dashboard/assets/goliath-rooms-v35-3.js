
/* V35.3 Room labels + team name compatibility */
(function(){
  const renameMap={
    "Director":"Scorsese",
    "Publisher":"Shakespeare",
    "Analyst":"Einstein",
    "Executive":"Rockefeller"
  };
  function normalizeDeptName(){
    document.querySelectorAll('.hq .room h3').forEach(h=>{
      Object.keys(renameMap).forEach(old=>{
        if(h.textContent.includes(old)){
          h.innerHTML=h.innerHTML.replace(old,renameMap[old]);
        }
      });
    });
    document.querySelectorAll('.kpi strong').forEach(s=>{
      Object.keys(renameMap).forEach(old=>{
        if(s.textContent.trim()===old) s.textContent=renameMap[old];
      });
    });
  }
  function improveRoomStatus(){
    document.querySelectorAll('.hq .room').forEach((room,i)=>{
      const status=room.querySelector('.roomStatus');
      if(!status) return;
      const names=['Searching records','Outreach active','Producing media','Publishing content','Analyzing data','Prioritizing ROI'];
      status.textContent=room.classList.contains('active')?names[i]:'Standing by';
    });
  }
  document.addEventListener('DOMContentLoaded',()=>{
    normalizeDeptName();
    setTimeout(improveRoomStatus,300);
  });
})();
