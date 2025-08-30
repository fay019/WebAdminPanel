// tables.js — unified table behaviors for Mini Web Panel
// - Auto-init on .tbl tables
// - Sorting with type detection and URL persistence (?sort=key&dir=asc|desc)
// - Mobile assistance: add data-label from headers if missing
// - Accessibility: focusable sortable headers, aria-sort

(function(){
  function ready(fn){
    if(document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn);
  }

  function debounce(fn, wait){
    let t; return function(){ clearTimeout(t); t = setTimeout(()=>fn.apply(this, arguments), wait); };
  }

  function textOf(el){ return (el && (el.getAttribute('data-sort') ?? '').toString().trim()) || el.textContent.trim(); }

  function detectType(values){
    // values: array of strings (first non-empty few)
    const sample = values.filter(v => v !== '').slice(0, 5);
    let nums = 0, dates = 0;
    for(const v of sample){
      const nv = Number(v);
      if(!isNaN(nv)) { nums++; continue; }
      const dv = Date.parse(v);
      if(!isNaN(dv)) { dates++; }
    }
    if(nums === sample.length && sample.length>0) return 'number';
    if(dates === sample.length && sample.length>0) return 'date';
    return 'string';
  }

  function getColumnCells(tbody, index){
    const out = [];
    tbody.querySelectorAll('tr').forEach(tr => {
      const cells = tr.cells;
      if(index < cells.length){ out.push(cells[index]); }
    });
    return out;
  }

  function compareValues(a, b, type){
    if(type === 'number'){
      const na = Number(a), nb = Number(b);
      return na - nb;
    }
    if(type === 'date'){
      return Date.parse(a) - Date.parse(b);
    }
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  }

  function sortTable(tbl, th, index, dir){
    const tbody = tbl.tBodies[0]; if(!tbody) return;
    const rows = Array.from(tbody.rows);
    const values = rows.map(r => textOf(r.cells[index]||{textContent:''}));
    const type = detectType(values);
    rows.sort((r1, r2) => {
      const v1 = textOf(r1.cells[index]||{textContent:''});
      const v2 = textOf(r2.cells[index]||{textContent:''});
      const cmp = compareValues(v1, v2, type);
      return dir === 'asc' ? cmp : -cmp;
    });
    // Re-append
    const frag = document.createDocumentFragment();
    rows.forEach(r => frag.appendChild(r));
    tbody.appendChild(frag);

    // Update aria-sort on headers
    const ths = tbl.tHead ? Array.from(tbl.tHead.rows[0].cells) : [];
    ths.forEach(h => h.removeAttribute('aria-sort'));
    th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
  }

  function updateSortIndicators(ths, currentIdx, dir){
    ths.forEach((th,i)=>{
      th.classList.remove('is-asc','is-desc');
      if(i===currentIdx){ th.classList.add(dir==='asc'?'is-asc':'is-desc'); }
    });
  }

  function persistSortInUrl(tbl, th, index, dir){
    const params = new URLSearchParams(window.location.search);
    const key = th.dataset.sortKey || ('c'+index);
    params.set('sort', key);
    params.set('dir', dir);
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', newUrl);
  }

  function findSortIndexByParam(tbl){
    const params = new URLSearchParams(window.location.search);
    const wantKey = params.get('sort');
    const dir = (params.get('dir')||'').toLowerCase() === 'asc' ? 'asc' : (params.get('dir')||'').toLowerCase() === 'desc' ? 'desc' : '';
    if(!tbl.tHead) return null;
    const ths = Array.from(tbl.tHead.rows[0].cells);
    if(wantKey){
      const idx = ths.findIndex(th => (th.dataset.sortKey||'') === wantKey || ('c'+ths.indexOf(th)) === wantKey);
      if(idx >= 0) return { index: idx, dir: dir || 'asc' };
    }
    return null;
  }

  function applyInitialSort(tbl){
    if(!tbl.tHead || !tbl.tBodies[0]) return;
    const ths = Array.from(tbl.tHead.rows[0].cells);
    // URL first
    const found = findSortIndexByParam(tbl);
    if(found){
      const th = ths[found.index];
      updateSortIndicators(ths, found.index, found.dir);
      sortTable(tbl, th, found.index, found.dir);
      return;
    }
    // Default from dataset
    const defKey = tbl.dataset.defaultSort;
    const defDir = (tbl.dataset.defaultDir||'asc').toLowerCase();
    if(defKey){
      const idx = ths.findIndex(th => (th.dataset.sortKey||'') === defKey);
      if(idx>=0){
        const th = ths[idx];
        updateSortIndicators(ths, idx, defDir);
        sortTable(tbl, th, idx, defDir);
      }
    }
  }

  function addDataLabels(tbl){
    const thead = tbl.tHead; if(!thead) return;
    const headers = Array.from(thead.rows[0].cells).map(th => th.textContent.trim());
    tbl.querySelectorAll('tbody tr').forEach(tr => {
      Array.from(tr.cells).forEach((td,i)=>{
        if(!td.hasAttribute('data-label') && headers[i]){
          td.setAttribute('data-label', headers[i]);
        }
      });
    });
  }

  function initTable(tbl){
    tbl.setAttribute('role', 'table');
    const thead = tbl.tHead; const tbody = tbl.tBodies[0];
    if(!thead || !tbody) return;
    const ths = Array.from(thead.rows[0].cells);
    ths.forEach((th, idx) => {
      th.setAttribute('scope', 'col');
      if(th.classList.contains('col-actions')) return;
      if(th.classList.contains('is-sortable')){
        th.tabIndex = 0;
        th.addEventListener('click', () => {
          const dir = th.classList.contains('is-asc') ? 'desc' : 'asc';
          updateSortIndicators(ths, idx, dir);
          sortTable(tbl, th, idx, dir);
          persistSortInUrl(tbl, th, idx, dir);
        });
        th.addEventListener('keydown', (e) => {
          if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); th.click(); }
        });
      }
    });
    addDataLabels(tbl);
  }

  ready(function(){
    document.querySelectorAll('table.tbl').forEach(tbl => {
      initTable(tbl);
      applyInitialSort(tbl);
    });
    const onResize = debounce(()=>{
      document.querySelectorAll('table.tbl').forEach(addDataLabels);
    }, 200);
    window.addEventListener('resize', onResize);
  });
})();
