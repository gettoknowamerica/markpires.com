/* V40.4 Global Media Router: video/image creation clicks go to Completed Media */
(function(){
  const mediaWords = [
    'scorsese','video','media','render','creation','completed media','review cut',
    'image','graphic','thumbnail','reel','short','comfy','wan','flux'
  ];
  function isMediaClick(el){
    const txt=(el.innerText||el.textContent||'').toLowerCase();
    const href=(el.getAttribute && (el.getAttribute('href')||'')) || '';
    if(href.includes('/dashboard/goliath-completed-media.php')) return false;
    if(href.includes('/dashboard/assets/generated/')) return true;
    return mediaWords.some(w=>txt.includes(w));
  }
  document.addEventListener('click',function(e){
    const el=e.target.closest('a,button,.kpi,.g-kpi,.event,.v381QItem,.compactOpportunity');
    if(!el) return;
    if(isMediaClick(el)){
      const txt=(el.innerText||'').toLowerCase();
      if(txt.includes('blog') && !txt.includes('video')) return;
      e.preventDefault();
      e.stopPropagation();
      window.location.href='/dashboard/goliath-completed-media.php';
    }
  },true);
})();