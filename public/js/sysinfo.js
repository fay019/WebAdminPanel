// sysinfo.js — canonical poller for /api/sysinfo with singleton guard
(function(){
  // Singleton guard: prevent multiple pollers if script is loaded twice
  if (window.__SYSINFO_POLLING__ === true) {
    // Already started; do nothing
    return;
  }
  window.__SYSINFO_POLLING__ = true;

  const CFG = {
    endpoint: (typeof window.SYSINFO_URL === 'string' && window.SYSINFO_URL) ? window.SYSINFO_URL : '/api/sysinfo',
    intervalMs: (typeof window.SYSINFO_INTERVAL_MS === 'number' && window.SYSINFO_INTERVAL_MS>0) ? window.SYSINFO_INTERVAL_MS : 2000,
    hiddenIntervalMs: (typeof window.SYSINFO_HIDDEN_INTERVAL_MS === 'number' && window.SYSINFO_HIDDEN_INTERVAL_MS>0) ? window.SYSINFO_HIDDEN_INTERVAL_MS : 5000,
    timeoutMs: (typeof window.SYSINFO_TIMEOUT_MS === 'number' && window.SYSINFO_TIMEOUT_MS>0) ? window.SYSINFO_TIMEOUT_MS : 2000,
    maxConsecutiveFails: (typeof window.SYSINFO_MAX_FAILS === 'number' && window.SYSINFO_MAX_FAILS>=1) ? window.SYSINFO_MAX_FAILS : 2,
  };
  if (!CFG.endpoint) return;

  // Expose global state
  window.SYSINFO_LAST_TS = window.SYSINFO_LAST_TS || 0;
  window.SYSINFO_LAST_DATA = window.SYSINFO_LAST_DATA || null;

  // DOM hooks (dashboard widgets)
  const elTemp = document.getElementById('cpuTempVal');
  const elRam  = document.getElementById('ramVal');
  const elLoad = document.getElementById('cpuLoadVal');

  function setText(el, txt){ if (el) el.textContent = txt; }

  function fmtRam(obj){
    if (obj && typeof obj === 'object' && 'used_mb' in obj && 'total_mb' in obj) {
      const used = Math.round(obj.used_mb);
      const total = Math.round(obj.total_mb);
      const pct = obj.percent ? String(obj.percent).replace(/\.0+$/, '') : Math.round(used * 100 / Math.max(total, 1)) + '%';
      return `${used}MB / ${total}MB (${pct})`;
    }
    if (typeof obj === 'string') return obj;
    return 'n/a';
  }

  function parseKV(text){
    const out = {};
    (text||'').split(/\r?\n/).forEach(line=>{
      const i=line.indexOf('='); if(i<=0) return;
      const k=line.slice(0,i).trim().toLowerCase().replace(/[^a-z0-9_]+/g,'_');
      const v=line.slice(i+1).trim(); out[k]=v;
    });
    return out;
  }

  async function fetchWithTimeout(url, ms){
    const ctrl = new AbortController();
    const t = setTimeout(()=>ctrl.abort(), ms);
    try {
      const r = await fetch(url, {cache:'no-store', signal: ctrl.signal});
      if (!r.ok) throw new Error('http '+r.status);
      const raw = await r.text();
      try { return JSON.parse(raw); } catch { return parseKV(raw); }
    } finally { clearTimeout(t); }
  }

  function pickTemp(d){ return d?.cpu_temp ?? d?.['cpu-temp'] ?? d?.cpu?.temp_c ?? d?.cpuTemp ?? 'n/a'; }
  function pickLoad(d){
    if (d?.cpu_load != null) return d.cpu_load;
    if (d?.load != null) return d.load;
    if (d?.cpu?.load_pct != null) return d.cpu.load_pct;
    if (d?.cpuLoad != null) return d.cpuLoad;
    if (d?.loadavg && (d.loadavg['1m'] != null)) return d.loadavg['1m'];
    return 'n/a';
  }
  function pickRam(d){ return fmtRam(d?.ram ?? d?.mem ?? d?.memory); }

  let consecutiveFails = 0;
  let timer = null;

  function scheduleNext(){
    const hidden = typeof document !== 'undefined' && document.hidden;
    const delay = hidden ? CFG.hiddenIntervalMs : CFG.intervalMs;
    timer = setTimeout(tick, delay);
  }

  async function tick(){
    try {
      const data = await fetchWithTimeout(CFG.endpoint, CFG.timeoutMs);
      window.SYSINFO_LAST_TS = Date.now();
      window.SYSINFO_LAST_DATA = data;
      consecutiveFails = 0;
      // Update UI if present
      setText(elTemp, String(pickTemp(data)));
      setText(elRam, pickRam(data));
      setText(elLoad, String(pickLoad(data)));
      // Emit normalized event
      document.dispatchEvent(new CustomEvent('sysinfo:update', { detail: { data, at: window.SYSINFO_LAST_TS } }));
    } catch (e) {
      consecutiveFails++;
      document.dispatchEvent(new CustomEvent('sysinfo:error', { detail: { at: Date.now(), consecutiveFails } }));
      if (consecutiveFails >= CFG.maxConsecutiveFails) {
        setText(elTemp, 'n/a');
        setText(elRam, 'n/a');
        setText(elLoad, 'n/a');
      }
      if (console && console.debug) console.debug('[sysinfo] fail', e?.message||e, 'x', consecutiveFails);
    } finally {
      scheduleNext();
    }
  }

  // Start now
  tick();

  // Adjust on visibility changes
  document.addEventListener('visibilitychange', () => {
    if (timer) { clearTimeout(timer); timer = null; }
    scheduleNext();
  });
})();