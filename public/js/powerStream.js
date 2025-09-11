// powerStream.js — SSE client for power events (reboot/shutdown/online)
(function(){
  if (window.__POWER_STREAM__) return; window.__POWER_STREAM__ = true;

  const led = document.getElementById('srv-led');
  const label = led ? led.querySelector('.srv-led-label') : null;

  function setLed(state){
    if (!led) return;
    led.classList.remove('ok','err','pulse');
    if (state === 'online') { led.classList.add('ok'); if (label) label.textContent = 'Serveur: Allumé'; }
    else if (state === 'offline') { led.classList.add('err'); if (label) label.textContent = 'Serveur: Éteint'; }
    else if (state === 'rebooting') { led.classList.add('pulse'); if (label) label.textContent = 'Serveur: Redémarrage…'; }
    else if (state === 'shutting') { led.classList.add('err'); if (label) label.textContent = 'Serveur: Extinction…'; }
    else { if (label) label.textContent = 'Serveur: Inconnu'; }
  }

  // BroadcastChannel: only one tab should own the EventSource
  const channelName = 'power_events_channel_v1';
  const bc = ('BroadcastChannel' in window) ? new BroadcastChannel(channelName) : null;
  let isLeader = false;
  let leaderPingTimer = null;
  let leaderLastSeen = 0;

  function startLeaderPing(){
    if (leaderPingTimer) clearInterval(leaderPingTimer);
    leaderPingTimer = setInterval(()=>{
      bc && bc.postMessage({ t:'leader_ping', at: Date.now() });
    }, 5000);
  }

  if (bc) {
    bc.onmessage = (ev)=>{
      const m = ev.data || {};
      if (m.t === 'leader_ping') { leaderLastSeen = Date.now(); }
      if (m.t === 'evt' && m.type) handleEvent(m.type, m.payload || {});
      if (m.t === 'state' && m.state) setLed(m.state);
    };
    // Elect leader after 1s if no leader seen
    setTimeout(()=>{
      if (Date.now() - leaderLastSeen > 6000) {
        isLeader = true;
        startLeaderPing();
        openSse();
      }
    }, 1000);
    // Also re-elect leader if leader gone for >8s
    setInterval(()=>{
      if (!isLeader && (Date.now() - leaderLastSeen > 8000)) {
        isLeader = true;
        startLeaderPing();
        openSse();
      }
    }, 4000);
  } else {
    // No BroadcastChannel support -> this tab opens SSE
    isLeader = true; openSse();
  }

  let es = null;
  let reconnectTimer = null;

  function handleEvent(type, data){
    switch(type){
      case 'reboot_started':
        setLed('rebooting');
        break;
      case 'shutdown_started':
        setLed('shutting');
        break;
      case 'server_online':
        setLed('online');
        break;
      default:
        break;
    }
  }

  function broadcast(type, payload){ bc && bc.postMessage({ t:'evt', type, payload }); }
  function broadcastState(state){ bc && bc.postMessage({ t:'state', state }); }

  function openSse(){
    if (!isLeader) return;
    if (es) { try { es.close(); } catch {} es = null; }
    try {
      es = new EventSource('/api/power/stream', { withCredentials: true });
    } catch (e) {
      scheduleReconnect();
      return;
    }

    es.addEventListener('open', ()=>{
      broadcastState('online');
    });
    es.addEventListener('error', (e)=>{
      // If unauthorized or closed, stop; EventSource auto-reconnects unless we close it
      try {
        const target = e && e.currentTarget; const rs = target && target.readyState;
        if (rs === 2 /* CLOSED */) {
          setLed('offline'); broadcastState('offline');
          scheduleReconnect();
        }
      } catch {}
    });
    ['reboot_started','shutdown_started','server_online'].forEach((t)=>{
      es.addEventListener(t, (ev)=>{
        let data = {}; try { data = JSON.parse(ev.data || '{}'); } catch {}
        handleEvent(t, data); broadcast(t, data);
      });
    });
  }

  function scheduleReconnect(){
    if (!isLeader) return;
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(()=>{
      reconnectTimer = null; openSse();
    }, 4000);
  }

  // If the stream closes (e.g., reboot), display offline in this tab as well
  setLed('online');
})();
