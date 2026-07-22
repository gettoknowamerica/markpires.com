/* V35.6 Goliath Voice Command Center */
(function(){
  let recognition=null;
  let listening=false;
  let wakeMode=false;

  function ensureVoicePanel(){
    if(document.getElementById('gVoicePanel')) return;
    const back=document.createElement('div');
    back.id='gVoiceBackdrop';
    back.className='gVoiceBackdrop';
    back.onclick=closeGoliathVoice;

    const panel=document.createElement('div');
    panel.id='gVoicePanel';
    panel.className='gVoicePanel';
    panel.innerHTML=`<div class="gVoiceHead">
      <div><h2>Hey Goliath</h2><small>Voice command center for Rockefeller, Jessica, Einstein, Scout, Scorsese, and Shakespeare.</small></div>
      <button class="g-btn g-btn-dark" onclick="closeGoliathVoice()">Close</button>
    </div>
    <div class="gVoiceBody">
      <div id="gVoiceOrb" class="gVoiceOrb">G</div>
      <div id="gVoiceStatus" class="gVoiceStatus">Ready. Press Start Listening and say “Hey Goliath…”</div>
      <textarea id="gVoiceTranscript" class="gVoiceTranscript" placeholder="Transcript appears here..."></textarea>
      <div class="gVoiceActions">
        <button class="g-btn g-btn-green" onclick="startGoliathListening()">🎤 Start Listening</button>
        <button class="g-btn g-btn-red" onclick="stopGoliathListening()">■ Stop</button>
        <button class="g-btn g-btn-gold" onclick="sendGoliathVoiceCommand()">⚡ Send Command</button>
        <button class="g-btn g-btn-blue" onclick="speakGoliathBriefing()">🔊 Brief Me</button>
      </div>
      <div class="gVoiceHint">
        Try: “Hey Goliath, start a seller drip for Mark Harrison,” or “Hey Goliath, create a video about modern homes in Fairfield.”
      </div>
    </div>`;
    document.body.appendChild(back);
    document.body.appendChild(panel);
  }

  window.openGoliathVoice=function(){
    ensureVoicePanel();
    document.getElementById('gVoiceBackdrop').classList.add('open');
    document.getElementById('gVoicePanel').classList.add('open');
  }

  window.closeGoliathVoice=function(){
    stopGoliathListening();
    document.getElementById('gVoiceBackdrop')?.classList.remove('open');
    document.getElementById('gVoicePanel')?.classList.remove('open');
  }

  function setStatus(txt,active=false){
    const s=document.getElementById('gVoiceStatus');
    const orb=document.getElementById('gVoiceOrb');
    if(s) s.textContent=txt;
    if(orb) orb.classList.toggle('active',active);
  }

  window.startGoliathListening=function(){
    ensureVoicePanel();
    const SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SpeechRecognition){
      setStatus('Voice recognition is not supported here. Use Chrome on desktop or Android. iPhone Safari may limit this.');
      return;
    }
    if(listening) return;

    recognition=new SpeechRecognition();
    recognition.continuous=true;
    recognition.interimResults=true;
    recognition.lang='en-US';
    listening=true;
    wakeMode=true;
    setStatus('Listening for “Hey Goliath”…',true);

    recognition.onresult=function(event){
      let interim='', finalText='';
      for(let i=event.resultIndex;i<event.results.length;i++){
        const t=event.results[i][0].transcript;
        if(event.results[i].isFinal) finalText+=t;
        else interim+=t;
      }
      const box=document.getElementById('gVoiceTranscript');
      if(interim) setStatus('Hearing: '+interim,true);
      if(finalText){
        let current=(box.value||'').trim();
        box.value=(current+' '+finalText).trim();
        const lower=finalText.toLowerCase();
        if(lower.includes('hey goliath')){
          const cleaned=finalText.replace(/hey goliath[:,]?/ig,'').trim();
          box.value=(current+' '+cleaned).trim();
          setStatus('Command captured. Press Send Command or keep talking.',true);
          speak('I heard you, Mark.');
        }
      }
    };
    recognition.onerror=function(e){setStatus('Voice error: '+(e.error||'unknown'));}
    recognition.onend=function(){
      listening=false;
      if(wakeMode){
        setTimeout(()=>{ if(wakeMode) startGoliathListening(); },650);
      }else{
        setStatus('Voice stopped.');
      }
    };
    recognition.start();
  }

  window.stopGoliathListening=function(){
    wakeMode=false;
    if(recognition){
      try{recognition.stop();}catch(e){}
    }
    listening=false;
    setStatus('Voice stopped.');
  }

  window.sendGoliathVoiceCommand=async function(){
    const box=document.getElementById('gVoiceTranscript');
    const text=(box?.value||'').trim();
    if(!text){setStatus('No command captured yet.');return;}
    setStatus('Sending command to Goliath...',true);
    let dept='Rockefeller', type='voice_command';
    const t=text.toLowerCase();
    if(t.includes('video')||t.includes('reel')||t.includes('graphic')||t.includes('thumbnail')){dept='Scorsese';type=t.includes('graphic')||t.includes('thumbnail')?'director_image':'director_video';}
    else if(t.includes('drip')||t.includes('follow')||t.includes('call')||t.includes('text')){dept='Jessica';type='full_followup_drip';}
    else if(t.includes('search')||t.includes('number')||t.includes('owner')){dept='Scout';type='scout_search';}
    else if(t.includes('stat')||t.includes('market')||t.includes('mls')){dept='Einstein';type='market_analysis';}
    else if(t.includes('publish')||t.includes('post')||t.includes('blog')){dept='Shakespeare';type='publisher_schedule';}

    try{
      const r=await fetch('/lead-engine/goliath-event-bus.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          key:'timetomakethedonuts',
          action:'command',
          command_type:type,
          department:dept,
          title:'Voice command for '+dept,
          prompt:text,
          priority:130,
          source:'voice_command_center',
          roi_estimate:10000,
          metadata:{voice:true,transcript:text}
        })
      });
      const j=await r.json();
      if(j.success){
        setStatus(dept+' accepted the mission.');
        speak(dept+' accepted the mission.');
        if(window.gToast) gToast('Voice command queued',dept+' is taking ownership.');
        box.value='';
      }else{
        setStatus('Command was not queued.');
        speak('I could not queue that command yet.');
      }
    }catch(e){
      setStatus('Event bus connection issue.');
      speak('I could not reach the event bus.');
    }
  }

  window.speakGoliathBriefing=function(){
    const msg='Good morning, Mark. Rockefeller here. Goliath is online. Jessica is ready for follow up. Einstein is ready for market analysis. Scout is ready to research. Scorsese is ready to create. Shakespeare is ready to publish. Tell me the highest value mission.';
    speak(msg);
    setStatus(msg);
  }

  function speak(text){
    if(!('speechSynthesis' in window)) return;
    const u=new SpeechSynthesisUtterance(text);
    u.rate=.9; u.pitch=.82; u.volume=1;
    speechSynthesis.cancel();
    speechSynthesis.speak(u);
  }

  document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.ask,.askGlobal').forEach(btn=>{
      btn.addEventListener('contextmenu',e=>{e.preventDefault();openGoliathVoice();});
    });
  });
})();
