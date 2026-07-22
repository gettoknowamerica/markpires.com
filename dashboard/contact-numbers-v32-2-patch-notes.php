<?php
/* 
V32.2 Contact Numbers patch notes.

1) Add this stylesheet below the main goliath-v32-1.css line:
<link rel="stylesheet" href="/dashboard/assets/goliath-v32-2-patch.css?v=322">

2) Replace the old Refresh Numbers anchor:
<a class="btn dark" target="_blank" href="/lead-engine/build-contact-numbers.php?key=...">Refresh Numbers</a>

With:
<div class="inlineRefresh">
  <button id="refreshNumbersBtn" class="btn dark" onclick="refreshNumbers()">Refresh Numbers</button>
  <span class="spinner"></span>
</div>

3) Add this JS before </body>:
<script>
async function refreshNumbers(){
  const wrap=document.querySelector('.inlineRefresh');
  if(wrap) wrap.classList.add('isLoading');
  const r=await fetch('/lead-engine/refresh-contact-numbers.php?key=<?=h($key)?>');
  const data=await r.json();
  alert(data.message || 'Refresh queued.');
  setTimeout(()=>location.href='/dashboard/contact-numbers.php?fresh='+Date.now(),1200);
}
</script>

4) New badge logic:
Use last visit in localStorage and compare row created_at when available.
*/
?>