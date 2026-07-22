(function(){
  let recognition=null,listening=false,autoListen=false,conversationId='ask_'+Date.now(),silenceTimer=null,interimText='';
  function ensureAsk(){
    if(document.getElementById('gAskPanel'))return;
    const back=document.createElement('div');back.className='gAskBackdrop';back.id='gAskBackdrop';back.onclick=closeAskGoliath;
    const panel=document.createElement('div');panel.className='gAskPanel gAskConversation';panel.id='gAskPanel';
    panel.innerHTML=`<div class="gAskHead"><div><h2>Ask Goliath</h2><small>Free-form executive conversation. Talk normally; Goliath answers first, then routes work only when useful.</small></div><button class="gAskClose" onclick="closeAskGoliath()">Close</button></div><div class="gAskBody" id="gAskBody"><div class="gMsg goliath"><strong>Goliath:</strong><br>I'm here, Mark. This is conversation mode. Type and press Enter, or turn on Always Listening and say “Hey Goliath...”</div></div><div class="gAskFoot"><textarea id="gAskInput" class="gAskInput" placeholder="Talk to Goliath naturally. Press Enter to send. Shift+Enter makes a new line."></textarea><div class="gAskActions"><button class="gAskPrimary" onclick="sendAskGoliath()">Send</button><button class="gAskVoice" onclick="toggleGoliathVoice()">🎤 Always Listening</button><button class="gAskMemory" onclick="routeAskToAgent()">Route Only</button></div><div class="gAskStatus" id="gAskStatus">Mic requires one browser permission click. After that, say “Hey Goliath” and keep talking.</div></div>`;
    document.body.appendChild(back);document.body.appendChild(panel);
    const input=document.getElementById('gAskInput');
    input.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendAskGoliath();}});
  }
  window.openAskGoliath=function(){ensureAsk();document.getElementById('gAskBackdrop').classList.add('open');document.getElementById('gAskPanel').classList.add('open');setTimeout(()=>document.getElementById('gAskInput')?.focus(),100)};
  window.closeAskGoliath=function(){document.getElementById('gAskBackdrop')?.classList.remove('open');document.getElementById('gAskPanel')?.classList.remove('open')};
  function addMsg(type,html){const body=document.getElementById('gAskBody');const msg=document.createElement('div');msg.className='gMsg '+type;msg.innerHTML=html;body.appendChild(msg);body.scrollTop=body.scrollHeight;return msg;}
  function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function renderTrace(trace){if(!Array.isArray(trace)||!trace.length)return '';return '<details class="gTrace"><summary>Visible Goliath process</summary>'+trace.map(t=>'<div><b>'+esc(t.label)+':</b> '+esc(t.detail)+'</div>').join('')+'</details>';}
  window.sendAskGoliath=async function(textOverride){
    ensureAsk();const input=document.getElementById('gAskInput');const text=(textOverride||input.value||'').trim();if(!text)return;
    input.value='';addMsg('user',`<strong>You:</strong><br>${esc(text)}`);const thinking=addMsg('goliath','<strong>Goliath:</strong><br>Thinking in executive mode...');
    try{const r=await fetch('/lead-engine/ask-goliath.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:text,prompt:text,mode:'freeform',source:'mission_control',conversation_id:conversationId})});const j=await r.json();
      const answer=j.answer||j.response||'I answered, but no response text came back.';
      thinking.innerHTML='<strong>Goliath:</strong><br>'+esc(answer).replace(/\n/g,'<br>')+renderTrace(j.trace);
      if(j.routed){addMsg('goliath','<strong>Routed:</strong><br>'+esc(j.routed));}
    }catch(e){thinking.innerHTML='<strong>Goliath:</strong><br>Connection issue. The browser reached Mission Control, but /lead-engine/ask-goliath.php did not answer.';}
  };
  window.routeAskToAgent=async function(){const text=(document.getElementById('gAskInput')?.value||'').trim();if(!text){addMsg('goliath','<strong>Goliath:</strong><br>Type the agent command first.');return;}const agent=(text.match(/\b(Scout|Jessica|Scorsese|Shakespeare|Einstein|Rockefeller|Prospector|Columbo)\b/i)||[])[1]||'Rockefeller';const r=await fetch('/lead-engine/goliath-agent-command.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({agent:agent[0].toUpperCase()+agent.slice(1).toLowerCase(),command:text,source:'ask_goliath_route_only'})});const j=await r.json();addMsg('goliath','<strong>Goliath:</strong><br>'+(j.success?'Command routed to '+agent+'.':(j.error||'Route failed.')));};
  window.toggleGoliathVoice=function(){ensureAsk();const SR=window.SpeechRecognition||window.webkitSpeechRecognition;const status=document.getElementById('gAskStatus');if(!SR){status.innerHTML='Voice is not supported here. Use Chrome or Edge for microphone conversation mode.';return;}autoListen=!autoListen;if(listening&&recognition){recognition.stop();if(!autoListen)status.innerHTML='Always Listening off.';return;}
    recognition=new SR();recognition.continuous=true;recognition.interimResults=true;recognition.lang='en-US';listening=true;interimText='';status.innerHTML='<span class="gAskListening">Always Listening...</span> Say “Hey Goliath” and then speak normally.';
    recognition.onresult=function(event){let finalText='';for(let i=event.resultIndex;i<event.results.length;i++){const chunk=event.results[i][0].transcript;if(event.results[i].isFinal)finalText+=chunk;else interimText=chunk;}if(interimText)status.innerHTML='<span class="gAskListening">Listening:</span> '+esc(interimText);if(finalText){let heard=finalText.trim();if(/hey goliath|okay goliath|ask goliath/i.test(heard)){heard=heard.replace(/hey goliath|okay goliath|ask goliath/ig,'').trim();if(heard){clearTimeout(silenceTimer);silenceTimer=setTimeout(()=>sendAskGoliath(heard),350);}}}};
    recognition.onerror=function(e){status.innerHTML='Mic issue: '+esc(e.error||'permission/network problem')+'. Click Always Listening again after allowing microphone access.';};
    recognition.onend=function(){listening=false;if(autoListen){try{recognition.start();listening=true;}catch(e){status.innerHTML='Listening paused. Click Always Listening again.';}}else status.innerHTML='Always Listening off.';};
    try{recognition.start();}catch(e){status.innerHTML='Could not start microphone. Check browser permission.';}
  };
  document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.ask,[data-open-ask-goliath]').forEach(btn=>{btn.onclick=e=>{e.preventDefault();openAskGoliath();};});});
})();
