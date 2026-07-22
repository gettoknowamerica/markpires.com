(() => {
 document.addEventListener('DOMContentLoaded',()=>{
  const audio=document.getElementById('v111AudioPlayer');
  if(audio){
   audio.setAttribute('playsinline','');
   audio.setAttribute('webkit-playsinline','');
   audio.preload='auto';
  }
  // On any user gesture, unlock the persistent audio element for iPhone.
  const unlock=async()=>{
   if(!audio||audio.dataset.unlocked==='1')return;
   try{
    audio.muted=true;
    audio.src='data:audio/mp3;base64,//uQZAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAEAAACcQCA';
    await audio.play();audio.pause();audio.currentTime=0;audio.muted=false;audio.dataset.unlocked='1';
   }catch(_){}
  };
  document.addEventListener('touchstart',unlock,{once:true,passive:true});
  document.addEventListener('pointerdown',unlock,{once:true});
 });
})();