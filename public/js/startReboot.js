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

  function showOverlay(title, hint) {
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
  closeBt?.addEventListener('click', () => {
    if (overlay) { overlay.style.display = 'none'; overlay.setAttribute('aria-hidden','true'); }
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
    if (closeBt) closeBt.style.display = isShutdown ? '' : 'none';

    // Compte à rebours (par défaut 30s, min 5s)
    let seconds = 30;
    try { seconds = Math.max(5, parseInt(form.getAttribute('data-countdown') || '30', 10)); } catch {}
    setDot('#f59e0b');
    const label = isShutdown ? 'Extinction dans ' : 'Arrêt avant redémarrage dans ';
    if (hintEl) hintEl.textContent = label + `${seconds}s…`;
    await new Promise((resolve) => {
      const timer = setInterval(() => {
        seconds -= 1;
        if (seconds <= 0) { clearInterval(timer); resolve(); return; }
        if (hintEl) hintEl.textContent = label + `${seconds}s…`;
      }, 1000);
    });

    if (isShutdown) {
      setDot('#22c55e');
      if (logEl) {
        logEl.textContent += 'Extinction demandée. À très bientôt !\n';
        logEl.textContent += 'Pour redémarrer votre Raspberry Pi, débranchez puis rebranchez l’alimentation.\n';
      }
      if (hintEl) hintEl.textContent = 'À très bientôt !';
      finishOverlay();
      return;
    } else {
      // Reboot: wait for the server to come back online via sysinfo updates
      setDot('#94a3b8');
      if (hintEl) hintEl.textContent = 'Redémarrage en cours… en attente du retour en ligne…';
      const onUpdate = (ev)=>{
        const at = (ev?.detail && ev.detail.at) || window.SYSINFO_LAST_TS || 0;
        if (at && (Date.now() - at) < 5000) {
          document.removeEventListener('sysinfo:update', onUpdate);
          if (hintEl) hintEl.textContent = 'De retour en ligne — rechargement…';
          setTimeout(()=>location.reload(), 300);
        }
      };
      document.addEventListener('sysinfo:update', onUpdate);
      // Safety timeout (90s)
      setTimeout(()=>{
        document.removeEventListener('sysinfo:update', onUpdate);
        if (hintEl) hintEl.textContent = 'Tentative de rechargement…';
        location.reload();
      }, 90000);
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
