(() => {
 const KEY=window.GOLIATH_V111_KEY||'';
 const API='/lead-engine/';
 const WAKE=/\b(?:hey|okay|ok)\s+goliath\b/i;
 const S={
  enabled:localStorage.getItem('goliath_v1182_enabled')==='1',
  conversationActive:localStorage.getItem('goliath_v1182_conversation_active')==='1',
  conversationUid:localStorage.getItem('goliath_v1182_uid')||'',
  lastId:Number(localStorage.getItem('goliath_v1182_last_id')||0),
  rec:null,listening:false,waiting:false,speaking:false,audioCtx:null,audioSource:null,polling:false,timer:null
 };
 const $=id=>document.getElementById(id);
 function setState(t){const e=$('v111VoiceState');if(e)e.textContent=t}
 async function unlock(){
  const C=window.AudioContext||window.webkitAudioContext;
  if(C){if(!S.audioCtx)S.audioCtx=new C();if(S.audioCtx.state!=='running')await S.audioCtx.resume()}
 }
 function stopAudio(){if(S.audioSource){try{S.audioSource.stop()}catch(_){}S.audioSource=null}const a=$('v111AudioPlayer');if(a)try{a.pause()}catch(_){}S.speaking=false}
 function stopRec(){if(S.rec){try{S.rec.onend=null;S.rec.abort()}catch(_){}S.rec=null}S.listening=false}
 async function speak(text,url){
  stopRec();stopAudio();await unlock();
  if(url&&S.audioCtx){
   try{
    const r=await fetch(new URL(url,location.origin).href+'?v='+Date.now(),{cache:'no-store'});
    const b=await r.arrayBuffer();const decoded=await S.audioCtx.decodeAudioData(b.slice(0));
    const src=S.audioCtx.createBufferSource();src.buffer=decoded;src.connect(S.audioCtx.destination);S.audioSource=src;S.speaking=true;
    src.onended=()=>{S.speaking=false;startListening()};src.start();return;
   }catch(_){}
  }
  if('speechSynthesis'in window){
   const u=new SpeechSynthesisUtterance(text);u.onend=()=>startListening();speechSynthesis.speak(u);
  }else startListening();
 }
 async function send(text){
  const message=String(text||'').trim();if(!message)return;
  stopRec();S.waiting=true;setState('Goliath is thinking…');
  const r=await fetch(API+'ask-goliath-live-v117.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,message,conversation_uid:S.conversationUid,voice:true,tts_requested:true})});
  const d=await r.json();if(!d.ok)throw new Error(d.error||'Ask Goliath failed');
  S.conversationUid=d.conversation_uid;localStorage.setItem('goliath_v1182_uid',S.conversationUid);pollSoon(100);
 }
 async function poll(){
  if(S.polling||!S.conversationUid){pollSoon(700);return}S.polling=true;
  try{
   const r=await fetch(`${API}ask-goliath-result-v116-1.php?key=${encodeURIComponent(KEY)}&conversation_uid=${encodeURIComponent(S.conversationUid)}&after_id=${S.lastId}&_=${Date.now()}`,{cache:'no-store'});
   const d=await r.json();
   for(const m of d.messages||[]){
    if(m.message_type==='pending'||!m.message_text)continue;
    S.lastId=Math.max(S.lastId,Number(m.id||0));localStorage.setItem('goliath_v1182_last_id',String(S.lastId));
    if(m.speaker_key==='goliath'&&m.message_type!=='error'){S.waiting=false;await speak(m.message_text,m.audio_url||'')}
   }
  }catch(_){}finally{S.polling=false;pollSoon(500)}
 }
 function pollSoon(ms){clearTimeout(S.timer);S.timer=setTimeout(poll,ms)}
 function createRec(){
  const R=window.SpeechRecognition||window.webkitSpeechRecognition;if(!R)return null;
  const rec=new R();rec.lang='en-US';rec.continuous=true;rec.interimResults=true;
  rec.onstart=()=>{S.listening=true;setState(S.conversationActive?'Conversation active — just speak.':'Listening for “Hey Goliath”…')};
  rec.onresult=e=>{
   for(let i=e.resultIndex;i<e.results.length;i++){
    if(!e.results[i].isFinal)continue;
    let text=e.results[i][0].transcript.trim();
    if(!S.conversationActive){
     const match=text.match(WAKE);if(!match)continue;
     S.conversationActive=true;localStorage.setItem('goliath_v1182_conversation_active','1');
     text=text.slice((match.index||0)+match[0].length).replace(/^[\s,:;-]+/,'').trim();
     setState('Conversation active — just speak.');
     if(!text)continue;
    }
    if(S.speaking)stopAudio();
    if(text)send(text);
   }
  };
  rec.onerror=()=>{};
  rec.onend=()=>{S.listening=false;S.rec=null;if(S.enabled&&!S.waiting)setTimeout(startListening,350)};
  return rec;
 }
 async function startListening(){
  if(!S.enabled||S.waiting||S.listening)return;
  await unlock();S.rec=createRec();if(!S.rec)return;try{S.rec.start()}catch(_){}
 }
 async function enable(){
  await unlock();
  const stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true}});
  stream.getTracks().forEach(t=>t.stop());
  S.enabled=true;localStorage.setItem('goliath_v1182_enabled','1');startListening();
 }
 function stop(){
  S.enabled=false;S.conversationActive=false;
  localStorage.removeItem('goliath_v1182_enabled');localStorage.removeItem('goliath_v1182_conversation_active');
  stopRec();stopAudio();setState('Conversation stopped.');
 }
 document.addEventListener('DOMContentLoaded',()=>{
  $('v111VoiceButton')?.addEventListener('click',()=>S.enabled?startListening():enable());
  $('v111StopVoice')?.addEventListener('click',stop);
  if(S.enabled)startListening();pollSoon(100);
 });
})();