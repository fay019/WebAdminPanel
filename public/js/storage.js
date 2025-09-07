// storage.js — render storage widget on Dashboard using /api/storage
(function(){
  if (window.__STORAGE_WIDGET__) return; window.__STORAGE_WIDGET__ = true;

  const API = '/api/storage';
  const USE_CHARTJS = true; // use CDN if present

  // Config
  const MAX_SEGMENTS = 10; // if more volumes, group smallest used into "Autres"
  const SMALL_SHARE_THRESHOLD = 0.01; // <1% of total used
  const state = { unit: 'percent' }; // 'percent' | 'gib'

  const elCard = document.getElementById('storageCard');
  if (!elCard) return;
  const elToggle = elCard.querySelector('[data-action="toggle-unit"]');
  const elPie = elCard.querySelector('#storagePie');
  const elTotals = elCard.querySelector('#storageTotals');
  const elGrid = document.getElementById('storageGrid');
  const elVolumesTitle = document.getElementById('volumesTitle');
  const elTableBody = document.querySelector('#volumesTable tbody');
  const elCopyBtn = document.getElementById('copyStorageJson');
  const elNvme = elCard.querySelector('[data-nvme-health]');

  const elRootTile = document.querySelector('[data-metric="diskMain"]');
  const elWebTile  = document.querySelector('[data-metric="diskWww"]');

  // Load Chart.js lazily if not available
  function ensureChartJs(){
    return new Promise((resolve)=>{
      if (window.Chart) return resolve();
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      s.onload = ()=>resolve(); s.onerror = ()=>resolve();
      document.head.appendChild(s);
    });
  }

  function hashColor(str){
    // simple deterministic hash -> HSL
    let h = 0; for (let i=0;i<str.length;i++) h = (h*31 + str.charCodeAt(i))>>>0;
    const hue = h % 360; const sat = 60; const light = 50;
    return `hsl(${hue} ${sat}% ${light}%)`;
  }

  const fr1 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const fr0 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

  function fmtDec(v){ const n = Number(v); if (!isFinite(n)) return '0'; if (Math.abs(n) >= 1000) return fr0.format(n); return fr1.format(n); }
  function fmtPct01(n){ return fr1.format(Math.round(n*10)/10); }

  function bytesToUnit(bytes){
    let v = Number(bytes)||0; const units=['o','Ko','Mo','Go','To','Po']; let i=0;
    while (v>=1024 && i<units.length-1){ v/=1024; i++; }
    const str = (Math.abs(v) >= 1000) ? fr0.format(v) : fr1.format(v);
    return { v, unit: units[i], text: `${str} ${units[i]}` };
  }

  function formatGiB(bytes){ return (bytes/ (1024**3)); }

  function formatStats(s){
    if (!s) return 'n/a';
    const used = Number(s.used_bytes ?? s.usedBytes ?? s.used ?? 0);
    const size = Number(s.size_bytes ?? s.sizeBytes ?? s.size ?? 0);
    const pct = size>0 ? (used*100/size) : 0;
    const usedGiB = fmtDec(used/ (1024**3));
    const sizeGiB = fmtDec(size/ (1024**3));
    return `${usedGiB} Go / ${sizeGiB} Go (${fmtDec(pct)} %)`;
  }

  function ensureWarnBadge(el, show){
    if (!el) return;
    let b = el.nextElementSibling && el.nextElementSibling.classList.contains('badge') ? el.nextElementSibling : null;
    if (!b && show){ b=document.createElement('span'); b.className='badge warn'; b.style.marginLeft='6px'; b.textContent='WARN'; el.after(b); }
    if (b) b.style.display = show ? '' : 'none';
  }

  function buildPieData(volumes){
    const totalUsed = volumes.reduce((s,v)=>s+v.used_bytes,0) || 1;
    let items = volumes.map(v=>({ key: `${(v.label? (v.label + ' || ' + v.device) : v.device)} (${v.mountpoint})`, used: v.used_bytes, share: v.used_bytes/totalUsed, v }));
    // Group others if more than MAX_SEGMENTS or if any item <1% share
    items.sort((a,b)=>b.used - a.used);
    const needGroupByCount = items.length > MAX_SEGMENTS;
    const smallItems = items.filter(x=>x.share < SMALL_SHARE_THRESHOLD);
    const needGroupBySmall = smallItems.length > 0;
    if (needGroupByCount || needGroupBySmall){
      const head = [];
      const tail = [];
      if (needGroupByCount){
        head.push(...items.slice(0, MAX_SEGMENTS-1));
        tail.push(...items.slice(MAX_SEGMENTS-1));
      } else {
        // keep all non-small in head
        items.forEach(x=>{ (x.share < SMALL_SHARE_THRESHOLD ? tail : head).push(x); });
      }
      const otherUsed = tail.reduce((s,x)=>s+x.used,0);
      if (otherUsed > 0) head.push({ key: 'Autres', used: otherUsed, share: otherUsed/totalUsed, v: { label: 'Autres', mountpoint: '', device: 'others' } });
      return head;
    }
    return items;
  }

  function tip(vol){
    const used = Number(vol.used_bytes||0); const size = Number(vol.size_bytes||0);
    const pct = size>0 ? (used*100/size) : 0;
    const usedTxt = fmtDec(used/(1024**3));
    const sizeTxt = fmtDec(size/(1024**3));
    const label = vol.label ? `${vol.label} || ${vol.device}` : vol.device;
    return `${label} (${vol.mountpoint}) — ${usedTxt} Go / ${sizeTxt} Go (${fmtDec(pct)} %)`;
  }

  function renderGrid(volumes){
    if (!elGrid) return;
    elGrid.innerHTML = '';
    // sort by used_bytes desc
    const sorted = [...volumes].sort((a,b)=> (b.used_bytes||0) - (a.used_bytes||0));
    sorted.forEach(v=>{
      const wrap = document.createElement('div');
      wrap.className = 'mini-donut';
      wrap.style.textAlign='center';
      const c = document.createElement('canvas'); c.width=80; c.height=80; wrap.appendChild(c);
      const label = document.createElement('div'); label.className='smallmono'; label.textContent = `${v.label ? v.label+ ' || ' + v.device : v.device} (${v.mountpoint})`;
      label.title = tip(v);
      wrap.appendChild(label);
      // numeric line: utilisé/total (xx,x %)
      const nums = document.createElement('div'); nums.className='smallmono'; nums.style.marginTop='2px';
      const used = Number(v.used_bytes||0); const size = Number(v.size_bytes||0); const pct = size>0?(used*100/size):0;
      nums.textContent = `${fmtDec(used/(1024**3))} Go / ${fmtDec(size/(1024**3))} Go (${fmtDec(pct)} %)`;
      nums.title = tip(v);
      wrap.appendChild(nums);
      // badges row
      const badgesRow = document.createElement('div'); badgesRow.className = 'chip-row'; badgesRow.style.marginTop = '4px';
      const addBadge = (txt, cls, tipTxt)=>{ const b = document.createElement('span'); b.className = 'badge'+(cls?(' '+cls):''); b.textContent = txt; if (tipTxt) b.setAttribute('data-tip', tipTxt); badgesRow.appendChild(b); };
      if (Array.isArray(v.badges)) {
        v.badges.forEach((bTxt)=>{ if (bTxt) addBadge(bTxt, '', null); });
      } else {
        if (v.is_nvme) addBadge('NVMe');
        if (v.is_sd) addBadge('SD');
      }
      if ((v.used_pct||0) >= 95) addBadge('ALERTE', 'err', 'Espace disque ≥95% utilisé');
      wrap.appendChild(badgesRow);
      elGrid.appendChild(wrap);
      if (window.Chart){
        new Chart(c.getContext('2d'), { type: 'doughnut', data: {
          labels: ['Utilisé','Libre'],
          datasets: [{ data: [v.used_bytes, Math.max(0, v.size_bytes - v.used_bytes)], backgroundColor: [hashColor(v.id || v.device), 'rgba(120,120,120,0.2)'], borderWidth: 0 }]
        }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: ()=> tip(v) } } }, cutout: '70%', radius: 36 } });
      }
    });
  }

  let pieChart = null;
  function renderPie(volumes){
    const sorted = [...volumes].sort((a,b)=>b.used_bytes - a.used_bytes);
    const items = buildPieData(sorted);
    const labels = items.map(x=>x.key);
    const values = items.map(x=>x.used);
    const colors = items.map(x=>hashColor((x.v.id||x.v.device||'') ));
    if (window.Chart && elPie){
      if (pieChart) { pieChart.destroy(); pieChart = null; }
      pieChart = new Chart(elPie.getContext('2d'), { type: 'pie', data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] }, options: {
        plugins: { legend: { position: 'right', labels: { boxWidth: 12 } }, tooltip: { callbacks: { label: (ctx)=>{
          const idx = ctx.dataIndex; const vol = items[idx].v; return tip(vol);
        } } } },
      }});
    }
  }

  function renderTotals(volumes){
    if (!elTotals) return;
    const used = volumes.reduce((s,v)=>s + (Number(v.used_bytes)||0), 0);
    const size = volumes.reduce((s,v)=>s + (Number(v.size_bytes)||0), 0);
    const pct = size>0 ? (used*100/size) : 0;
    elTotals.textContent = `Total — ${fmtDec(used/(1024**3))} Go / ${fmtDec(size/(1024**3))} Go (${fmtDec(pct)} %)`;
  }

  function updateUnit(){
    // Re-render to update tooltips
    if (window.__STORAGE_LAST_VOLUMES__) {
      renderPie(window.__STORAGE_LAST_VOLUMES__);
      renderGrid(window.__STORAGE_LAST_VOLUMES__);
      renderTotals(window.__STORAGE_LAST_VOLUMES__);
    }
  }

  function updateTiles(path_stats){
    try {
      if (!path_stats) return;
      const fmt = (s)=>formatStats(s);
      if (elRootTile && path_stats.root){
        const txt = fmt(path_stats.root);
        if (elRootTile.textContent !== txt) elRootTile.textContent = txt;
        const warn = (path_stats.root.used_pct||0) >= 95;
        ensureWarnBadge(elRootTile, warn);
      }
      if (elWebTile){
        if (path_stats.web){
          const txt = fmt(path_stats.web);
          if (elWebTile.textContent !== txt) elWebTile.textContent = txt;
          const warn = (path_stats.web.used_pct||0) >= 95;
          ensureWarnBadge(elWebTile, warn);
        } else {
          elWebTile.textContent = 'n/a'; ensureWarnBadge(elWebTile, false);
        }
      }
    } catch {}
  }

  async function load(){
    try {
      await ensureChartJs();
      const r = await fetch(API, { cache:'no-store' }); if (!r.ok) throw new Error('http '+r.status); const data = await r.json();
      const volumes = Array.isArray(data.volumes) ? data.volumes : [];
      window.__STORAGE_LAST_VOLUMES__ = volumes;
      // Titles and counts
      if (elVolumesTitle) elVolumesTitle.textContent = `Volumes (${volumes.length})`;
      // Render pie + totals + donuts grid
      renderPie(volumes);
      renderTotals(volumes);
      renderGrid(volumes);
      // Fill table if present
      if (elTableBody) {
        elTableBody.innerHTML = '';
        volumes.forEach(v=>{
          const used = Number(v.used_bytes||0); const size = Number(v.size_bytes||0); const free = Math.max(0, size-used); const pct = size>0?(used*100/size):0;
          const tr = document.createElement('tr');
          const td = (t)=>{ const x=document.createElement('td'); x.textContent=t; return x; };
          tr.appendChild(td(v.mountpoint||''));
          tr.appendChild(td(v.fs||v.fstype||''));
          tr.appendChild(td(`${fmtDec(used/(1024**3))} Go`));
          tr.appendChild(td(`${fmtDec(free/(1024**3))} Go`));
          tr.appendChild(td(`${fmtDec(size/(1024**3))} Go`));
          tr.appendChild(td(`${fmtDec(pct)} %`));
          elTableBody.appendChild(tr);
        });
      }
      // Copy JSON button
      if (elCopyBtn) {
        elCopyBtn.onclick = ()=>{ try { navigator.clipboard.writeText(JSON.stringify({ volumes }, null, 2)); elCopyBtn.textContent='Copié !'; setTimeout(()=>elCopyBtn.textContent='Copier JSON storage',1200); } catch(_){} };
      }
      updateTiles(data.path_stats || {});
    } catch (e) {
      console.debug('[storage] fail', e?.message||e);
    }
  }

  async function loadNvme(){
    try {
      const r = await fetch('/api/nvme/health', { cache: 'no-store' });
      if (!r.ok) throw new Error('http '+r.status);
      const h = await r.json();
      // Header chip in Storage card
      if (elNvme){
        const container = elNvme;
        container.innerHTML = '';
        const wrap = document.createElement('span'); wrap.className='chip-row';
        const icon = document.createElementNS('http://www.w3.org/2000/svg','svg');
        icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('width','16'); icon.setAttribute('height','16'); icon.setAttribute('aria-hidden','true');
        const path = document.createElementNS('http://www.w3.org/2000/svg','path');
        path.setAttribute('d','M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm3 0h14v8H5zM7 11h2m3 0h2m3 0h2');
        path.setAttribute('stroke','currentColor'); path.setAttribute('stroke-width','1.6'); path.setAttribute('fill','none'); path.setAttribute('stroke-linecap','round'); path.setAttribute('stroke-linejoin','round');
        icon.appendChild(path);
        const badge = document.createElement('span'); badge.className='badge';
        let cls = 'ok'; let label = 'OK';
        if (!h || h.status==='NA' || h.ok===false) { cls='warn'; label='NA'; }
        else if (h.status==='HOT') { cls='err'; label='HOT'; }
        else if (h.status==='WARN') { cls='warn'; label='WARN'; }
        badge.className = 'badge ' + cls; badge.textContent = label;
        const tip = h && h.device ? `NVMe ${h.device}` : 'NVMe';
        badge.setAttribute('data-tip', tip);
        icon.setAttribute('title', tip);
        wrap.appendChild(icon); wrap.appendChild(badge);
        container.appendChild(wrap);
      }
      // Dedicated card
      const card = document.getElementById('nvmeHealthCard');
      if (card){
        const st = document.getElementById('nvmeHealthStatus');
        const banner = document.getElementById('nvmeHealthBanner');
        const tempEl = document.getElementById('nvmeTemp');
        const wearEl = document.getElementById('nvmeWear');
        const errEl = document.getElementById('nvmeErrors');
        const pohEl = document.getElementById('nvmePOH');
        const tsEl = document.getElementById('nvmeTs');
        const m = h && h.metrics || {};
        // status badge
        let cls = 'ok', label = 'OK';
        if (!h || h.status==='NA' || h.ok===false) { cls='warn'; label='NA'; }
        else if (h.status==='HOT') { cls='err'; label='HOT'; }
        else if (h.status==='WARN') { cls='warn'; label='WARN'; }
        st.className = 'badge ' + cls; st.textContent = label; st.setAttribute('title', h && h.device ? h.device : '');
        // metrics
        tempEl.textContent = (m.temperature_c!=null) ? `${m.temperature_c} °C` : 'n/a';
        wearEl.textContent = (m.percentage_used!=null) ? `${fmtDec(m.percentage_used)} %` : 'n/a';
        errEl.textContent = (m.media_errors!=null) ? `${m.media_errors}` : 'n/a';
        pohEl.textContent = (m.power_on_hours!=null) ? `${m.power_on_hours} h` : 'n/a';
        // timestamp ago
        const ts = Number(h && h.ts) || 0; if (ts>0){ const mins = Math.floor((Date.now()/1000 - ts)/60); tsEl.textContent = `il y a ${mins} min`; } else { tsEl.textContent = 'n/a'; }
        // NA banner with reason
        const metricsGrid = card.querySelector('.grid');
        if (!h || h.status==='NA'){
          banner.style.display='';
          const reason = (h && h.reason) ? h.reason : 'indisponible';
          banner.textContent = `Santé NVMe indisponible (${reason})`;
          if (metricsGrid) metricsGrid.style.display = 'none';
        } else {
          banner.style.display='none'; banner.textContent='';
          if (metricsGrid) metricsGrid.style.display = '';
        }
      }
    } catch (e) {
      // no-op
    }
  }

  if (elToggle) elToggle.addEventListener('click', (e)=>{
    e.preventDefault(); state.unit = state.unit==='percent'?'gib':'percent'; updateUnit([]);
  });

  // initial load and then poll every 15s (storage 15–30 s)
  function startStoragePolling(){ if (window.__STORAGE_TIMER__) clearInterval(window.__STORAGE_TIMER__); window.__STORAGE_TIMER__ = setInterval(load, 15000); }
  function stopStoragePolling(){ if (window.__STORAGE_TIMER__) { clearInterval(window.__STORAGE_TIMER__); window.__STORAGE_TIMER__=null; } }
  function startNvmePolling(){ if (window.__NVME_TIMER__) clearInterval(window.__NVME_TIMER__); window.__NVME_TIMER__ = setInterval(loadNvme, 10*60*1000); }
  function stopNvmePolling(){ if (window.__NVME_TIMER__) { clearInterval(window.__NVME_TIMER__); window.__NVME_TIMER__=null; } }
  load();
  loadNvme();
  startStoragePolling();
  startNvmePolling();
  // Pause when tab is hidden
  document.addEventListener('visibilitychange', ()=>{
    if (document.hidden) { stopStoragePolling(); stopNvmePolling(); }
    else { load(); loadNvme(); startStoragePolling(); startNvmePolling(); }
  });
})();
