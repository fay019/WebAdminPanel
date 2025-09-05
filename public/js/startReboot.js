// startReboot.js — dédié au flux alimentation (shutdown/reboot)
// Dépendances UI: éléments overlay présents dans dashboard/index.php
// Contract: écoute l’événement custom 'power:submit' émis par app.js après confirmation.

(function () {
  // Config endpoint: préférer window.POWER_ENDPOINT, sinon garder l’URL d’action passée (legacy)
  function getEndpoint(fallbackUrl) {
    if (typeof window.POWER_ENDPOINT === 'string' && window.POWER_ENDPOINT.trim() !== '') {
      return window.POWER_ENDPOINT.trim();
    }
    return fallbackUrl || '/system_power.php';
  }

  const overlay = document.getElementById('powerOverlay');
  const titleEl = document.getElementById('powerTitle');
  const hintEl  = document.getElementById('powerHint');
  const logEl   = document.getElementById('powerLog');
  const closeBt = document.getElementById('powerClose');

  // --- State & cleanup management ---
  let listeners = [];
  let timers = [];
  function addListener(target, type, handler, opts){ if (!target) return; target.addEventListener(type, handler, opts); listeners.push([target,type,handler,opts]); }
  function addTimer(id){ timers.push(id); return id; }
  function cleanup() {
    // clear timers
    for (const t of timers.splice(0)) { try { clearTimeout(t); } catch {} try { clearInterval(t); } catch {} }
    // remove listeners
    for (const it of listeners.splice(0)) { try { it[0].removeEventListener(it[1], it[2], it[3]); } catch {} }
    // reset attributes
    if (overlay) { delete overlay.dataset.powerMode; overlay.removeAttribute('data-power-mode'); }
    // reset UI
    if (closeBt) { closeBt.disabled = false; closeBt.style.display = ''; }
  }

  function closeOverlayInternal() {
    if (overlay) { overlay.style.display = 'none'; overlay.setAttribute('aria-hidden','true'); }
    cleanup();
  }

  function showOverlay(title, hint) {
    cleanup(); // ensure clean before opening
    if (titleEl && title) titleEl.textContent = title;
    if (hintEl && hint)   hintEl.textContent  = hint;
    if (logEl) logEl.textContent = '';
    if (closeBt) closeBt.disabled = true;
    if (overlay) { overlay.style.display = 'block'; overlay.setAttribute('aria-hidden','false'); }
  }
  function setDot(color) {
    const dot = overlay?.querySelector('.ok-dot');
    if (dot) dot.style.background = color;
  }
  function finishOverlay() { if (closeBt) closeBt.disabled = false; }
  function enableEscClose(){ addListener(document, 'keydown', (e)=>{ if (e.key === 'Escape') closeOverlayInternal(); }); }
  addListener(closeBt, 'click', () => {
    closeOverlayInternal();
    const mode = overlay?.dataset?.powerMode || '';
    if (mode !== 'shutdown') location.reload();
  });

  function parseKV(txt) {
    const out = {}; (txt || '').split(/\r?\n/).forEach(l => { const i = l.indexOf('='); if (i<=0) return; const k=l.slice(0,i).trim().toLowerCase().replace(/[^a-z0-9_]+/g,'_'); const v=l.slice(i+1).trim(); out[k]=v; });
    return out;
  }
  // Use sysinfo.js as the single source; do not fetch here
  async function getBootIdSafe() {
    const d = (window.SYSINFO_LAST_DATA || null);
    if (d && (d.boot_id || d.bootId)) return d.boot_id || d.bootId;
    return null;
  }

  async function postPowerJson(url, fd) {
    const r = await fetch(url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const txt = await r.text();
    try { return { status: r.status, data: JSON.parse(txt), raw: txt }; }
    catch { return { status: r.status, data: null, raw: txt }; }
  }

  async function runPowerFlow(form, actionUrl, submitterName, submitterValue) {
    showOverlay('Commande en cours…', 'Préparation…');

    // Lecture discrète du boot_id courant (aucun message anxiogène si indisponible)
    const beforeId = await getBootIdSafe();
    if (beforeId && logEl) {
      // Journal en mode discret: afficher seulement si disponible
      logEl.textContent += `boot_id: ${beforeId}\n`;
    } else {
      // Fallback silencieux: ne rien afficher dans l’UI (laisser éventuel debug en console)
      console.debug('[power] boot_id indisponible (ok)');
    }

    setDot('#60a5fa');
    if (hintEl) hintEl.textContent = 'Envoi de la commande…';

    const fd = new FormData(form);
    if (submitterName) fd.set(submitterName, submitterValue || '1');
    if (!fd.has('action')) {
      // Conserver compat: si boutons utilisent name=action value=shutdown|reboot
      const btnAction = submitterName === 'action' ? submitterValue : null;
      if (btnAction) fd.set('action', btnAction);
    }
    // Forcer mode ajax côté serveur (au cas où)
    fd.set('ajax','1');

    const targetUrl = getEndpoint(actionUrl);

    let res;
    try {
      res = await postPowerJson(targetUrl, fd);
    } catch (err) {
      setDot('#ef4444');
      if (hintEl) hintEl.textContent = 'Erreur réseau';
      if (logEl) logEl.textContent += `[ERREUR] ${err?.message || err}\n`;
      finishOverlay();
      return;
    }

    // Journal brut (utile debug)
    if (logEl && res.raw) {
      const raw = (typeof res.raw === 'string') ? res.raw : String(res.raw);
      if (raw.trim()) logEl.textContent += raw + '\n';
    }

    if (!res || res.status >= 400 || (res.data && res.data.ok === false)) {
      setDot('#ef4444');
      if (hintEl) hintEl.textContent = 'Erreur côté serveur';
      finishOverlay();
      return;
    }

    const isShutdown = String(fd.get('action')) === 'shutdown';
    if (overlay) overlay.dataset.powerMode = isShutdown ? 'shutdown' : 'reboot';
    // Close button: hidden during reboot until offline reached; visible for shutdown now
    if (closeBt) { closeBt.style.display = isShutdown ? '' : 'none'; }

    // Compte à rebours (par défaut 30s, min 5s)
    let seconds = 30;
    try { seconds = Math.max(5, parseInt(form.getAttribute('data-countdown') || '30', 10)); } catch {}
    setDot('#f59e0b');
    const label = isShutdown ? 'Extinction dans ' : 'Arrêt avant redémarrage dans ';
    if (hintEl) hintEl.textContent = label + `${seconds}s…`;
    await new Promise((resolve) => {
      const iv = addTimer(setInterval(() => {
        seconds -= 1;
        if (seconds <= 0) { clearInterval(iv); resolve(); return; }
        if (hintEl) hintEl.textContent = label + `${seconds}s…`;
      }, 1000));
    });

    if (isShutdown) {
      // Phase: shutdown_wait_offline → attendre détection hors ligne via sysinfo.js
      document.dispatchEvent(new CustomEvent('power:phase', { detail: { phase: 'shutdown_wait_offline' } }));
      setDot('#94a3b8');
      if (hintEl) hintEl.textContent = 'Extinction en cours… en attente du passage hors ligne…';
      let wentOffline = false;
      let failCount = 0;
      // Close button and ESC enabled immediately after countdown
      if (closeBt) { closeBt.style.display = ''; finishOverlay(); }
      enableEscClose();
      const onErr = (ev)=>{
        failCount = ev?.detail?.consecutiveFails || (failCount+1);
        if (!wentOffline && failCount >= 2) {
          wentOffline = true;
          document.removeEventListener('sysinfo:error', onErr);
          setDot('#22c55e');
          if (logEl) logEl.textContent += 'Serveur hors ligne détecté.\n';
          if (hintEl) hintEl.textContent = 'Serveur hors ligne détecté — Vous pouvez fermer ce message.';
          finishOverlay(); // bouton Fermer activé, pas de reload auto
        }
      };
      addListener(document, 'sysinfo:error', onErr);
      // Filet de sécurité: activer bouton après 60s si pas d'events
      addTimer(setTimeout(()=>{ if (!wentOffline) { if (hintEl) hintEl.textContent = 'Vous pouvez fermer ce message.'; finishOverlay(); } }, 60000));
      return;
    } else {
      // Reboot: reboot_wait_offline puis reboot_wait_online via sysinfo.js
      document.dispatchEvent(new CustomEvent('power:phase', { detail: { phase: 'reboot_wait_offline' } }));
      setDot('#94a3b8');
      if (hintEl) hintEl.textContent = 'Redémarrage en cours… en attente du passage hors ligne…';
      let wentOffline = false;
      let failCount = 0;
      // Make Close visible/enabled and ESC active when we leave sending -> offline wait
      if (closeBt) { closeBt.style.display = ''; finishOverlay(); }
      enableEscClose();
      const onErr = (ev)=>{
        failCount = ev?.detail?.consecutiveFails || (failCount+1);
        if (!wentOffline && failCount >= 2) {
          wentOffline = true;
          document.removeEventListener('sysinfo:error', onErr);
          if (hintEl) hintEl.textContent = 'Hors ligne détecté — attente du retour en ligne…';
          // Phase: reboot_wait_online
          document.dispatchEvent(new CustomEvent('power:phase', { detail: { phase: 'reboot_wait_online' } }));
          // Maintenant attendre le retour online (un succès)
          const onUpdate = (evu)=>{
            const at = (evu?.detail && evu.detail.at) || window.SYSINFO_LAST_TS || 0;
            if (at && (Date.now() - at) < 5000) {
              document.removeEventListener('sysinfo:update', onUpdate);
              if (hintEl) hintEl.textContent = 'De retour en ligne — rechargement…';
              addTimer(setTimeout(()=>location.reload(), 300));
              // Safety: also close if reload blocked
              addTimer(setTimeout(()=>{ if (overlay && overlay.style.display !== 'none') closeOverlayInternal(); }, 5000));
            }
          };
          addListener(document, 'sysinfo:update', onUpdate);
          // Timeout global au cas où (90s)
          addTimer(setTimeout(()=>{
            document.removeEventListener('sysinfo:update', onUpdate);
            if (hintEl) hintEl.textContent = 'Tentative de rechargement…';
            location.reload();
          }, 90000));
        }
      };
      addListener(document, 'sysinfo:error', onErr);
      // Filet de sécu si pas d’événements reçus
      addTimer(setTimeout(()=>{
        if (!wentOffline) {
          if (hintEl) hintEl.textContent = 'Tentative de rechargement…';
          location.reload();
        }
      }, 120000));
      return;
    }
  }

  // Interception des formulaires power (soumission directe sans data-confirm)
  document.addEventListener('submit', async (e) => {
    const form = e.target?.closest('form');
    if (!form) return;
    const actionUrl = (form.getAttribute('action') || '/system_power.php').trim();
    // Détection formulaire power (par convention legacy)
    let isPower = false;
    try { isPower = new URL(actionUrl, window.location.origin).pathname.endsWith('/system_power.php'); }
    catch { isPower = actionUrl.includes('system_power.php'); }
    if (!isPower) return;
    if (form.matches('[data-confirm]')) return; // confirmation gérée par app.js
    e.preventDefault();
    const submitter = e.submitter || null;
    runPowerFlow(form, actionUrl, submitter?.name || null, submitter?.value || null);
  });

  // Écoute l’événement custom (déclenché par app.js après confirmation)
  document.addEventListener('power:submit', (evt) => {
    const d = evt.detail || {};
    if (!d.form) return;
    const actionUrl = (d.actionUrl || '/system_power.php').trim();
    runPowerFlow(d.form, actionUrl, d.submitterName || null, d.submitterValue || null);
  });
})();
