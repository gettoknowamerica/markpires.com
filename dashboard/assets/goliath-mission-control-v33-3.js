
(function(){
function patchAskButton(){document.querySelectorAll('.ask').forEach(btn=>{btn.onclick=function(e){e.preventDefault();if(window.openAskGoliath){window.openAskGoliath();}};});}
function compactAskOnFocus(){
document.addEventListener('focusin',e=>{if(e.target&&e.target.id==='gAskInput'){let b=document.getElementById('gAskBody');if(b)b.style.maxHeight=window.innerWidth<800?'18vh':'32vh';}});
document.addEventListener('focusout',e=>{if(e.target&&e.target.id==='gAskInput'){let b=document.getElementById('gAskBody');if(b)b.style.maxHeight=window.innerWidth<800?'30vh':'42vh';}});
}
function labelAgents(){document.querySelectorAll('.room').forEach(room=>{const t=(room.querySelector('h3')?.textContent||''),s=room.querySelector('.roomStatus');if(!s)return;
if(t.includes('Scout'))s.textContent=room.classList.contains('active')?'Searching records':'Standing by';
if(t.includes('Jessica'))s.textContent=room.classList.contains('active')?'Outreach active':'Standing by';
if(t.includes('Director'))s.textContent=room.classList.contains('active')?'Producing media':'Standing by';
if(t.includes('Publisher'))s.textContent=room.classList.contains('active')?'Scheduling posts':'Standing by';
if(t.includes('Analyst'))s.textContent=room.classList.contains('active')?'Scanning signals':'Standing by';
if(t.includes('Executive'))s.textContent=room.classList.contains('active')?'Prioritizing ROI':'Standing by';
});}
document.addEventListener('DOMContentLoaded',()=>{patchAskButton();compactAskOnFocus();setTimeout(labelAgents,400);});
})();
