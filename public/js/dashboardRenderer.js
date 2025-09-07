// dashboardRenderer.js — subscribe to sysinfo:update and refresh dashboard values
(function(){
  if (window.__DASHBOARD_RENDERER__) return; // singleton guard
  window.__DASHBOARD_RENDERER__ = true;

  const UI_CFG = Object.assign({ temp: { cpu: { cold:45, hot:70 }, ambient: { cold:10, hot:25 } }, icons: { set: 'lucide' }, a11y: { forceHighContrast: false } }, window.UI_CONFIG||{});

  const elUptime = document.getElementById('uptimeVal');
  const elDisk = document.querySelector('[data-metric="diskMain"]');
  const elOS = document.querySelector('[data-metric="osPretty"]');
  const elKernel = document.querySelector('[data-metric="osKernel"]');
  const elProcCount = document.querySelector('[data-metric="procCount"]');
  const elCpuCores = document.querySelector('[data-metric="cpuCores"]');
  const elTopCpu = document.querySelector('[data-metric="topCpu"]');
  const elTopMem = document.querySelector('[data-metric="topMem"]');
  const elDiskWww = document.querySelector('[data-metric="diskWww"]');
  const elPhpCli = document.querySelector('[data-metric="phpCli"]');
  const elNginxVer = document.querySelector('[data-metric="nginxVer"]');

  // Store last rendered raw values to avoid unnecessary DOM updates
  const last = Object.create(null);

  function setText(el, txt){ if (!el) return; const t = String(txt); if (el.textContent !== t) el.textContent = t; }
  function setTitle(el, txt){ if (!el) return; const t = String(txt); if (el.getAttribute('title') !== t) el.setAttribute('title', t); }

  function humanSeconds(sec){
    sec = Math.max(0, Math.floor(+sec || 0));
    const d = Math.floor(sec/86400); sec%=86400;
    const h = Math.floor(sec/3600); sec%=3600;
    const m = Math.floor(sec/60); const s = sec%60;
    const parts=[]; if(d) parts.push(d+' j'); if(h) parts.push(h+' h'); if(m) parts.push((m<10&&h?('0'+m):m)+' min'); if(!d&&!h&&s) parts.push((s<10?('0'+s):s)+' s');
    return parts.length?parts.join(' '):'0 s';
  }

  function classifyTemp(v, type){
    const t = Number(v);
    if (!isFinite(t)) return { state:'NA', color:'#999' };
    const th = (type==='ambient') ? UI_CFG.temp.ambient : UI_CFG.temp.cpu;
    if (t < th.cold) return { state:'FROID', color:'#3b82f6' };
    if (t > th.hot) return { state:'CHAUD', color:'#ef4444' };
    return { state:'NORMAL', color:'#10b981' };
  }

  function ensureTempBadge(){
    const v = document.getElementById('cpuTempVal'); if (!v) return null;
    let b = v.nextElementSibling && v.nextElementSibling.classList.contains('badge') ? v.nextElementSibling : null;
    if (!b) { b = document.createElement('span'); b.className='badge'; b.style.marginLeft='8px'; v.after(b); }
    return b;
  }
  function ensureAmbientBadge(){
    const v = document.getElementById('ambientTempVal'); if (!v) return null;
    let b = v.nextElementSibling && v.nextElementSibling.classList.contains('badge') ? v.nextElementSibling : null;
    if (!b) { b = document.createElement('span'); b.className='badge'; b.style.marginLeft='8px'; v.after(b); }
    return b;
  }

  function onUpdate(e){
    const data = (e && e.detail && e.detail.data) || window.SYSINFO_LAST_DATA || {};
    if (!data || typeof data !== 'object') return;

    // Uptime: prefer uptime.seconds, else compute from boot_time if available
    try {
      let seconds = null;
      if (data.uptime && typeof data.uptime.seconds === 'number') seconds = data.uptime.seconds;
      else if (data.boot_time || data.bootTime) {
        const bt = (data.boot_time || data.bootTime);
        const nowSec = Math.floor(Date.now()/1000);
        const bootSec = (typeof bt === 'number') ? Math.floor(bt) : parseInt(bt,10);
        if (isFinite(bootSec)) seconds = Math.max(0, nowSec - bootSec);
      }
      if (seconds != null) {
        const formatted = (data.uptime && data.uptime.human) ? data.uptime.human : humanSeconds(seconds);
        if (last.uptime !== seconds) {
          setText(elUptime, formatted);
          last.uptime = seconds;
        } else if (elUptime && !elUptime.textContent) {
          setText(elUptime, formatted);
        }
      }
    } catch {}

    // CPU Temperature classification + badge
    try {
      const rawTemp = (data.cpu && (data.cpu.temp_c ?? data.cpu.tempC)) ?? (data.cpu_temp || data['cpu-temp'] || null);
      if (rawTemp != null) {
        const el = document.getElementById('cpuTempVal');
        const cls = classifyTemp(rawTemp, 'cpu');
        if (el) { el.style.color = cls.color; el.title = `Temp CPU — ${rawTemp} °C (${cls.state})`; el.textContent = `${rawTemp} °C`; }
        const badge = ensureTempBadge(); if (badge) { badge.textContent = cls.state; badge.style.background = cls.color; badge.style.color = '#fff'; }
      }
    } catch {}

    // Ambient Temperature classification + badge (if available)
    try {
      const amb = data.ambient && (data.ambient.temp_c ?? data.ambient.tempC);
      if (amb != null) {
        const el = document.getElementById('ambientTempVal');
        const cls = classifyTemp(amb, 'ambient');
        if (el) { el.style.color = cls.color; el.title = `Temp Ext — ${amb} °C (${cls.state})`; el.textContent = `${amb} °C`; }
        const badge = ensureAmbientBadge(); if (badge) { badge.textContent = cls.state; badge.style.background = cls.color; badge.style.color = '#fff'; }
      }
    } catch {}

    // CPU cores
    const cores = data.cpu && typeof data.cpu.cores === 'number' ? data.cpu.cores : null;
    if (cores != null && last.cores !== cores) { setText(elCpuCores, cores); last.cores = cores; }

    // Processes count
    const proc = data.processes && typeof data.processes.count === 'number' ? data.processes.count : null;
    if (proc != null && last.proc !== proc) { setText(elProcCount, proc); last.proc = proc; }

    // OS pretty and kernel
    const pretty = data.os && data.os.pretty ? data.os.pretty : null;
    if (pretty != null && last.pretty !== pretty) { setText(elOS, pretty); last.pretty = pretty; }
    const kernel = data.os && data.os.kernel ? data.os.kernel : null;
    if (kernel != null && last.kernel !== kernel) { setText(elKernel, kernel); last.kernel = kernel; }

    // Disk main (if any normalized string exists) — fallback to composed text
    if (data.disk && data.disk.srv_www) {
      const d = data.disk.srv_www; // {size_gb, used_gb, avail_gb, used_pct}
      const text = `${d.used_gb}G/${d.size_gb}G (${d.used_pct}%)`;
      if (last.diskMain !== text) { setText(elDisk, text); last.diskMain = text; }
    }

    // Disk /var/www (legacy field likely present in initial PHP render) – if backend also sends string
    if (data.disk_www) { if (last.diskWww !== data.disk_www) { setText(elDiskWww, data.disk_www); last.diskWww = data.disk_www; } }

    // Top CPU / MEM labels if present
    if (data.cpu && data.cpu.top_cpu_proc) {
      const p = data.cpu.top_cpu_proc; const text = `${p.cmd} (${p.cpu}%)`;
      if (last.topCpu !== text) { setText(elTopCpu, text); setTitle(elTopCpu, text); last.topCpu = text; }
    }
    if (data.mem && data.mem.top_mem_proc) {
      const p = data.mem.top_mem_proc; const text = `${p.cmd} (${p.rss_mb}MB)`;
      if (last.topMem !== text) { setText(elTopMem, text); setTitle(elTopMem, text); last.topMem = text; }
    }

    // PHP CLI
    if (data.php && data.php.cli_version) {
      if (last.phpCli !== data.php.cli_version) { setText(elPhpCli, data.php.cli_version); last.phpCli = data.php.cli_version; }
    }

    // Nginx version
    if (data.nginx && data.nginx.version) {
      if (last.nginx !== data.nginx.version) { setText(elNginxVer, data.nginx.version); last.nginx = data.nginx.version; }
    }
  }

  document.addEventListener('sysinfo:update', onUpdate);
  // If sysinfo already fetched, render immediately once
  if (window.SYSINFO_LAST_DATA) {
    try { onUpdate({ detail: { data: window.SYSINFO_LAST_DATA, at: window.SYSINFO_LAST_TS || Date.now() } }); } catch {}
  }
})();
