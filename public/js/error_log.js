// Lightweight Error Log UI renderer
(function(){
  const RAW_ID = '__error_log_data';
  const listEl = document.getElementById('logList');
  const searchEl = document.getElementById('logSearch');
  const btnRefresh = document.getElementById('btnRefresh');
  const btnLoadMore = document.getElementById('btnLoadMore');
  const autoScrollEl = document.getElementById('autoScroll');
  const lastUpdatedEl = document.getElementById('logLastUpdated');
  const lastUpdatedTxt = document.getElementById('logLastUpdatedTxt');
  const countEl = document.getElementById('logCount');

  if (!listEl) return;

  const TYPE_COLORS = {
    php_error: 'warn',
    php_exception: 'danger',
    php_fatal: 'danger',
    http_404: 'info',
    http_405: 'info',
    http_500: 'danger',
    power_exec: 'info',
    other: 'muted'
  };

  let allLines = [];
  let entries = [];
  let pageSize = 50;
  let visibleCount = 0;
  let activeTypes = new Set(['php_error','php_exception','php_fatal','http_404','http_405','http_500','power_exec','other']);
  let keyword = '';

  function fmtDate(tsStr){
    // input like [2025-09-15 16:57:33] [type] ...
    const m = tsStr.match(/\[(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})\]/);
    if(!m) return tsStr;
    const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    const d = new Date(m[1].replace(/-/g,'/') + ' ' + m[2] + ' UTC');
    const txt = (isNaN(d.getTime())) ? (m[1]+' - '+m[2]+' (UTC)') : `${d.getUTCDate()} ${months[d.getUTCMonth()]} ${d.getUTCFullYear()} - ${m[2]} (UTC)`;
    return txt;
  }

  function parseLine(line){
    // Expected format: [date] [level] [rid] message | ctx={...}
    const obj = { raw: line, date: '', level: 'other', rid: '', message: line, ctx: null, summary: '', type: 'other' };
    const m = line.match(/^\[(.+?)\] \[(.+?)\] \[([0-9a-f]+)\] (.*?)(?: \| ctx=(\{.*\}))?$/);
    if (m) {
      obj.date = m[1];
      obj.level = m[2];
      obj.rid = m[3];
      obj.message = m[4];
      obj.type = (m[2]||'').toLowerCase();
      try { obj.ctx = m[5]? JSON.parse(m[5]) : null; } catch { obj.ctx = m[5] || null; }
    }
    obj.summary = (obj.message.length > 180) ? (obj.message.slice(0,177) + '…') : obj.message;
    if (!(obj.type in TYPE_COLORS)) obj.type = 'other';
    return obj;
  }

  function renderBadge(type){
    const cls = TYPE_COLORS[type] || 'muted';
    return `<span class="badge ${cls}">[${escapeHtml(type)}]</span>`;
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
  }

  function renderEntry(ent, idx){
    const dateTxt = fmtDate(`[${ent.date}]`);
    const detailsId = `logd-${idx}`;
    const ctxPretty = typeof ent.ctx === 'string' ? ent.ctx : (ent.ctx ? JSON.stringify(ent.ctx, null, 2) : null);
    return `
      <div class="log-card" data-type="${escapeHtml(ent.type)}" tabindex="0" role="button" aria-expanded="false" aria-controls="${detailsId}">
        <div class="log-head">
          <div class="log-left">
            <div class="log-date">${escapeHtml(dateTxt)}</div>
            <div class="log-badges">${renderBadge(ent.type)}</div>
          </div>
          <div class="log-summary" title="${escapeHtml(ent.message)}">${escapeHtml(ent.summary)}</div>
        </div>
        <div class="log-details" id="${detailsId}" hidden>
          <div class="log-ctx">${ctxPretty ? `<pre>${escapeHtml(ctxPretty)}</pre>` : '<em class="muted">(pas de détails)</em>'}</div>
          <div class="log-rid small muted">requestId: ${escapeHtml(ent.rid || '')}</div>
        </div>
      </div>`;
  }

  function applyFilters(list){
    const kw = keyword.trim().toLowerCase();
    return list.filter(e => activeTypes.has(e.type) && (kw==='' || e.raw.toLowerCase().includes(kw)));
  }

  function render()
  {
    const filtered = applyFilters(entries);
    const slice = filtered.slice(Math.max(0, filtered.length - visibleCount));
    if (slice.length === 0) {
      listEl.innerHTML = '<div class="small muted" style="padding:8px 12px">Aucune erreur enregistrée.</div>';
      countEl && (countEl.textContent = '0');
      return;
    }
    listEl.innerHTML = slice.map(renderEntry).join('');
    countEl && (countEl.textContent = `${slice.length} / ${filtered.length}`);
    // bind toggles
    listEl.querySelectorAll('.log-card').forEach(card => {
      card.addEventListener('click', () => toggleCard(card));
      card.addEventListener('keydown', (e)=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); toggleCard(card);} });
    });
    if (autoScrollEl && autoScrollEl.checked) {
      listEl.scrollTop = listEl.scrollHeight;
    }
  }

  function toggleCard(card){
    const details = card.querySelector('.log-details');
    const expanded = !(details.hasAttribute('hidden'));
    if (expanded){ details.setAttribute('hidden', ''); card.setAttribute('aria-expanded','false'); }
    else{ details.removeAttribute('hidden'); card.setAttribute('aria-expanded','true'); }
  }

  function ingest(lines){
    allLines = Array.isArray(lines) ? lines : [];
    entries = allLines.map(parseLine);
    visibleCount = Math.max(pageSize, Math.min(pageSize, entries.length));
    render();
  }

  function humanizeTs(ts){
    const d = new Date(ts*1000);
    const pad = n=> String(n).padStart(2,'0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  function updateLastUpdated(ts){
    if (!lastUpdatedEl || !lastUpdatedTxt) return;
    const n = Number(lastUpdatedEl.getAttribute('data-ts')||ts||Date.now()/1000);
    lastUpdatedTxt.textContent = humanizeTs(n);
  }

  function refresh(){
    btnRefresh?.setAttribute('disabled', 'true');
    fetch('/dashboard/error-log?partial=1&lines=200', { headers: { 'Accept': 'application/json' } })
      .then(r=>r.json())
      .then(json => {
        if (!json || !json.ok) return;
        lastUpdatedEl?.setAttribute('data-ts', String(json.ts));
        updateLastUpdated(json.ts);
        ingest(json.lines || []);
      })
      .catch(()=>{})
      .finally(()=> btnRefresh?.removeAttribute('disabled'));
  }

  // Events
  document.querySelectorAll('.flt').forEach(chk => {
    chk.addEventListener('change', function(){
      const v = this.value;
      if (this.checked) activeTypes.add(v); else activeTypes.delete(v);
      render();
    });
  });

  searchEl?.addEventListener('input', function(){ keyword = this.value || ''; render(); });
  btnRefresh?.addEventListener('click', refresh);
  btnLoadMore?.addEventListener('click', function(){ visibleCount = Math.min(entries.length, visibleCount + pageSize); render(); });

  // Init from embedded JSON
  try {
    const raw = document.getElementById(RAW_ID)?.textContent || '[]';
    const arr = JSON.parse(raw);
    ingest(arr);
  } catch { ingest([]); }

  // Initial last updated text
  updateLastUpdated(Number(lastUpdatedEl?.getAttribute('data-ts')||Date.now()/1000));
})();