(function(){
  function markWorking(){
    const summaries = window.GOLIATH_AGENT_SUMMARIES || {};
    document.querySelectorAll('[data-agent-tile]').forEach(tile=>{
      const agent = tile.getAttribute('data-agent-tile');
      const data = summaries[agent] || {};
      const tasks = Number(data.tasks || 0);
      const events = Number(data.count || 0);
      tile.classList.toggle('is-working', tasks > 0 || events > 0);
      const bubble = tile.querySelector('.agentWorkBubble');
      if(bubble){
        let verb = 'standing by';
        if(agent==='Scout') verb='finding contacts';
        if(agent==='Jessica') verb='email queue';
        if(agent==='Scorsese') verb='editing';
        if(agent==='Shakespeare') verb='writing';
        if(agent==='Einstein') verb='analyzing';
        if(agent==='Rockefeller') verb='prioritizing';
        if(agent==='Prospector') verb='mining leads';
        if(agent==='Columbo') verb='mining gold';
        bubble.innerHTML = '<b>'+(tasks||events||0)+'</b> '+verb;
      }
    });
  }
  document.addEventListener('DOMContentLoaded', markWorking);
  setInterval(markWorking, 12000);
})();
