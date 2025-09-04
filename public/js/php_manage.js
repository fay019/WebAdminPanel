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

  async function streamPost(url, data){
    openOverlay('Installation en cours…');
    try{
      const res = await fetch(url, {
        method: 'POST',
        body: data,
        headers: { 'Accept': 'text/plain' }
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
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('button[data-stream="1"]');
    if (!btn) return;
    const form = btn.closest('form');
    if (!form) return;
    ev.preventDefault();
    if (btn.hasAttribute('data-confirm')){
      if (!window.confirm(btn.getAttribute('data-confirm'))) return;
    }
    const fd = new FormData(form);
    // normalize version fields (preserve legacy names)
    if (!fd.get('ver') && fd.get('version')){ fd.set('ver', fd.get('version')); }
    const custom = fd.get('ver_custom');
    if (custom){ fd.set('ver', custom); }
    fd.set('ajax', '1');
    streamPost('/php/manage/stream', fd);
  });
})();
