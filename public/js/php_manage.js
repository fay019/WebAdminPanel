(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }

  const overlay = qs('#busyOverlay');
  const title = qs('#busyTitle');
  const hint = qs('#busyHint');
  const logPre = qs('#busyLog');
  const closeBtn = qs('#busyClose');

  function openOverlay(t){
    title.textContent = t || 'Exécution en cours…';
    overlay.removeAttribute('aria-hidden');
    overlay.style.display = 'block';
    closeBtn.disabled = true;
    logPre.textContent = '';
  }
  function closeOverlay(){
    overlay.setAttribute('aria-hidden','true');
    overlay.style.display = 'none';
  }
  if (closeBtn) closeBtn.addEventListener('click', closeOverlay);

  async function streamPost(url, data, action){
    let t = 'Exécution en cours…';
    if (action === 'install') t = 'Installation en cours…';
    else if (action === 'remove') t = 'Désinstallation en cours…';
    else if (action === 'restart') t = 'Redémarrage en cours…';
    openOverlay(t);
    try{
      // Ajouter un header CSRF si présent dans le formulaire pour robustesse
      let csrf = data.get('_csrf') || data.get('_token') || data.get('csrf') || data.get('token') || '';
      const headers = { 'Accept': 'text/plain', 'Cache-Control': 'no-store', 'X-Requested-With': 'fetch' };
      if (csrf) headers['X-CSRF-Token'] = csrf;
      const res = await fetch(url, {
        method: 'POST',
        body: data,
        headers
      });
      if (!res.body){
        const txt = await res.text();
        logPre.textContent += txt;
      } else {
        const reader = res.body.getReader();
        const decoder = new TextDecoder('utf-8');
        while(true){
          const {done, value} = await reader.read();
          if (done) break;
          logPre.textContent += decoder.decode(value, {stream:true});
          logPre.scrollTop = logPre.scrollHeight;
        }
      }
      title.textContent = 'Terminé';
    }catch(e){
      logPre.textContent += '\n[ERREUR] '+(e&&e.message?e.message:String(e))+'\n';
      title.textContent = 'Erreur';
    } finally {
      closeBtn.disabled = false;
    }
  }

  // Attach to forms/buttons with data-stream="1"
  // Utilitaires modale de confirmation locale (sans dépendre de app.js)
  const cm = document.getElementById('confirm-modal');
  const cmMsg = cm ? document.getElementById('cm-message') : null;
  const cmOk = cm ? document.getElementById('cm-okay') : null;
  const cmCancel = cm ? document.getElementById('cm-cancel') : null;
  function openConfirm(message, onOk){
    if (!cm || !cmMsg || !cmOk) { return false; }
    cmMsg.textContent = message || 'Confirmer ?';
    cm.hidden = false;
    const handler = function(e){ e.preventDefault(); closeConfirm(); onOk && onOk(); };
    cmOk.addEventListener('click', handler, { once:true });
    if (cmCancel) cmCancel.addEventListener('click', () => closeConfirm(), { once:true });
    function esc(e){ if (e.key==='Escape'){ closeConfirm(); document.removeEventListener('keydown', esc); } }
    document.addEventListener('keydown', esc);
    return true;
  }
  function closeConfirm(){ if (cm) cm.hidden = true; }

  // 1) Boutons avec confirmation + stream: utiliser la modale locale puis streamer
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('button[data-stream="1"][data-confirm], input[type="submit"][data-stream="1"][data-confirm]');
    if (!btn) return;
    const form = btn.closest('form');
    if (!form) return;
    ev.preventDefault();
    ev.stopPropagation();
    const msg = btn.getAttribute('data-confirm') || form.getAttribute('data-confirm') || 'Confirmer ?';
    const proceed = () => {
      const fd = new FormData(form);
      if (btn.name) { fd.set(btn.name, btn.value || ''); }
      if (!fd.get('ver') && fd.get('version')){ fd.set('ver', fd.get('version')); }
      const custom = fd.get('ver_custom'); if (custom){ fd.set('ver', custom); }
      fd.set('ajax', '1'); fd.set('stream', '1');
      const action = btn.value || fd.get('action') || '';
      streamPost('/php/manage/stream', fd, action);
    };
    if (!openConfirm(msg, proceed)) {
      // fallback si pas de modale: continuer sans modale
      proceed();
    }
  });

  // 2) Pour les boutons stream SANS confirmation, intercepter le clic et streamer directement
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('button[data-stream="1"]');
    if (!btn) return;
    const form = btn.closest('form');
    if (!form) return;
    // Si une confirmation est demandée (data-confirm), laisser app.js gérer la modale
    if (btn.hasAttribute('data-confirm') || form.hasAttribute('data-confirm')) return;
    ev.preventDefault();
    const fd = new FormData(form);
    if (btn.name) { fd.set(btn.name, btn.value || ''); }
    if (!fd.get('ver') && fd.get('version')){ fd.set('ver', fd.get('version')); }
    const custom = fd.get('ver_custom');
    if (custom){ fd.set('ver', custom); }
    fd.set('ajax', '1');
    fd.set('stream', '1');
    const action = btn.value || fd.get('action') || '';
    streamPost('/php/manage/stream', fd, action);
  });
})();
