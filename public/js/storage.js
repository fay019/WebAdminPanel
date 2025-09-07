// storage.js — render storage widget on Dashboard using /api/storage
(function(){
  if (window.__STORAGE_WIDGET__) return; window.__STORAGE_WIDGET__ = true;

  const API = '/api/storage';
  const USE_CHARTJS = true; // use CDN if present

  // Config
  const MAX_SEGMENTS = 10; // if more volumes, group smallest used into "Autres"
  const state = { unit: 'percent' }; // 'percent' | 'gib'

  const elCard = document.getElementById('storageCard');
  if (!elCard) return;
  const elToggle = elCard.querySelector('[data-action="toggle-unit"]');
  const elPie = elCard.querySelector('#storagePie');
  const elGrid = elCard.querySelector('#storageGrid');

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

  function buildPieData(volumes){
    const items = volumes.map(v=>({ key: `${v.label} (${v.mountpoint})`, used: v.used_bytes, v }));
    // Group others
    if (items.length > MAX_SEGMENTS){
      items.sort((a,b)=>b.used - a.used);
      const head = items.slice(0, MAX_SEGMENTS-1);
      const tail = items.slice(MAX_SEGMENTS-1);
      const otherUsed = tail.reduce((s,x)=>s+x.used,0);
      head.push({ key: 'Autres', used: otherUsed, v: { label: 'Autres', mountpoint: '', device: 'others' } });
      return head;
    }
    return items;
  }

  function tip(vol){
    const usedGiB = formatGiB(vol.used_bytes).toFixed(1);
    const sizeGiB = formatGiB(vol.size_bytes).toFixed(1);
    const pct = (vol.used_pct != null ? vol.used_pct : (vol.size_bytes? (vol.used_bytes*100/vol.size_bytes):0)).toFixed(1);
    if (state.unit === 'percent') return `${vol.label} (${vol.mountpoint}) — ${pct}%`;
    return `${vol.label} (${vol.mountpoint}) — ${usedGiB} / ${sizeGiB} GiB (${pct}%)`;
  }

  function renderGrid(volumes){
    elGrid.innerHTML = '';
    volumes.forEach(v=>{
      const wrap = document.createElement('div');
      wrap.className = 'mini-donut';
      wrap.style.display='inline-block'; wrap.style.margin='8px'; wrap.style.textAlign='center';
      const c = document.createElement('canvas'); c.width=80; c.height=80; wrap.appendChild(c);
      const label = document.createElement('div'); label.className='smallmono'; label.textContent = `${v.label} (${v.mountpoint})`;
      label.style.maxWidth='120px'; label.style.overflow='hidden'; label.style.textOverflow='ellipsis'; label.style.whiteSpace='nowrap';
      label.title = tip(v);
      wrap.appendChild(label);
      elGrid.appendChild(wrap);
      if (window.Chart){
        new Chart(c.getContext('2d'), { type: 'doughnut', data: {
          labels: ['Utilisé','Libre'],
          datasets: [{ data: [v.used_bytes, Math.max(0, v.size_bytes - v.used_bytes)], backgroundColor: [hashColor(v.device+v.mountpoint), 'rgba(120,120,120,0.2)'], borderWidth: 0 }]
        }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: ()=> tip(v) } } }, cutout: '70%', radius: 36 } });
      }
    });
  }

  let pieChart = null;
  function renderPie(volumes){
    const items = buildPieData(volumes);
    const labels = items.map(x=>x.key);
    const values = items.map(x=>x.used);
    const colors = items.map(x=>hashColor((x.v.device||'')+(x.v.mountpoint||'')+(x.key)));
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

  async function load(){
    try {
      await ensureChartJs();
      const r = await fetch(API, { cache:'no-store' }); if (!r.ok) throw new Error('http '+r.status); const data = await r.json();
      const volumes = Array.isArray(data.volumes) ? data.volumes : [];
      window.__STORAGE_LAST_VOLUMES__ = volumes;
      renderPie(volumes);
      renderGrid(volumes);
    } catch (e) {
      console.debug('[storage] fail', e?.message||e);
    }
  }

  if (elToggle) elToggle.addEventListener('click', (e)=>{
    e.preventDefault(); state.unit = state.unit==='percent'?'gib':'percent'; updateUnit([]); // currently visual effect mainly tooltips already in GiB
  });

  // initial load and then poll every 12s (covers 10s cache)
  load();
  setInterval(load, 12000);
})();
