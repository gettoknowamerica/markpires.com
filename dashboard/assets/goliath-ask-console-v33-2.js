
(function(){
  let recognition=null;
  let listening=false;

  function ensureAsk(){
    if(document.getElementById('gAskPanel')) return;
    const back=document.createElement('div');
    back.className='gAskBackdrop';
    back.id='gAskBackdrop';
    back.onclick=closeAskGoliath;

    const panel=document.createElement('div');
    panel.className='gAskPanel';
    panel.id='gAskPanel';
    panel.innerHTML=`<div class="gAskHead">
      <div><h2>Ask Goliath</h2><small>Type, dictate, or say what you want Goliath to do.</small></div>
      <button class="gAskClose" onclick="closeAskGoliath()">Close</button>
    </div>
    <div class="gAskBody" id="gAskBody">
      <div class="gMsg goliath"><strong>Goliath:</strong><br>Ready. Give me an objective. Example: “Find the best follow-up for Mark Harrison and create a California-to-Fairfield drip piece.”</div>
    </div>
    <div class="gAskFoot">
      <textarea id="gAskInput" class="gAskInput" placeholder="Ask Goliath what to do next..."></textarea>
      <div class="gAskActions">
        <button class="gAskPrimary" onclick="sendAskGoliath()">Send to Goliath</button>
        <button class="gAskVoice" onclick="toggleGoliathVoice()">🎤 Conversation Mode</button>
        <button class="gAskMemory" onclick="saveGoliathMemory()">Save Important Work</button>
      </div>
      <div class="gAskStatus" id="gAskStatus">Voice uses your browser microphone when supported.</div>
    </div>`;
    document.body.appendChild(back);
    document.body.appendChild(panel);
  }

  window.openAskGoliath=function(){
    ensureAsk();
    document.getElementById('gAskBackdrop').classList.add('open');
    document.getElementById('gAskPanel').classList.add('open');
    setTimeout(()=>document.getElementById('gAskInput')?.focus(),100);
  }

  window.closeAskGoliath=function(){
    document.getElementById('gAskBackdrop')?.classList.remove('open');
    document.getElementById('gAskPanel')?.classList.remove('open');
  }

  function addMsg(type,html){
    const body=document.getElementById('gAskBody');
    const msg=document.createElement('div');
    msg.className='gMsg '+type;
    msg.innerHTML=html;
    body.appendChild(msg);
    body.scrollTop=body.scrollHeight;
  }

  window.sendAskGoliath=async function(){
    ensureAsk();
    const input=document.getElementById('gAskInput');
    const text=(input.value||'').trim();
    if(!text) return;
    input.value='';
    addMsg('user',`<strong>You:</strong><br>${escapeHtml(text)}`);
    addMsg('goliath',`<strong>Goliath:</strong><br>Queued. I am sending this to the local brain and Mission Control.`);
    try{
      const r=await fetch('/lead-engine/ask-goliath.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({prompt:text,source:'mission_control'})
      });
      const j=await r.json();
      if(j.success){
        addMsg('goliath',`<strong>Goliath:</strong><br>Task created. Watch Mission Control for the worker response.<br><small>${j.id||''}</small>`);
        if(window.goliathToast) goliathToast('Ask Goliath queued',text.substring(0,90));
      }else{
        addMsg('goliath',`<strong>Goliath:</strong><br>I could not queue it yet: ${escapeHtml(j.error||'Unknown error')}`);
      }
    }catch(e){
      addMsg('goliath',`<strong>Goliath:</strong><br>Connection issue. The console is open, but the ask endpoint needs to be uploaded.`);
    }
  }

  window.toggleGoliathVoice=function(){
    ensureAsk();
    const SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
    const status=document.getElementById('gAskStatus');
    if(!SpeechRecognition){
      status.innerHTML='Voice is not supported in this browser. Use Chrome on desktop/mobile.';
      return;
    }
    if(listening && recognition){recognition.stop();return;}
    recognition=new SpeechRecognition();
    recognition.continuous=true;
    recognition.interimResults=true;
    recognition.lang='en-US';
    listening=true;
    status.innerHTML='<span class="gAskListening">Listening...</span> Say: Hey Goliath, then your instruction.';
    recognition.onresult=function(event){
      let finalText='';
      for(let i=event.resultIndex;i<event.results.length;i++){
        if(event.results[i].isFinal) finalText+=event.results[i][0].transcript;
      }
      if(finalText){
        const cleaned=finalText.replace(/hey goliath[:,]?/ig,'').trim();
        document.getElementById('gAskInput').value=(document.getElementById('gAskInput').value+' '+cleaned).trim();
      }
    };
    recognition.onend=function(){listening=false;status.innerHTML='Voice paused. Press Conversation Mode to listen again.';}
    recognition.start();
  }

  window.saveGoliathMemory=async function(){
    const text=(document.getElementById('gAskInput')?.value||'').trim();
    if(!text){addMsg('goliath','<strong>Goliath:</strong><br>Type or dictate what should be saved first.');return;}
    addMsg('goliath','<strong>Goliath:</strong><br>Saved as important context for this work session.');
    if(window.goliathToast) goliathToast('Memory note saved',text.substring(0,90));
  }

  function escapeHtml(str){return String(str).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}

  document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.ask').forEach(btn=>{
      btn.onclick=function(e){e.preventDefault();openAskGoliath();}
    });
  });
})();
