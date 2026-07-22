(() => {
 const KEY=window.GOLIATH_V111_KEY||'',API='/lead-engine/';
 const IS_IOS=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const IS_MOBILE=IS_IOS||matchMedia('(max-width:760px)').matches;
 const S={
  conversationUid:sessionStorage.getItem('goliath_v1165_conversation')||'',
  lastMessageId:Number(sessionStorage.getItem('goliath_v1165_last_id')||0),
  recognition:null,voiceActive:false,speaking:false,polling:false,waiting:false,
  audioUnlocked:false,lastSpoken:'',pollTimer:null,restartTimer:null,captureBusy:false,audioContext:null,audioSource:null
 };
 const $=id=>document.getElementById(id);
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

 function bubble(name,text,cls,id=''){
  const box=$('v111Conversation');if(!box||!text)return;
  if(id&&document.querySelector(`[data-message-id="${id}"]`))return;
  const d=document.createElement('div');d.className=`v111Bubble ${cls}`;if(id)d.dataset.messageId=id;
  d.innerHTML=`<b>${esc(name)}</b><span>${esc(text)}</span>`;box.appendChild(d);box.scrollTop=box.scrollHeight;
 }
 function connection(text,good=false){const e=$('v111Connection');if(e){e.textContent=text;e.classList.toggle('online',good)}}
 function voiceState(text,active=false){const e=$('v111VoiceState');if(e)e.textContent=text;const b=$('v111VoiceButton');if(b)b.classList.toggle('active',active)}
 function audioEl(){
  let a=$('v111AudioPlayer');if(!a){a=document.createElement('audio');a.id='v111AudioPlayer';document.body.appendChild(a)}
  a.preload='auto';a.setAttribute('playsinline','');a.setAttribute('webkit-playsinline','');return a;
 }
 async function unlockAudio(){
  if(S.audioUnlocked&&S.audioContext?.state==='running')return true;
  try{
   const Ctx=window.AudioContext||window.webkitAudioContext;
   if(Ctx){
    if(!S.audioContext)S.audioContext=new Ctx();
    await S.audioContext.resume();
    const b=S.audioContext.createBuffer(1,1,22050);
    const src=S.audioContext.createBufferSource();src.buffer=b;src.connect(S.audioContext.destination);src.start(0);
   }
   S.audioUnlocked=true;return true;
  }catch(_){return false}
 }
 function releaseRecognition(){
  clearTimeout(S.restartTimer);
  if(S.recognition){
   try{S.recognition.onend=null;S.recognition.abort()}catch(_){}
   if(IS_MOBILE)S.recognition=null;
  }
  S.captureBusy=false;
 }
 function stopSpeech(){
  if(S.audioSource){try{S.audioSource.stop(0)}catch(_){}S.audioSource=null}
  const a=audioEl();try{a.pause();a.currentTime=0}catch(_){}
  if('speechSynthesis'in window)speechSynthesis.cancel();S.speaking=false;
 }
 function browserFallback(text){
  if(!('speechSynthesis'in window)){voiceState('Audio unavailable on this browser.');return}
  const u=new SpeechSynthesisUtterance(text);u.rate=.92;u.pitch=.8;u.volume=1;
  u.onstart=()=>{S.speaking=true};u.onend=()=>{S.speaking=false;voiceState(IS_MOBILE?'Tap Live Voice to reply.':'Listening…',!IS_MOBILE)};
  speechSynthesis.speak(u);
 }
 async function speak(text,url=''){
  if(!text)return;
  releaseRecognition();
  stopSpeech();
  S.lastSpoken=String(text).toLowerCase();
  if(!url){browserFallback(text);return}
  const fullUrl=new URL(url,location.origin).href+'?cb='+Date.now();

  // iPhone is most reliable when audio is decoded through an AudioContext that
  // was unlocked by the user's Start Live Voice tap.
  try{
   await unlockAudio();
   if(S.audioContext){
    const response=await fetch(fullUrl,{cache:'no-store'});
    if(!response.ok)throw new Error('audio HTTP '+response.status);
    const bytes=await response.arrayBuffer();
    const buffer=await S.audioContext.decodeAudioData(bytes.slice(0));
    const source=S.audioContext.createBufferSource();
    source.buffer=buffer;source.connect(S.audioContext.destination);S.audioSource=source;
    source.onended=()=>{
     if(S.audioSource===source)S.audioSource=null;
     S.speaking=false;
     voiceState(IS_MOBILE?'Tap Live Voice to reply.':'Listening…',!IS_MOBILE);
     if(!IS_MOBILE&&S.voiceActive)restartDesktop();
    };
    S.speaking=true;voiceState('Goliath is speaking. Tap Live Voice to interrupt.',true);
    source.start(0);
    return;
   }
  }catch(_){}

  const a=audioEl();a.src=fullUrl;
  a.onplay=()=>{S.speaking=true;voiceState('Goliath is speaking. Tap Live Voice to interrupt.',true)};
  a.onended=()=>{S.speaking=false;voiceState(IS_MOBILE?'Tap Live Voice to reply.':'Listening…',!IS_MOBILE);if(!IS_MOBILE&&S.voiceActive)restartDesktop()};
  a.onerror=()=>{voiceState('Kokoro audio could not play. Using device voice.');browserFallback(text)};
  try{await a.play()}catch(_){voiceState('Mobile audio was blocked. Tap Live Voice once and try again.');browserFallback(text)}
 }
 async function sendMessage(message,voice=true){
  const text=String(message||'').trim();if(!text)return;
  await unlockAudio();bubble('Mark',text,'mark');const input=$('v111ChatInput');if(input)input.value='';
  connection('THINKING',true);S.waiting=true;
  try{
   const r=await fetch(API+'ask-goliath-live-v116-1.php',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({key:KEY,message:text,conversation_uid:S.conversationUid,voice,tts_requested:voice})});
   const raw=await r.text();let d;try{d=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,220)||`HTTP ${r.status}`)}
   if(!r.ok||!d.ok)throw new Error(d.details?.message||d.error||'Ask Goliath failed');
   S.conversationUid=d.conversation_uid;sessionStorage.setItem('goliath_v1165_conversation',S.conversationUid);schedule(80);
  }catch(e){S.waiting=false;bubble('System',e.message,'error');connection('ERROR')}
 }
 function schedule(ms){clearTimeout(S.pollTimer);S.pollTimer=setTimeout(poll,ms)}
 async function poll(){
  if(!S.conversationUid||S.polling){schedule(S.waiting?250:1000);return}
  S.polling=true;
  try{
   const r=await fetch(`${API}ask-goliath-result-v116-1.php?key=${encodeURIComponent(KEY)}&conversation_uid=${encodeURIComponent(S.conversationUid)}&after_id=${S.lastMessageId}&_=${Date.now()}`,{cache:'no-store'});
   const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'poll failed');
   for(const m of d.messages||[]){
    if(m.message_type==='pending'||!m.message_text)continue;
    S.lastMessageId=Math.max(S.lastMessageId,Number(m.id||0));sessionStorage.setItem('goliath_v1165_last_id',String(S.lastMessageId));
    if(m.speaker_key==='mark')continue;
    // Do not replay old generic worker errors into a new mobile conversation.
    if(m.message_type==='error'&&/unknown worker error/i.test(m.message_text||''))continue;
    bubble(m.speaker_name||'Goliath',m.message_text,m.message_type==='error'?'error':'goliath',m.id);
    if(m.speaker_key==='goliath'&&m.message_type!=='error'){S.waiting=false;await speak(m.message_text,m.audio_url||'')}
   }
   connection(S.waiting?'THINKING':'LIVE',true);schedule(d.has_pending?250:900);
  }catch(_){connection('RECONNECTING');schedule(S.waiting?700:1500)}
  finally{S.polling=false}
 }
 function createRecognition(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;if(!SR)return null;
  const rec=new SR();rec.lang='en-US';rec.interimResults=true;rec.continuous=!IS_MOBILE;
  rec.onstart=()=>{S.captureBusy=true;voiceState('Listening…',true)};
  rec.onspeechstart=()=>{if(S.speaking)stopSpeech();voiceState('I hear you…',true)};
  rec.onresult=e=>{
   let finalText='',interim='';
   for(let i=e.resultIndex;i<e.results.length;i++){const t=e.results[i][0].transcript;if(e.results[i].isFinal)finalText+=t;else interim+=t}
   if(interim)voiceState(`Hearing: ${interim}`,true);
   if(finalText.trim()){
    const clean=finalText.trim().replace(/^\s*hey\s+goliath[\s,:-]*/i,'').trim();
    S.captureBusy=false;try{rec.onend=null;rec.abort()}catch(_){};if(IS_MOBILE)S.recognition=null;
    sendMessage(clean||'Hey Goliath. Are you there?',true);
   }
  };
  rec.onerror=e=>{
   S.captureBusy=false;
   if(e.error==='audio-capture')voiceState('Microphone became busy. Tap Live Voice again.');
   else if(e.error==='not-allowed')voiceState('Allow microphone access in browser settings.');
   else if(e.error!=='no-speech'&&e.error!=='aborted')voiceState(`Voice: ${e.error||'error'}`);
  };
  rec.onend=()=>{
   S.captureBusy=false;
   if(!IS_MOBILE&&S.voiceActive&&!S.speaking)S.restartTimer=setTimeout(restartDesktop,500);
   else if(IS_MOBILE&&!S.waiting&&!S.speaking)voiceState('Tap Live Voice to speak.');
  };
  return rec;
 }
 function restartDesktop(){if(IS_MOBILE||!S.voiceActive||S.speaking||S.captureBusy)return;try{S.recognition.start()}catch(_){}}
 async function startVoice(){
  await unlockAudio();stopSpeech();
  if(IS_MOBILE&&S.recognition){releaseRecognition()}
  if(!S.recognition)S.recognition=createRecognition();
  if(!S.recognition){voiceState('Speech recognition is not supported in this browser.');return}
  if(IS_MOBILE){
   S.voiceActive=false;
   try{S.recognition.start()}catch(_){releaseRecognition();voiceState('Microphone is busy. Wait a moment and tap again.')}
  }else{
   S.voiceActive=true;restartDesktop();
  }
 }
 function stopVoice(){
  S.voiceActive=false;clearTimeout(S.restartTimer);stopSpeech();
  releaseRecognition();voiceState('Voice stopped.');
 }
 async function updateFeed(){
  try{
   const r=await fetch(`${API}goliath-live-feed-v116-4.php?key=${encodeURIComponent(KEY)}&_=${Date.now()}`,{cache:'no-store'});
   const d=await r.json();if(!d.ok)return;
   for(const e of d.executives||[]){
    const cell=document.querySelector(`.agentCell[data-executive="${CSS.escape(e.executive_key)}"]`);if(!cell)continue;
    const metric=cell.querySelector('.metric');if(metric)metric.textContent=String(e.active_count??0);
    const task=cell.querySelector('.taskText');if(task)task.innerHTML=`<strong>${esc(e.current_mode||e.status||'ready')}:</strong> ${esc(e.current_action||'Ready for mission')}`;
    const bar=cell.querySelector('.battery b'),pct=cell.querySelector('.meterLine em'),p=Math.max(0,Math.min(100,Number(e.progress||0)));
    if(bar)bar.style.width=`${p}%`;if(pct)pct.textContent=`${p}%`;
   }
   document.querySelectorAll('[data-v112-finished]').forEach(el=>el.textContent=String(d.counts?.artifacts?.reviewable??0));
   const scoreboard=document.querySelector('.scoreboard');
   if(scoreboard){
    scoreboard.querySelectorAll('.scoreRow').forEach(x=>x.remove());
    for(const e of d.executives||[]){
     if(!e.review_count)continue;
     scoreboard.insertAdjacentHTML('beforeend',`<a class="scoreRow monitor-link" href="/dashboard/goliath-review-center.php?exec=${encodeURIComponent(e.executive_key)}"><b>${esc(e.display_name)}</b><span>${e.review_count}</span></a>`);
    }
   }
   const list=document.querySelector('.activityList');
   if(list&&(d.review_items||[]).length){
    list.innerHTML=d.review_items.slice(0,9).map(e=>`<a class="activityItem monitor-link" href="${esc(e.url)}"><span class="aiIcon">✅</span><span><b>${esc(e.title)}</b><small>${esc(e.executive_key)} · ${esc(e.artifact_type)} · ready for review</small></span><em>READY</em></a>`).join('');
   }
  }catch(_){}
 }
 function startGoliathMovement(){
  const avatar=document.querySelector('.goliathAvatar');if(!avatar)return;
  function step(){
   const x=10+Math.random()*70,y=4+Math.random()*8,current=parseFloat(avatar.dataset.x||'28'),dir=x>=current?1:-1;
   avatar.dataset.x=String(x);avatar.style.transform=`translateX(-50%) scaleX(${dir})`;avatar.style.left=`${x}%`;avatar.style.bottom=`${y}%`;
   const walk=1800+Math.random()*2500;avatar.style.transition=`left ${walk}ms linear,bottom ${walk}ms linear,transform .35s ease`;
   setTimeout(()=>{avatar.classList.add('goliathStopped');setTimeout(()=>{avatar.classList.remove('goliathStopped');step()},700+Math.random()*1900)},walk);
  }step();
 }
 window.GoliathV111={startVoice,stopVoice,sendMessage};
 document.addEventListener('DOMContentLoaded',()=>{
  $('v111ChatForm')?.addEventListener('submit',e=>{e.preventDefault();sendMessage($('v111ChatInput')?.value||'',false)});
  $('v111VoiceButton')?.addEventListener('click',startVoice);$('v111StopVoice')?.addEventListener('click',stopVoice);
  document.addEventListener('pointerdown',unlockAudio,{once:true});window.addEventListener('focus',()=>schedule(50));
  updateFeed();setInterval(updateFeed,5000);schedule(100);startGoliathMovement();
 });
})();