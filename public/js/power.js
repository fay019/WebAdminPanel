// power.js — Robust controller for power overlay (shutdown/reboot)
// Singleton, no leaks, listens to 'power:submit' custom event dispatched by app.js
(function(){
  if (window.__POWER_OVERLAY_INIT__ === true) return; // idempotent include
  window.__POWER_OVERLAY_INIT__ = true;

  const sel = {
    overlay: '#powerOverlay',
    title:   '#powerTitle',
    hint:    '#powerHint',
    log:     '#powerLog',
    close:   '#powerClose',
    dot:     '.ok-dot'
  };

  const el = {
    overlay: document.querySelector(sel.overlay),
    title:   document.querySelector(sel.title),
    hint:    document.querySelector(sel.hint),
    log:     document.querySelector(sel.log),
    close:   document.querySelector(sel.close),
  };

  function setDot(color){
    const dot = el.overlay ? el.overlay.querySelector(sel.dot) : null;
    if (dot) dot.style.background = color;
  }

  function autoScrollLog(){
    if (!el.log) return;
    // Keep scroll pinned to bottom if it was close to bottom
    try {
      el.log.scrollTop = el.log.scrollHeight;
    } catch {}
  }

  const PowerOverlayController = (function(){
    let state = 'idle'; // 'running'|'error'|'done'|'rebooting'|'shutting-down'|'idle'
    let countdownTimer = null;
    let escHandler = null;
    let reader = null; // ReadableStreamDefaultReader
    let abortCtrl = null;
    let closing = false;

    function setAria(open){
      if (!el.overlay) return;
      el.overlay.style.display = open ? 'block' : 'none';
      el.overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function setTitle(t){ if (el.title) el.title.textContent = t; }
    function setHint(t){ if (el.hint) el.hint.textContent = t; }

    function enableCloseButton(enable){ if (el.close) { el.close.disabled = !enable; if (enable) el.close.focus({ preventScroll: true }); } }

    function clearTimers(){ if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; } }

    function stopStream(){
      try { if (reader) { try { reader.cancel(); } catch {} } } catch {}
      reader = null;
      try { if (abortCtrl) abortCtrl.abort(); } catch {}
      abortCtrl = null;
    }

    function trapEsc(){
      if (escHandler) return; // already
      escHandler = function(e){
        if (e.key !== 'Escape') return;
        if (state !== 'running') {
          api.close();
        }
      };
      document.addEventListener('keydown', escHandler);
    }
    function untrapEsc(){ if (escHandler) { document.removeEventListener('keydown', escHandler); escHandler = null; } }

    function reset(){
      clearTimers();
      stopStream();
      untrapEsc();
      closing = false;
      state = 'idle';
      if (el.log) el.log.textContent = '';
      setTitle('Commande en cours…');
      setHint('Envoi de la commande…');
      setDot('#60a5fa');
      enableCloseButton(false);
    }

    function open(title, hint){
      reset();
      if (title) setTitle(title);
      if (hint) setHint(hint);
      setAria(true);
      state = 'running';
      trapEsc();
    }

    function appendLog(text){
      if (!el.log || text == null) return;
      const t = typeof text === 'string' ? text : String(text);
      if (t) {
        el.log.textContent += t;
        autoScrollLog();
      }
    }

    function setState(next){
      state = next;
      switch(next){
        case 'running':
          setDot('#60a5fa'); enableCloseButton(false); setTitle('Commande en cours…'); break;
        case 'error':
          setDot('#ef4444'); enableCloseButton(true); setTitle('Erreur'); break;
        case 'done':
          setDot('#60a5fa'); enableCloseButton(true); setTitle('Terminé'); break;
        case 'rebooting':
          setDot('#60a5fa'); enableCloseButton(true); setTitle('Redémarrage…'); startCountdown('Redémarrage dans'); break;
        case 'shutting-down':
          setDot('#60a5fa'); enableCloseButton(true); setTitle('Extinction…'); startCountdown('Extinction dans'); break;
      }
    }

    function startCountdown(prefix){
      clearTimers();
      let seconds = 10;
      try { const attr = el.overlay?.getAttribute('data-countdown') || el.close?.getAttribute('data-countdown'); if (attr) seconds = Math.max(3, parseInt(attr,10)); } catch {}
      setHint(`${prefix} ${seconds}…`);
      countdownTimer = setInterval(()=>{
        seconds -= 1;
        if (seconds <= 0) {
          clearTimers();
          api.close();
        } else {
          setHint(`${prefix} ${seconds}…`);
        }
      }, 1000);
    }

    function close(){
      if (closing) return;
      closing = true;
      clearTimers();
      stopStream();
      untrapEsc();
      setAria(false);
      // Reset quickly after close to free resources
      setTimeout(()=>{ reset(); }, 0);
    }

    function enableClose(){ enableCloseButton(true); }

    const api = { open, appendLog, setState, enableClose, close, reset, get state(){ return state; }, _setReader(r){ reader = r; }, _setAbort(c){ abortCtrl = c; } };
    return api;
  })();

  // Expose for debug if needed (not polluting globals excessively)
  window.PowerOverlayController = PowerOverlayController;

  // Close button handler (bound once)
  if (el.close) {
    el.close.addEventListener('click', function(){
      const st = PowerOverlayController.state;
      PowerOverlayController.close();
      if (st === 'done') {
        try { if (window.POWER_RELOAD_ON_DONE !== false) location.reload(); } catch {}
      }
    });
  }

  // Helper: streaming fetch, optionally mocked by window.POWER_MOCK
  async function streamFetch(url, opts){
    if (window.POWER_MOCK === true) {
      const scenario = (opts && opts.body instanceof FormData) ? String(opts.body.get('action')||'') : '';
      return mockStreamResponse(scenario);
    }
    const r = await fetch(url, opts);
    return r;
  }

  function mockStreamResponse(action){
    const encoder = new TextEncoder();
    const chunks = [];
    if (action === 'reboot') {
      chunks.push('Starting reboot\n');
      chunks.push('Stopping services...\n');
      chunks.push('REBOOTING NOW\n');
    } else if (action === 'shutdown') {
      chunks.push('Starting shutdown\n');
      chunks.push('Saving state...\n');
      chunks.push('SHUTTING DOWN NOW\n');
    } else if (action === 'error') {
      chunks.push('[ERROR] Something went wrong\n');
    } else {
      chunks.push('Running task...\n');
      chunks.push('OK\n');
    }
    const body = new ReadableStream({
      start(controller){
        let i=0; const iv = setInterval(()=>{
          if (i>=chunks.length){ clearInterval(iv); controller.close(); return; }
          controller.enqueue(encoder.encode(chunks[i++]));
        }, 100);
      }
    });
    return new Response(body, { status: 200, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }

  // Main integration: listen to power:submit
  document.addEventListener('power:submit', async (evt) => {
    try {
      const detail = evt?.detail || {};
      const form = detail.form;
      let actionUrl = (detail.actionUrl || '/dashboard/power').trim();
      // Normalize legacy endpoint to MVC route to avoid nginx 404 on /system_power.php
      try {
        const u = new URL(actionUrl, window.location.origin);
        if (u.pathname.endsWith('/system_power.php')) actionUrl = '/dashboard/power';
      } catch {}
      if (!form || !actionUrl) return;
      // Guard: if already running, ignore new submissions
      if (PowerOverlayController.state === 'running') {
        console.info('[power] Ignored new request while running');
        return;
      }

      PowerOverlayController.open('Commande en cours…', 'Envoi de la commande…');

      const fd = new FormData(form);
      // Preserve clicked submitter if provided
      if (detail.submitterName) fd.set(detail.submitterName, detail.submitterValue || '1');
      if (!fd.has('action')) {
        // fallback: use submitterName/value if it looked like an action
        if (detail.submitterName === 'action' && detail.submitterValue) fd.set('action', detail.submitterValue);
      }
      // Ensure ajax flag expected by backend
      if (!fd.has('ajax')) fd.set('ajax','1');

      const ctrl = new AbortController();
      PowerOverlayController._setAbort(ctrl);

      const resp = await streamFetch(actionUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'text/plain', 'X-Requested-With': 'XMLHttpRequest' },
        signal: ctrl.signal,
      });

      // Handle redirects or non-200 early
      const ctype = resp.headers.get('content-type') || '';
      if (!resp.ok) {
        // Try to parse JSON error payload for clearer message
        if (/^application\/json/i.test(ctype)) {
          const data = await resp.json().catch(()=>null);
          if (data) {
            const msg = (data.message || data.error || ('HTTP ' + resp.status));
            PowerOverlayController.appendLog(String(msg) + '\n');
            PowerOverlayController.setState('error');
            PowerOverlayController.enableClose();
            return;
          }
        }
        const txt = await resp.text().catch(()=> '');
        PowerOverlayController.appendLog(txt || ('HTTP '+resp.status));
        PowerOverlayController.setState('error');
        PowerOverlayController.enableClose();
        return;
      }

      // Accept JSON (MVC endpoint) or streaming text
      if (/^application\/json/i.test(ctype)) {
        const data = await resp.json().catch(()=>null);
        if (!data) {
          PowerOverlayController.appendLog('Réponse JSON invalide');
          PowerOverlayController.setState('error');
          PowerOverlayController.enableClose();
          return;
        }
        if (data.ok) {
          const act = (data.action || '').toString();
          if (act === 'reboot') {
            PowerOverlayController.appendLog((data.message || 'Redémarrage demandé') + '\n');
            PowerOverlayController.setState('rebooting');
            return;
          }
          if (act === 'shutdown') {
            PowerOverlayController.appendLog((data.message || 'Extinction demandée') + '\n');
            PowerOverlayController.setState('shutting-down');
            return;
          }
          PowerOverlayController.appendLog((data.message || 'OK') + '\n');
          PowerOverlayController.setState('done');
          PowerOverlayController.enableClose();
          return;
        } else {
          PowerOverlayController.appendLog((data.message || data.error || 'Erreur') + '\n');
          PowerOverlayController.setState('error');
          PowerOverlayController.enableClose();
          return;
        }
      }

      if (!/^text\/(plain|event-stream)/i.test(ctype) && !/^application\/octet-stream/i.test(ctype)) {
        const txt = await resp.text().catch(()=> '');
        PowerOverlayController.appendLog(txt || 'Réponse inattendue');
        setHint('Session expirée ? Veuillez vous reconnecter.');
        PowerOverlayController.setState('error');
        PowerOverlayController.enableClose();
        return;
      }

      const reader = resp.body.getReader();
      PowerOverlayController._setReader(reader);
      const decoder = new TextDecoder('utf-8');
      let sawReboot = false, sawShutdown = false, sawOk = false, sawError = false;

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        PowerOverlayController.appendLog(chunk);
        const up = chunk.toUpperCase();
        if (/REBOOT|RESTART/.test(up)) sawReboot = true;
        if (/SHUTDOWN|POWERING OFF|POWER OFF/.test(up)) sawShutdown = true;
        if (/\bOK\b|OK:/.test(chunk)) sawOk = true;
        if (/ERROR|ERREUR|\[ERROR\]|\[ERREUR\]/i.test(chunk)) sawError = true;
      }

      // End of stream
      if (sawError) {
        PowerOverlayController.setState('error');
        PowerOverlayController.enableClose();
        return;
      }
      if (sawReboot) {
        PowerOverlayController.setState('rebooting');
        return; // countdown will auto-close
      }
      if (sawShutdown) {
        PowerOverlayController.setState('shutting-down');
        return; // countdown will auto-close
      }
      if (sawOk) {
        PowerOverlayController.setState('done');
        PowerOverlayController.enableClose();
        return;
      }
      // Default: consider done without explicit marker
      PowerOverlayController.setState('done');
      PowerOverlayController.enableClose();
    } catch (err) {
      console.error('[power] exception', err);
      try { PowerOverlayController.setState('error'); } catch {}
      try { PowerOverlayController.appendLog(String(err?.message||err)); } catch {}
      PowerOverlayController.enableClose();
    }
  }, { passive: true });
})();
