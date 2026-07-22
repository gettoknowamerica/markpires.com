/* V37.1 Direct open: deliverables route to the actual workspace */
(function(){
  document.addEventListener('click',function(e){
    const item=e.target.closest('.event');
    if(!item) return;
    const text=(item.innerText||'').toLowerCase();
    const dept=(item.getAttribute('data-dept')||'').toLowerCase();
    if(dept.includes('scorsese') || text.includes('video') || text.includes('render') || text.includes('creation') || text.includes('media')){
      e.preventDefault(); e.stopPropagation();
      location.href='/dashboard/goliath-studio.php?filter=completed&dept=Scorsese';
      return;
    }
  },true);
})();