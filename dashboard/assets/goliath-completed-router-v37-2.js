/* V37.2 Direct completed-work router */
(function(){
  function goScorsese(){
    location.href='/dashboard/goliath-completed-media.php';
  }
  document.addEventListener('click',function(e){
    const card=e.target.closest('.kpi,.g-kpi,a,button,.event');
    if(!card) return;
    const text=(card.innerText||'').toLowerCase();
    const dept=(card.getAttribute('data-dept')||'').toLowerCase();
    const isScorsese=dept.includes('scorsese') || text.includes('scorsese') || text.includes('media complete') || text.includes('video complete') || text.includes('creation returned') || text.includes('media ready');
    if(isScorsese && (text.includes('complete') || text.includes('media') || text.includes('video') || text.includes('creation'))){
      e.preventDefault();
      e.stopPropagation();
      goScorsese();
    }
  },true);
})();