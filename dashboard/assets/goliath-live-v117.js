(() => {
 const KEY=window.GOLIATH_V111_KEY||'';
 const API='/lead-engine/';
 const WAKE=/\b(?:hey|okay|ok)\s+goliath\b/i;
 const IS_IOS=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const S={
  conversationUid:localStorage.getItem('goliath_v117_conversation')||'',
  lastMessageId:Number(localStorage.getItem('goliath_v117_last_id')||0),
  recognition:null,
  enabled:localStorage.getItem('goliath_v117_enabled')==='1',
  listening:false,
  awakened:false,
  speaking:false,
  waiting:false,
  polling:false,
  restarting:false,
  audioContext:null,
  audioSource:null,
  lastSpoken:'',
  pollTimer:null,
  restartTimer:null,
  silenceTimer:null
 };
 const $=id=>document.getElementById(id);
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

 function bubble(name,text,cls,id=''){
  const box=$('v111Conversation');if(!box||!text)return;
  if(id&&document.querySelector(`[data-message-id="${id}"]`))return;
  const d=document.createElement('div');d.className=`v111Bubble ${cls}`;if(id)d.dataset.messageId=id;
  d.innerHTML=`<b>${esc(name)}</b><span>${esc(text)}</span>`;
  box.appendChild(d);box.scrollTop=box.scrollHeight;
 }
 function status(text,good=false){
  const c=$('v111Connection');if(c){c.textContent=text;c.classList.toggle('online',good)}
 }
 function voiceStatus(text,active=false){
  const v=$('v111VoiceState');if(v)v.textContent=text;
  const b=$('v111VoiceButton');if(b){b.textContent=S.enabled?'🎙️ Goliath Enabled':'🎙️ Enable Hands-Free Goliath';b.classList.toggle('active',active)}
 }
 function audioEl(){
  let a=$('v111AudioPlayer');
  if(!a){a=document.createElement('audio');a.id='v111AudioPlayer';document.body.appendChild(a)}
  a.preload='auto';a.setAttribute('playsinline','');a.setAttribute('webkit-playsinline','');return a;
 }
 async function unlockAudio(){
  try{
   const Ctx=window.AudioContext||window.webkitAudioContext;
   if(Ctx){
    if(!S.audioContext)S.audioContext=new Ctx();
    if(S.audioContext.state!=='running')await S.audioContext.resume();
    const buffer=S.audioContext.createBuffer(1,1,22050);
    const source=S.audioContext.createBufferSource();source.buffer=buffer;source.connect(S.audioContext.destination);source.start(0);
   }
   return true;
  }catch(_){return false}
 }
 function stopPlayback(){
  if(S.audioSource){try{S.audioSource.stop(0)}catch(_){}S.audioSource=null}
  const a=audioEl();try{a.pause();a.currentTime=0}catch(_){}
  if('speechSynthesis'in window)speechSynthesis.cancel();
  S.speaking=false;
 }
 function stopRecognition(){
  clearTimeout(S.restartTimer);
  if(S.recognition){
   try{S.recognition.onend=null;S.recognition.abort()}catch(_){}
   S.recognition=null;
  }
  S.listening=false;
 }
 function browserFallback(text){
  if(!('speechSynthesis'in window)){afterSpeech();return}
  const u=new SpeechSynthesisUtterance(text);u.rate=.94;u.pitch=.78;u.volume=1;
  u.onstart=()=>{S.speaking=true;voiceStatus('Goliath is speaking — say “Hey Goliath” to interrupt.',true)};
  u.onend=afterSpeech;u.onerror=afterSpeech;speechSynthesis.speak(u);
 }
 async function playKokoro(text,url){
  stopPlayback();
  if(!url){browserFallback(text);return}
  const full=new URL(url,location.origin).href+'?v='+Date.now();

  try{
   await unlockAudio();
   if(S.audioContext){
    const response=await fetch(full,{cache:'no-store'});
    if(!response.ok)throw new Error('Audio HTTP '+response.status);
    const arrayBuffer=await response.arrayBuffer();
    const decoded=await S.audioContext.decodeAudioData(arrayBuffer.slice(0));
    const source=S.audioContext.createBufferSource();
    source.buffer=decoded;source.connect(S.audioContext.destination);S.audioSource=source;
    source.onended=()=>{if(S.audioSource===source)S.audioSource=null;afterSpeech()};
    S.speaking=true;voiceStatus('Goliath is speaking — say “Hey Goliath” to interrupt.',true);
    source.start(0);
    // Resume wake listening during playback for barge-in. Echo filtering below prevents
    // Goliath from responding to his own audio in most supported browsers.
    setTimeout(startWakeListener,120);
    return;
   }
  }catch(_){}

  const a=audioEl();a.src=full;
  a.onplay=()=>{S.speaking=true;voiceStatus('Goliath is speaking — say “Hey Goliath” to interrupt.',true);setTimeout(startWakeListener,120)};
  a.onended=afterSpeech;a.onerror=()=>browserFallback(text);
  try{await a.play()}catch(_){browserFallback(text)}
 }
 function afterSpeech(){
  S.speaking=false;S.awakened=false;
  voiceStatus('Listening for “Hey Goliath”…',true);
  startWakeListener();
 }
 function schedulePoll(ms){clearTimeout(S.pollTimer);S.pollTimer=setTimeout(poll,ms)}
 async function sendMessage(text){
  const message=String(text||'').trim();if(!message)return;
  stopRecognition();S.awakened=false;S.waiting=true;
  bubble('Mark',message,'mark');status('THINKING',true);voiceStatus('Goliath is thinking…',true);
  const input=$('v111ChatInput');if(input)input.value='';
  try{
   const response=await fetch(API+'ask-goliath-live-v117.php',{
    method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Accept':'application/json'},
    body:JSON.stringify({
     key:KEY,message,conversation_uid:S.conversationUid,
     voice:true,tts_requested:true,wake_phrase:'hey goliath',
     client:IS_IOS?'ios_browser':'browser'
    })
   });
   const raw=await response.text();let data;
   try{data=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,250)||`HTTP ${response.status}`)}
   if(!response.ok||!data.ok)throw new Error(data.details?.message||data.error||'Ask Goliath failed');
   S.conversationUid=data.conversation_uid;
   localStorage.setItem('goliath_v117_conversation',S.conversationUid);
   schedulePoll(80);
  }catch(error){
   S.waiting=false;bubble('System',error.message,'error');status('ERROR');afterSpeech();
  }
 }
 async function poll(){
  if(!S.conversationUid||S.polling){schedulePoll(S.waiting?180:900);return}
  S.polling=true;
  try{
   const response=await fetch(
    `${API}ask-goliath-result-v116-1.php?key=${encodeURIComponent(KEY)}&conversation_uid=${encodeURIComponent(S.conversationUid)}&after_id=${S.lastMessageId}&_=${Date.now()}`,
    {cache:'no-store'}
   );
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.error||'poll failed');
   for(const message of data.messages||[]){
    if(message.message_type==='pending'||!message.message_text)continue;
    S.lastMessageId=Math.max(S.lastMessageId,Number(message.id||0));
    localStorage.setItem('goliath_v117_last_id',String(S.lastMessageId));
    if(message.speaker_key==='mark')continue;
    if(message.message_type==='error'&&/unknown worker error/i.test(message.message_text||''))continue;
    bubble(message.speaker_name||'Goliath',message.message_text,message.message_type==='error'?'error':'goliath',message.id);
    if(message.speaker_key==='goliath'&&message.message_type!=='error'){
     S.waiting=false;status('LIVE',true);S.lastSpoken=String(message.message_text).toLowerCase();
     await playKokoro(message.message_text,message.audio_url||'');
    }
   }
   status(S.waiting?'THINKING':'LIVE',true);
   schedulePoll(data.has_pending?180:850);
  }catch(_){
   status('RECONNECTING');schedulePoll(S.waiting?600:1300);
  }finally{S.polling=false}
 }
 function processTranscript(transcript,isFinal){
  let heard=String(transcript||'').trim();
  if(!heard)return;

  const normalized=heard.toLowerCase().replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
  // Ignore likely acoustic echo from Goliath's current answer.
  if(S.speaking&&S.lastSpoken&&
     (S.lastSpoken.includes(normalized)||normalized.includes(S.lastSpoken.slice(0,70)))&&!WAKE.test(heard)){
   return;
  }

  if(!S.awakened){
   const wakeMatch=heard.match(WAKE);
   if(!wakeMatch){
    voiceStatus('Listening for “Hey Goliath”…',true);
    return;
   }
   stopPlayback();
   S.awakened=true;
   heard=heard.slice((wakeMatch.index||0)+wakeMatch[0].length).replace(/^[\s,:;-]+/,'').trim();
   voiceStatus('Yes, Mark — I’m listening…',true);
   clearTimeout(S.silenceTimer);
   if(heard&&isFinal){sendMessage(heard);return}
   S.silenceTimer=setTimeout(()=>{
    if(S.awakened){S.awakened=false;voiceStatus('Listening for “Hey Goliath”…',true)}
   },9000);
   return;
  }

  if(isFinal){
   clearTimeout(S.silenceTimer);
   const cleaned=heard.replace(WAKE,'').replace(/^[\s,:;-]+/,'').trim();
   if(cleaned)sendMessage(cleaned);
  }else{
   voiceStatus(`Hearing: ${heard}`,true);
  }
 }
 function createRecognition(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR)return null;
  const recognition=new SR();
  recognition.lang='en-US';recognition.continuous=true;recognition.interimResults=true;
  recognition.onstart=()=>{S.listening=true;voiceStatus(S.awakened?'Yes, Mark — I’m listening…':'Listening for “Hey Goliath”…',true)};
  recognition.onresult=event=>{
   for(let i=event.resultIndex;i<event.results.length;i++){
    processTranscript(event.results[i][0].transcript,event.results[i].isFinal);
   }
  };
  recognition.onerror=event=>{
   S.listening=false;
   if(event.error==='not-allowed'){
    S.enabled=false;localStorage.removeItem('goliath_v117_enabled');
    voiceStatus('Microphone permission is required. Tap Enable Hands-Free Goliath once.');
   }else if(!['no-speech','aborted','audio-capture'].includes(event.error)){
    voiceStatus(`Voice reconnecting: ${event.error}`);
   }
  };
  recognition.onend=()=>{
   S.listening=false;S.recognition=null;
   if(S.enabled&&!S.waiting)S.restartTimer=setTimeout(startWakeListener,IS_IOS?500:220);
  };
  return recognition;
 }
 async function startWakeListener(){
  if(!S.enabled||S.waiting||S.restarting||S.listening)return;
  S.restarting=true;
  try{
   await unlockAudio();
   if(!S.recognition)S.recognition=createRecognition();
   if(!S.recognition){
    voiceStatus('This browser does not support hands-free speech recognition.');
    return;
   }
   try{S.recognition.start()}catch(_){}
  }finally{S.restarting=false}
 }
 async function enableHandsFree(){
  await unlockAudio();
  try{
   const stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true}});
   stream.getTracks().forEach(track=>track.stop());
   S.enabled=true;localStorage.setItem('goliath_v117_enabled','1');
   voiceStatus('Listening for “Hey Goliath”…',true);
   startWakeListener();
  }catch(_){
   S.enabled=false;voiceStatus('Allow microphone access to enable hands-free Goliath.');
  }
 }
 function disableHandsFree(){
  S.enabled=false;localStorage.removeItem('goliath_v117_enabled');stopRecognition();stopPlayback();
  voiceStatus('Hands-free Goliath is disabled.');
 }
 window.GoliathV117={enableHandsFree,disableHandsFree,sendMessage,startWakeListener};

 document.addEventListener('DOMContentLoaded',()=>{
  $('v111ChatForm')?.addEventListener('submit',event=>{
   event.preventDefault();sendMessage($('v111ChatInput')?.value||'');
  });
  $('v111VoiceButton')?.addEventListener('click',()=>{
   if(S.enabled)disableHandsFree();else enableHandsFree();
  });
  $('v111StopVoice')?.addEventListener('click',disableHandsFree);
  window.addEventListener('focus',()=>{schedulePoll(50);if(S.enabled)startWakeListener()});
  document.addEventListener('visibilitychange',()=>{
   if(document.hidden){stopRecognition()}
   else if(S.enabled){startWakeListener();schedulePoll(50)}
  });
  schedulePoll(100);
  if(S.enabled){
   voiceStatus('Restoring hands-free Goliath…',true);
   // Browsers may permit immediate restart after the one-time prior grant.
   setTimeout(startWakeListener,350);
  }else{
   voiceStatus('Tap once to enable. After that, say “Hey Goliath” anytime.');
  }
 });
})();