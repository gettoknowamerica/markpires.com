/* V44.1 Ask Goliath Conversation Layer
   Immediate answer, background delegation, browser speech fallback, barge-in behavior.
*/
(function(){
  const KEY = window.GOLIATH_KEY || window.AFTER_HOURS_CRON_KEY || '';
  let conversationId = 'ask_' + Date.now();
  let recognizing=false;
  let recognition=null;
  let continuousMode=false;
  let speaking=false;

  function qs(sel){return document.querySelector(sel)}
  function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}

  function findChatBox(){
    return qs('#askGoliathMessages') || qs('#goliathAskMessages') || qs('.askGoliathMessages') || qs('.ask-messages');
  }
  function findInput(){
    return qs('#askGoliathInput') || qs('#askText') || qs('#askPrompt') || qs('textarea[placeholder*="Goliath"]') || qs('textarea');
  }

  function addMsg(who,text){
    const box=findChatBox();
    if(box){
      const card=document.createElement('div');
      card.className='v441Msg '+(who==='You'?'you':'goliath');
      card.innerHTML='<strong>'+esc(who)+':</strong><div>'+esc(text)+'</div>';
      box.appendChild(card);
      box.scrollTop=box.scrollHeight;
    }else{
      console.log(who+': '+text);
    }
  }

  function stopSpeaking(){
    if('speechSynthesis' in window){
      window.speechSynthesis.cancel();
    }
    speaking=false;
  }

  function speak(text){
    stopSpeaking();
    if(!text || !('speechSynthesis' in window)) return;
    const u=new SpeechSynthesisUtterance(text);
    u.rate=.96; u.pitch=.86; u.volume=1;
    u.onstart=()=>{speaking=true};
    u.onend=()=>{speaking=false;if(continuousMode) setTimeout(startListening,300)};
    window.speechSynthesis.speak(u);
  }

  async function sendAsk(message, voice=false){
    message=(message||'').trim();
    if(!message)return;
    stopSpeaking();
    addMsg('You',message);
    const input=findInput(); if(input) input.value='';
    try{
      const r=await fetch('/lead-engine/ask-goliath-chat.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({key:KEY,message,conversation_id:conversationId})
      });
      const j=await r.json();
      if(j.conversation_id) conversationId=j.conversation_id;
      addMsg('Goliath',j.response || 'I heard you, Mark.');
      if(voice) speak(j.response || 'I heard you, Mark.');
      return j;
    }catch(e){
      addMsg('Goliath','I heard you, but the conversation endpoint did not respond. Check ask-goliath-chat.php.');
    }
  }

  function setupSpeech(){
    const SR=window.SpeechRecognition || window.webkitSpeechRecognition;
    if(!SR) return false;
    recognition=new SR();
    recognition.lang='en-US';
    recognition.continuous=false;
    recognition.interimResults=true;

    let finalText='';
    recognition.onstart=()=>{recognizing=true; document.body.classList.add('goliathListening'); stopSpeaking();};
    recognition.onend=()=>{recognizing=false; document.body.classList.remove('goliathListening'); if(continuousMode && !speaking) setTimeout(startListening,650);};
    recognition.onerror=()=>{recognizing=false; document.body.classList.remove('goliathListening');};
    recognition.onresult=(ev)=>{
      stopSpeaking(); // barge-in: if Mark speaks while Goliath speaks, Goliath shuts up
      let interim='';
      for(let i=ev.resultIndex;i<ev.results.length;i++){
        const txt=ev.results[i][0].transcript;
        if(ev.results[i].isFinal) finalText+=txt;
        else interim+=txt;
      }
      const input=findInput();
      if(input) input.value=(finalText || interim).trim();
      if(finalText.trim()){
        const msg=finalText.trim();
        finalText='';
        try{recognition.stop()}catch(e){}
        sendAsk(msg,true);
      }
    };
    return true;
  }

  function startListening(){
    if(!recognition && !setupSpeech()){
      addMsg('Goliath','Browser speech recognition is not available here yet. Kokoro/OpenClaw will become the always-listening layer.');
      return;
    }
    if(recognizing) return;
    try{recognition.start()}catch(e){}
  }

  function toggleConversation(){
    continuousMode=!continuousMode;
    if(continuousMode){
      addMsg('Goliath','Conversation mode is on. Speak naturally. If you interrupt me, I will stop and listen.');
      startListening();
    }else{
      try{recognition && recognition.stop()}catch(e){}
      stopSpeaking();
      addMsg('Goliath','Conversation mode is off.');
    }
  }

  function wire(){
    document.addEventListener('click',function(e){
      const send=e.target.closest('#askGoliathSend,[data-ask-send],.askGoliathSend');
      if(send){
        e.preventDefault();
        const input=findInput();
        sendAsk(input?input.value:'',false);
      }
      const conv=e.target.closest('#askGoliathConversation,[data-conversation-mode],.conversationMode');
      if(conv){
        e.preventDefault();
        toggleConversation();
      }
      const mic=e.target.closest('#askGoliathMic,[data-ask-mic],.askGoliathMic');
      if(mic){
        e.preventDefault();
        startListening();
      }
    },true);

    document.addEventListener('keydown',function(e){
      const input=findInput();
      if(!input || document.activeElement!==input) return;
      if(e.key==='Enter' && !e.shiftKey){
        e.preventDefault();
        sendAsk(input.value,false);
      }
    });
  }

  function injectStyles(){
    if(qs('#v441AskStyles')) return;
    const s=document.createElement('style');
    s.id='v441AskStyles';
    s.textContent=`
      .v441Msg{border:1px solid #263753;border-radius:14px;padding:10px;margin:8px 0;background:#0f172a;color:#dbeafe;font-weight:700}
      .v441Msg.you{background:#10295c;border-color:#2855ad}
      .v441Msg.goliath{background:#101827;border-color:#c8a96e55}
      .v441Msg strong{display:block;color:#c8a96e;margin-bottom:4px}
      body.goliathListening #askGoliathConversation,
      body.goliathListening [data-conversation-mode],
      body.goliathListening .conversationMode{box-shadow:0 0 0 3px rgba(34,197,94,.28),0 0 22px rgba(34,197,94,.65)!important}
    `;
    document.head.appendChild(s);
  }

  window.GoliathAskV441={send:sendAsk,startListening,toggleConversation,stopSpeaking};
  document.addEventListener('DOMContentLoaded',()=>{injectStyles();wire();});
})();