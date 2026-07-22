/* V36.1 Studio media playback patch */
(function(){
  function isMediaUrl(url){return /\.(mp4|mov|webm|m4v|png|jpg|jpeg|webp|gif)(\?|$)/i.test(String(url||''));}
  function mediaFromCommand(c){
    let found=[];
    function scan(v){
      if(!v) return;
      if(typeof v==="string" && (v.includes('/dashboard/assets/generated/') || isMediaUrl(v))) found.push(v);
      else if(Array.isArray(v)) v.forEach(scan);
      else if(typeof v==="object") Object.values(v).forEach(scan);
    }
    scan(c.result); scan(c.metadata); scan(c);
    return [...new Set(found)];
  }
  window.showCommand=function(c){
    const urls=mediaFromCommand(c);
    const viewer=document.getElementById('viewer');
    if(!viewer) return;
    if(urls.length){
      const url=urls[0];
      const preview=document.getElementById('preview');
      if(preview && /\.(mp4|mov|webm|m4v)(\?|$)/i.test(url)){
        preview.src=url;
        preview.load();
        preview.scrollIntoView({behavior:'smooth',block:'center'});
      }
      if(/\.(png|jpg|jpeg|webp|gif)(\?|$)/i.test(url)){
        viewer.innerHTML='<img src="'+escapeHtml(url)+'" style="max-width:100%;border-radius:14px">';
      }else{
        viewer.innerHTML='<video controls playsinline src="'+escapeHtml(url)+'" style="width:100%;max-height:420px;background:#000;border-radius:14px"></video><div style="margin-top:8px"><a class="g-btn g-btn-blue" href="'+escapeHtml(url)+'" target="_blank">Open Media</a></div>';
      }
      if(window.gToast) gToast('Creation loaded','Ready to review, edit, approve, and distribute.');
      return;
    }
    viewer.innerHTML='<div class="g-drawerBox"><h3>No media returned yet</h3><div class="g-drawerValue">This command may still be rendering or it was completed before the media return bridge was installed.</div></div>';
  }
  function escapeHtml(str){return String(str||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
})();