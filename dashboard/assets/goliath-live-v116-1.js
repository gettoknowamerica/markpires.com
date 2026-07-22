(() => {
 const KEY=window.GOLIATH_V111_KEY||'', API='/lead-engine/';
 const S={
   conversationUid:localStorage.getItem('goliath_v1161_conversation')||'',
   lastMessageId:Number(localStorage.getItem('goliath_v1161_last_id')||0),
   recognition:null,voiceActive:false,speaking:false,polling:false,waiting:false,
   lastSpoken:'',replyStarted:0,audioUnlocked:false,pollTimer:null
 };
 const $=id=>document.getElementById(id);
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

 function bubble(name,text,cls,id=''){
   const box=$('v111Conversation');if(!box||!text)return;
   if(id&&document.querySelector(`[data-message-id="${id}"]`))return;
   const d=document.createElement('div');d.className=`v111Bubble ${cls}`;
   if(id)d.dataset.messageId=id;
   d.innerHTML=`<b>${esc(name)}</b><span>${esc(text)}</span>`;
   box.appendChild(d);box.scrollTop=box.scrollHeight;
 }
 function connection(text,good=false){const e=$('v111Connection');if(e){e.textContent=text;e.classList.toggle('online',good)}}
 function voiceState(text,active=false){const e=$('v111VoiceState');if(e)e.textContent=text;const b=$('v111VoiceButton');if(b)b.classList.toggle('active',active)}
 function audioEl(){
   let a=$('v111AudioPlayer');
   if(!a){a=document.createElement('audio');a.id='v111AudioPlayer';a.preload='auto';a.setAttribute('playsinline','');document.body.appendChild(a)}
   return a;
 }
 async function unlockAudio(){
   if(S.audioUnlocked)return true;
   const a=audioEl();
   try{
     a.muted=true;
     a.src='data:audio/mp3;base64,//uQZAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAEAAACcQCA';
     await a.play();a.pause();a.currentTime=0;a.muted=false;S.audioUnlocked=true;return true;
   }catch(_){return false}
 }
 function stopSpeech(){
   const a=audioEl();try{a.pause();a.currentTime=0}catch(_){}
   if('speechSynthesis'in window)speechSynthesis.cancel();
   S.speaking=false;
 }
 function restartRecognition(){if(S.voiceActive&&S.recognition&&!S.speaking)try{S.recognition.start()}catch(_){}}
 function afterSpeech(){S.speaking=false;if(S.voiceActive){voiceState('Listening…',true);setTimeout(restartRecognition,180)}}

 function browserFallback(text){
   voiceState('Kokoro audio missing — browser voice fallback.',true);
   if(!('speechSynthesis'in window)){afterSpeech();return}
   const u=new SpeechSynthesisUtterance(text);u.rate=.91;u.pitch=.78;u.volume=1;
   u.onstart=()=>{S.speaking=true};u.onend=afterSpeech;u.onerror=afterSpeech;speechSynthesis.speak(u);
 }
 async function speak(text,url=''){
   if(!text)return;stopSpeech();
   S.lastSpoken=String(text).toLowerCase().replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
   if(!url){browserFallback(text);return}
   const a=audioEl();a.src=url+(url.includes('?')?'&':'?')+'cb='+Date.now();
   a.onplay=()=>{S.speaking=true;voiceState('Kokoro is speaking — talk to interrupt.',true)};
   a.onended=afterSpeech;a.onerror=()=>{voiceState('Kokoro file could not play on this device.',true);browserFallback(text)};
   try{await a.play()}catch(_){voiceState('Tap Start Live Voice once to unlock iPhone audio.',true);browserFallback(text)}
 }

 async function sendMessage(message,voice=false){
   const text=String(message||'').trim();if(!text)return;
   await unlockAudio();bubble('Mark',text,'mark');const input=$('v111ChatInput');if(input)input.value='';
   connection('THINKING',true);S.waiting=true;S.replyStarted=Date.now();
   try{
     const r=await fetch(API+'ask-goliath-live-v116-1.php',{
       method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Accept':'application/json'},
       body:JSON.stringify({key:KEY,message:text,conversation_uid:S.conversationUid,voice:voice||S.voiceActive,tts_requested:voice||S.voiceActive})
     });
     const raw=await r.text();let d;try{d=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,260)||`HTTP ${r.status}`)}
     if(!r.ok||!d.ok)throw new Error(d.details?.message||d.error||'Ask Goliath failed');
     S.conversationUid=d.conversation_uid;localStorage.setItem('goliath_v1161_conversation',S.conversationUid);
     schedule(80);
   }catch(e){S.waiting=false;bubble('System',e.message,'error');connection('ERROR')}
 }

 function schedule(ms){clearTimeout(S.pollTimer);S.pollTimer=setTimeout(poll,ms)}
 async function poll(){
   if(!S.conversationUid||S.polling){schedule(S.waiting?250:900);return}
   S.polling=true;
   try{
     const r=await fetch(`${API}ask-goliath-result-v116-1.php?key=${encodeURIComponent(KEY)}&conversation_uid=${encodeURIComponent(S.conversationUid)}&after_id=${S.lastMessageId}&_=${Date.now()}`,{cache:'no-store'});
     const raw=await r.text();let d;try{d=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,220)||`HTTP ${r.status}`)}
     if(!r.ok||!d.ok)throw new Error(d.details?.message||d.error||'poll failed');
     for(const m of d.messages||[]){
       if(m.message_type==='pending'||!m.message_text)continue;
       S.lastMessageId=Math.max(S.lastMessageId,Number(m.id||0));
       localStorage.setItem('goliath_v1161_last_id',String(S.lastMessageId));
       if(m.speaker_key==='mark')continue;
       bubble(m.speaker_name||'Goliath',m.message_text,m.message_type==='error'?'error':'goliath',m.id);
       if(m.speaker_key==='goliath'&&m.message_type!=='error'){
         S.waiting=false;
         await speak(m.message_text,m.audio_url||'');
       }
     }
     connection(S.waiting?'THINKING':'LIVE',true);
     if(S.waiting&&Date.now()-S.replyStarted>20000)connection('STILL THINKING',true);
     schedule(d.has_pending?250:800);
   }catch(_){connection('RECONNECTING');schedule(S.waiting?700:1400)}
   finally{S.polling=false}
 }

 function startVoice(){
   const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
   if(!SR){voiceState('Live recognition requires Chrome, Edge, or supported Safari.');return}
   unlockAudio();stopSpeech();
   if(!S.recognition){
     const rec=new SR();rec.continuous=true;rec.interimResults=true;rec.lang='en-US';
     rec.onstart=()=>voiceState('Listening…',true);
     rec.onspeechstart=()=>{if(S.speaking)stopSpeech();voiceState('I hear you…',true)};
     rec.onresult=e=>{
       let finalText='',interim='';
       for(let i=e.resultIndex;i<e.results.length;i++){
         const t=e.results[i][0].transcript;if(e.results[i].isFinal)finalText+=t;else interim+=t;
       }
       if(interim)voiceState(`Hearing: ${interim}`,true);
       if(finalText.trim()){
         const heard=finalText.trim(),norm=heard.toLowerCase().replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
         if(S.speaking&&S.lastSpoken&&(S.lastSpoken.includes(norm)||norm.includes(S.lastSpoken.slice(0,55))))return;
         const clean=heard.replace(/^\s*hey\s+goliath[\s,:-]*/i,'').trim();
         sendMessage(clean||'Hey Goliath. Are you there?',true);
       }
     };
     rec.onerror=e=>voiceState(e.error==='not-allowed'?'Allow microphone access in browser settings.':`Voice: ${e.error||'error'}`);
     rec.onend=()=>{if(S.voiceActive&&!S.speaking)setTimeout(restartRecognition,250)};
     S.recognition=rec;
   }
   S.voiceActive=true;restartRecognition();
 }
 function stopVoice(){S.voiceActive=false;stopSpeech();if(S.recognition)try{S.recognition.stop()}catch(_){}voiceState('Voice stopped.')}

 async function updateFeed(){
   try{
     const r=await fetch(`${API}goliath-live-feed-v116-1.php?key=${encodeURIComponent(KEY)}&_=${Date.now()}`,{cache:'no-store'});
     const d=await r.json();if(!d.ok)return;
     for(const e of d.executives||[]){
       const cell=document.querySelector(`.agentCell[data-executive="${CSS.escape(e.executive_key)}"]`)||
         [...document.querySelectorAll('.agentCell')].find(x=>x.querySelector('.agentName')?.textContent.toLowerCase().includes(e.executive_key));
       if(!cell)continue;
       const metric=cell.querySelector('.metric');if(metric)metric.textContent=String(e.active_count??0);
       const task=cell.querySelector('.taskText');if(task)task.innerHTML=`<strong>${esc(e.current_mode||e.status||'ready')}:</strong> ${esc(e.current_action||'Ready for mission')}`;
       const bar=cell.querySelector('.battery b'),pct=cell.querySelector('.meterLine em');
       const p=Math.max(0,Math.min(100,Number(e.progress||0)));
       if(bar)bar.style.width=`${p}%`;if(pct)pct.textContent=`${p}%`;
     }
     const list=document.querySelector('.activityList');
     if(list&&(d.events||[]).length){
       list.innerHTML=d.events.slice(0,9).map(e=>`<a class="activityItem" href="${esc(e.url||'#')}"><span class="aiIcon">⚡</span><span><b>${esc(e.title)}</b><small>${esc(e.executive_key)} · ${esc(e.details)}</small></span><em>${esc(e.status)}</em></a>`).join('');
     }
   }catch(_){}
 }

 function startGoliathMovement(){
   const avatar=document.querySelector('.goliathAvatar');
   if(!avatar)return;
   const office=avatar.closest('.office');
   avatar.style.transition='left 3s linear, bottom 3s linear, transform .4s ease';
   function step(){
     const x=8+Math.random()*62;
     const y=5+Math.random()*14; // stays grounded near planet surface
     const current=parseFloat(avatar.dataset.x||'28');
     const dir=x>=current?1:-1;
     avatar.dataset.x=String(x);
     avatar.style.transform=`translateX(-50%) scaleX(${dir})`;
     avatar.style.left=`${x}%`;
     avatar.style.bottom=`${y}%`;
     const walk=1700+Math.random()*2600;
     avatar.style.transitionDuration=`${walk}ms,${walk}ms,.35s`;
     setTimeout(()=>{
       avatar.classList.add('goliathStopped');
       setTimeout(()=>{avatar.classList.remove('goliathStopped');step()},700+Math.random()*2200);
     },walk);
   }
   step();
 }

 window.GoliathV111={startVoice,stopVoice,sendMessage};
 document.addEventListener('DOMContentLoaded',()=>{
   $('v111ChatForm')?.addEventListener('submit',e=>{e.preventDefault();sendMessage($('v111ChatInput')?.value||'',false)});
   $('v111VoiceButton')?.addEventListener('click',startVoice);
   $('v111StopVoice')?.addEventListener('click',stopVoice);
   document.addEventListener('pointerdown',unlockAudio,{once:true});
   window.addEventListener('focus',()=>schedule(50));
   document.addEventListener('visibilitychange',()=>{if(!document.hidden)schedule(50)});
   updateFeed();setInterval(updateFeed,5000);schedule(100);startGoliathMovement();
 });
})();