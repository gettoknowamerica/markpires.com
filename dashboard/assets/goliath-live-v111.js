(() => {
  const KEY = window.GOLIATH_V111_KEY || '';
  const API = '/lead-engine/';
  const state = {
    conversationUid: localStorage.getItem('goliath_v111_conversation') || '',
    lastMessageId: Number(localStorage.getItem('goliath_v111_last_id') || 0),
    recognition: null,
    voiceActive: localStorage.getItem('goliath_voice_enabled') === '1',
    speaking: false,
    pollInFlight: false,
    waitingForReply: false,
    activeAudio: null,
    audioUnlocked: false,
    lastSpokenText: '',
    replyStartedAt: 0,
    pollFailures: 0,
    disposed: false
  };

  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

  function addBubble(name,text,cls,id=''){
    const box=$('v111Conversation');
    if(!box||!text)return;
    if(id&&document.querySelector(`[data-message-id="${id}"]`))return;
    const div=document.createElement('div');
    div.className=`v111Bubble ${cls}`;
    if(id)div.dataset.messageId=id;
    div.innerHTML=`<b>${esc(name)}</b><span>${esc(text)}</span>`;
    box.appendChild(div);
    box.scrollTop=box.scrollHeight;
  }

  function setConnection(text,good=false){
    const el=$('v111Connection');
    if(!el)return;
    el.textContent=text;
    el.classList.toggle('online',good);
  }
  function setVoiceState(text,active=false){
    const el=$('v111VoiceState'); if(el)el.textContent=text;
    const btn=$('v111VoiceButton'); if(btn)btn.classList.toggle('active',active);
  }

  function getAudio(){
    let audio=$('v111AudioPlayer');
    if(!audio){
      audio=document.createElement('audio');
      audio.id='v111AudioPlayer';
      audio.setAttribute('playsinline','');
      audio.preload='auto';
      document.body.appendChild(audio);
    }
    return audio;
  }

  async function unlockAudio(){
    if(state.audioUnlocked)return true;
    const audio=getAudio();
    try{
      audio.src='data:audio/mp3;base64,//uQZAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAEAAACcQCA';
      audio.muted=true;
      await audio.play();
      audio.pause();
      audio.currentTime=0;
      audio.muted=false;
      state.audioUnlocked=true;
      return true;
    }catch(_){
      return false;
    }
  }

  function stopSpeech(){
    const audio=getAudio();
    try{audio.pause();audio.currentTime=0;}catch(_){}
    if('speechSynthesis' in window)speechSynthesis.cancel();
    state.activeAudio=null;
    state.speaking=false;
  }

  function restartRecognition(){
    if(!state.voiceActive||!state.recognition||state.speaking)return;
    try{state.recognition.start();}catch(_){}
  }

  function afterSpeech(){
    state.speaking=false;
    state.activeAudio=null;
    if(state.voiceActive){
      setVoiceState('Listening…',true);
      setTimeout(restartRecognition,180);
    }
  }

  async function speak(text,audioUrl=''){
    if(!text)return;
    state.lastSpokenText=String(text).toLowerCase().replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
    stopSpeech();

    if(audioUrl){
      const audio=getAudio();
      state.activeAudio=audio;
      audio.onplay=()=>{
        state.speaking=true;
        setVoiceState('Goliath is speaking — talk to interrupt.',true);
      };
      audio.onended=afterSpeech;
      audio.onerror=()=>browserVoice(text);
      audio.src=audioUrl + (audioUrl.includes('?')?'&':'?') + 'v=' + Date.now();
      try{
        await audio.play();
        return;
      }catch(_){
        browserVoice(text);
        return;
      }
    }
    browserVoice(text);
  }

  function browserVoice(text){
    if(!('speechSynthesis' in window)){afterSpeech();return;}
    const u=new SpeechSynthesisUtterance(text);
    u.rate=.92;u.pitch=.86;u.volume=1;
    u.onstart=()=>{
      state.speaking=true;
      setVoiceState('Goliath is speaking — talk to interrupt.',true);
    };
    u.onend=afterSpeech;u.onerror=afterSpeech;
    speechSynthesis.speak(u);
  }

  async function sendMessage(message,voice=false){
    const text=String(message||'').trim();
    if(!text)return;
    addBubble('Mark',text,'mark');
    const input=$('v111ChatInput');if(input)input.value='';
    setConnection('THINKING',true);
    state.waitingForReply=true;
    state.replyStartedAt=Date.now();

    try{
      const res=await fetch(API+'ask-goliath-live-v111.php',{
        method:'POST',
        cache:'no-store',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({
          key:KEY,message:text,conversation_uid:state.conversationUid,
          voice:voice||state.voiceActive,tts_requested:voice||state.voiceActive
        })
      });
      const raw=await res.text();
      let data;try{data=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,260)||`HTTP ${res.status}`)}
      if(!res.ok||!data.ok)throw new Error(data.details?.message||data.error||'Ask Goliath failed');
      state.conversationUid=data.conversation_uid;
      localStorage.setItem('goliath_v111_conversation',state.conversationUid);
      queuePoll(100);
    }catch(err){
      state.waitingForReply=false;
      addBubble('System',err.message,'error');
      setConnection('ERROR');
    }
  }

  function queuePoll(ms=500){
    if(state.disposed)return;
    clearTimeout(state.pollTimer);
    state.pollTimer=setTimeout(pollConversation,ms);
  }

  async function pollConversation(){
    if(!state.conversationUid||state.pollInFlight||state.disposed){
      queuePoll(state.waitingForReply?350:1200); return;
    }
    state.pollInFlight=true;
    try{
      const url=`${API}ask-goliath-result-v111.php?key=${encodeURIComponent(KEY)}&conversation_uid=${encodeURIComponent(state.conversationUid)}&after_id=${state.lastMessageId}&_=${Date.now()}`;
      const res=await fetch(url,{cache:'no-store',headers:{'Accept':'application/json'}});
      const raw=await res.text();
      let data;try{data=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,260)||`HTTP ${res.status}`)}
      if(!res.ok||!data.ok)throw new Error(data.details?.message||data.error||'poll failed');

      let reply=false;
      for(const m of data.messages||[]){
        if(m.message_type==='pending'||!m.message_text)continue;
        const id=Number(m.id||0);
        state.lastMessageId=Math.max(state.lastMessageId,id);
        localStorage.setItem('goliath_v111_last_id',String(state.lastMessageId));
        if(m.speaker_key==='mark')continue;
        const cls=m.message_type==='error'?'error':'goliath';
        addBubble(m.speaker_name||'Goliath',m.message_text,cls,id);
        if(m.speaker_key==='goliath'&&m.message_type!=='error'){
          reply=true;state.waitingForReply=false;
          await speak(m.message_text,m.audio_url||'');
        }
      }
      state.pollFailures=0;
      setConnection(state.waitingForReply?'THINKING':'LIVE',true);
      if(state.waitingForReply&&Date.now()-state.replyStartedAt>45000)setConnection('STILL THINKING',true);
      queuePoll(state.waitingForReply&&!reply?300:900);
    }catch(_){
      state.pollFailures++;
      setConnection('RECONNECTING');
      queuePoll(state.waitingForReply?700:1500);
    }finally{
      state.pollInFlight=false;
    }
  }

  function startVoice(){
    const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){setVoiceState('Voice recognition requires supported Chrome, Edge, or Safari.');return;}
    unlockAudio();
    stopSpeech();

    if(!state.recognition){
      const rec=new SR();
      rec.continuous=true;
      rec.interimResults=true;
      rec.lang='en-US';
      rec.onstart=()=>setVoiceState('Listening…',true);
      rec.onspeechstart=()=>{
        if(state.speaking)stopSpeech();
        setVoiceState('I hear you…',true);
      };
      rec.onresult=e=>{
        let finalText='',interim='';
        for(let i=e.resultIndex;i<e.results.length;i++){
          const t=e.results[i][0].transcript;
          if(e.results[i].isFinal)finalText+=t;else interim+=t;
        }
        if(interim)setVoiceState(`Hearing: ${interim}`,true);
        if(finalText.trim()){
          const heard=finalText.trim();
          const normalized=heard.toLowerCase().replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
          if(state.speaking&&state.lastSpokenText&&
            (state.lastSpokenText.includes(normalized)||normalized.includes(state.lastSpokenText.slice(0,60))))return;
          const cleaned=heard.replace(/^\s*hey\s+goliath[\s,:-]*/i,'').trim();
          sendMessage(cleaned||'Hey Goliath. Can you hear me?',true);
        }
      };
      rec.onerror=e=>{
        if(e.error==='not-allowed')setVoiceState('Microphone permission was denied. Allow it in browser settings.');
        else setVoiceState(`Voice: ${e.error||'error'}`);
      };
      rec.onend=()=>{
        if(state.voiceActive&&!state.speaking)setTimeout(restartRecognition,250);
      };
      state.recognition=rec;
    }

    state.voiceActive=true;
    localStorage.setItem('goliath_voice_enabled','1');
    restartRecognition();
  }

  function stopVoice(){
    state.voiceActive=false;
    localStorage.setItem('goliath_voice_enabled','0');
    stopSpeech();
    if(state.recognition)try{state.recognition.stop()}catch(_){}
    setVoiceState('Voice stopped.');
  }

  async function updateFeed(){
    try{
      const res=await fetch(`${API}goliath-live-feed-v111.php?key=${encodeURIComponent(KEY)}&_=${Date.now()}`,{cache:'no-store'});
      const data=await res.json();
      if(!data.ok)return;
      for(const e of data.executives||[]){
        const cell=[...document.querySelectorAll('.agentCell')].find(x=>
          x.dataset.executive===e.executive_key ||
          x.querySelector('.agentName')?.textContent.toLowerCase().includes(e.executive_key)
        );
        if(!cell)continue;
        const task=cell.querySelector('.taskText');
        if(task)task.innerHTML=`<strong>${esc(e.current_mode||e.status||'ready')}:</strong> ${esc(e.current_action||'Ready for mission')}`;
        const metric=cell.querySelector('.metric');
        if(metric)metric.textContent=String(e.active_count??e.total_count??0);
        const battery=cell.querySelector('.battery b');
        const em=cell.querySelector('.meterLine em');
        const pct=Math.max(0,Math.min(100,Number(e.progress||0)));
        if(battery)battery.style.width=`${pct}%`;
        if(em)em.textContent=`${pct}%`;
      }
      const list=document.querySelector('.activityList');
      if(list&&(data.events||[]).length){
        list.innerHTML=(data.events||[]).slice(0,9).map(ev=>`
          <a class="activityItem" href="${esc(ev.url||'#')}">
            <span class="aiIcon">${esc(ev.icon||'⚡')}</span>
            <span><b>${esc(ev.title||'Executive update')}</b><small>${esc(ev.executive_key||'Goliath')} · ${esc(ev.details||'')}</small></span>
            <em>${esc(ev.status||'LIVE')}</em>
          </a>`).join('');
      }
    }catch(_){}
  }

  window.GoliathV111={startVoice,stopVoice,sendMessage};

  document.addEventListener('DOMContentLoaded',()=>{
    $('v111ChatForm')?.addEventListener('submit',e=>{e.preventDefault();sendMessage($('v111ChatInput')?.value||'',false)});
    $('v111VoiceButton')?.addEventListener('click',startVoice);
    $('v111StopVoice')?.addEventListener('click',stopVoice);
    document.addEventListener('pointerdown',unlockAudio,{once:true});
    window.addEventListener('focus',()=>queuePoll(50));
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)queuePoll(50)});
    updateFeed();
    setInterval(updateFeed,5000);
    queuePoll(100);
    if(state.voiceActive)setVoiceState('Tap Start Live Voice to resume microphone.',false);
  });

  window.addEventListener('beforeunload',()=>{state.disposed=true;clearTimeout(state.pollTimer)});
})();