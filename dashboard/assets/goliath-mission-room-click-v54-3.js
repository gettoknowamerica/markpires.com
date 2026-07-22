(function(){
  function officeUrl(agent){ if((agent||'').toLowerCase()==='scorsese') return '/dashboard/goliath-studio.php'; return '/dashboard/goliath-agent-detail.php?department='+encodeURIComponent(agent||'Goliath');}
  function go(agent){ if(agent) window.location.href=officeUrl(agent); }

  // Capture before older inline onclick handlers can block navigation.
  document.addEventListener('click', function(e){
    var room=e.target.closest && e.target.closest('a.room,[data-agent-tile],.agentTile');
    if(!room) return;
    var agent = room.getAttribute('data-dept') || room.getAttribute('data-agent-tile') || (room.closest('[data-agent-tile]')||{}).dataset?.agentTile;
    if(!agent) return;
    e.preventDefault();
    e.stopPropagation();
    go(agent);
  }, true);

  // KPI cards should also open each Executive office, including Scorsese.
  document.addEventListener('click', function(e){
    var k=e.target.closest && e.target.closest('.gTenKpis .kpi');
    if(!k) return;
    var name=(k.querySelector('strong')||{}).textContent || '';
    name=name.trim();
    var known=['Jessica','Scout','Scorsese','Mozart','Shakespeare','Einstein','Columbo','Prospector','Rockefeller','Pandora'];
    if(known.indexOf(name)===-1) return;
    e.preventDefault();
    e.stopPropagation();
    go(name);
  }, true);
})();
