
/* V36.3 live-ish activity state for Mission Control people */
(function(){
  function markActiveRooms(){
    document.querySelectorAll('.hq .room').forEach(room=>{
      const metric=parseInt((room.querySelector('.metric')?.textContent||'0').replace(/\D/g,''),10)||0;
      room.classList.toggle('active',metric>0);
      room.classList.toggle('idle',metric<=0);
    });
  }
  document.addEventListener('DOMContentLoaded',markActiveRooms);
})();
