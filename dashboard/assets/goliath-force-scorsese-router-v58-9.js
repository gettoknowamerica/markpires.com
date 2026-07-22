(function(){
  const target='/dashboard/scorsese-media-center.php';
  function fix(){
    document.querySelectorAll('a[href*="department=Scorsese"],a[href*="goliath-agent-detail.php?department=Scorsese"],a[data-dept="Scorsese"],.agent-room-scorsese').forEach(a=>{
      a.setAttribute('href',target);
      a.onclick=null;
      a.addEventListener('click',function(e){ e.preventDefault(); window.location.href=target; },{capture:true});
    });
    document.querySelectorAll('a.kpi[href*="Scorsese"],a[href*="Scorsese"]').forEach(a=>{
      if((a.textContent||'').toLowerCase().includes('scorsese') || (a.href||'').includes('department=Scorsese')){
        a.setAttribute('href',target);
      }
    });
    document.querySelectorAll('a[href*="/dashboard/goliath-studio.php"]').forEach(a=>{
      a.textContent=(a.textContent||'').replace(/Goliath Studio Pro|Goliath Studio/gi,'Scorsese Media Center');
      a.setAttribute('href',target);
    });
  }
  document.addEventListener('DOMContentLoaded',fix);
  setTimeout(fix,500);setTimeout(fix,1500);
})();
