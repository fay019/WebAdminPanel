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
  const elGrid = elCard.querySelector('#storageGrid');
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

  function formatGiB(bytes){ return (bytes/ (1024**3)); }

  function formatStats(s){
    if (!s) return 'n/a';
    const usedGiB = formatGiB(s.used_bytes || s.usedBytes || s.used).toFixed(1);
    const sizeGiB = formatGiB(s.size_bytes || s.sizeBytes || s.size).toFixed(1);
    const pct = (s.used_pct != null ? s.used_pct : (s.size_bytes? (s.used_bytes*100/s.size_bytes):0)).toFixed(1);
    return `${usedGiB}G/${sizeGiB}G (${pct}%)`;
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
    const usedGiB = formatGiB(vol.used_bytes).toFixed(1);
    const sizeGiB = formatGiB(vol.size_bytes).toFixed(1);
    const pct = (vol.used_pct != null ? vol.used_pct : (vol.size_bytes? (vol.used_bytes*100/vol.size_bytes):0)).toFixed(1);
    const label = vol.label ? `${vol.label} || ${vol.device}` : vol.device;
    return `${label} (${vol.mountpoint}) — ${usedGiB} / ${sizeGiB} GiB (${pct}%)`;
  }

  function renderGrid(volumes){
    elGrid.innerHTML = '';
    volumes.forEach(v=>{
      const wrap = document.createElement('div');
      wrap.className = 'mini-donut';
      wrap.style.display='inline-block'; wrap.style.margin='8px'; wrap.style.textAlign='center';
      const c = document.createElement('canvas'); c.width=80; c.height=80; wrap.appendChild(c);
      const label = document.createElement('div'); label.className='smallmono'; label.textContent = `${v.label ? v.label+ ' || ' + v.device : v.device} (${v.mountpoint})`;
      label.style.maxWidth='200px'; label.style.overflow='hidden'; label.style.textOverflow='ellipsis'; label.style.whiteSpace='nowrap';
      label.title = tip(v);
      wrap.appendChild(label);
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
    if (window.Chart){
      if (pieChart) { pieChart.destroy(); pieChart = null; }
      pieChart = new Chart(elPie.getContext('2d'), { type: 'pie', data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] }, options: {
        plugins: { legend: { position: 'right', labels: { boxWidth: 12 } }, tooltip: { callbacks: { label: (ctx)=>{
          const idx = ctx.dataIndex; const vol = items[idx].v; return tip(vol);
        } } } },
      }});
    }
  }

  function updateUnit(){
    // Re-render to update tooltips
    if (window.__STORAGE_LAST_VOLUMES__) {
      renderPie(window.__STORAGE_LAST_VOLUMES__);
      renderGrid(window.__STORAGE_LAST_VOLUMES__);
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
      renderPie(volumes);
      renderGrid(volumes);
      updateTiles(data.path_stats || {});
    } catch (e) {
      console.debug('[storage] fail', e?.message||e);
    }
  }

  async function loadNvme(){
    if (!elNvme) return;
    try {
      const r = await fetch('/api/nvme/health', { cache: 'no-store' });
      if (!r.ok) throw new Error('http '+r.status);
      const h = await r.json();
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
      if (!h || h.ok===false) { cls='warn'; label='NA'; }
      else if (h.status==='HOT') { cls='err'; label='HOT'; }
      else if (h.status==='WARN') { cls='warn'; label='WARN'; }
      badge.className = 'badge ' + cls; badge.textContent = label;
      const tip = h && (h.tooltip || '') || 'NVMe';
      // Use data-tip tooltip on the badge, and title on icon
      badge.setAttribute('data-tip', tip);
      icon.setAttribute('title', tip);
      wrap.appendChild(icon); wrap.appendChild(badge);
      container.appendChild(wrap);
    } catch (e) {
      // no-op
    }
  }

  if (elToggle) elToggle.addEventListener('click', (e)=>{
    e.preventDefault(); state.unit = state.unit==='percent'?'gib':'percent'; updateUnit([]);
  });

  // initial load and then poll every 12s (covers 10s cache)
  load();
  setInterval(load, 12000);
  // NVMe health: refresh every 10 minutes
  loadNvme();
  setInterval(loadNvme, 10*60*1000);
})();
