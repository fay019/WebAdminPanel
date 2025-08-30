// Password generator module for Mini Web Panel
// Isolated, no dependencies. Binds to containers having [data-pass-tools]
// Expects inputs named "password" and "confirm" within the same form.
(function(){
  'use strict';

  const SYMBOLS = '!@#$%^&*()-_=+[]{};:,.?';
  const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // no I, O to avoid ambiguity
  const LOWER = 'abcdefghijkmnpqrstuvwxyz'; // no l, o
  const DIGITS = '23456789'; // no 0,1

  function randInt(max){ return crypto.getRandomValues(new Uint32Array(1))[0] % max; }

  function shuffle(arr){
    for(let i=arr.length-1;i>0;i--){ const j = randInt(i+1); [arr[i],arr[j]]=[arr[j],arr[i]]; }
    return arr;
  }

  function generatePassword(len=16){
    // Ensure at least one of each class
    const pools = [UPPER, LOWER, DIGITS, SYMBOLS];
    const out = [
      UPPER[randInt(UPPER.length)],
      LOWER[randInt(LOWER.length)],
      DIGITS[randInt(DIGITS.length)],
      SYMBOLS[randInt(SYMBOLS.length)]
    ];
    const all = pools.join('');
    while(out.length < len){ out.push(all[randInt(all.length)]); }
    return shuffle(out).join('');
  }

  function scorePassword(p){
    if(!p) return 0;
    let score = 0;
    const len = p.length;
    const hasLower = /[a-z]/.test(p);
    const hasUpper = /[A-Z]/.test(p);
    const hasDigit = /\d/.test(p);
    const hasSymbol = /[^A-Za-z0-9]/.test(p);
    const variety = [hasLower,hasUpper,hasDigit,hasSymbol].filter(Boolean).length;
    if(len >= 8) score++;
    if(len >= 12) score++;
    if(variety >= 3) score++;
    if(variety === 4 && len >= 16) score++;
    return Math.min(4, score);
  }

  function strengthText(n){
    switch(n){
      case 0: return 'Très faible';
      case 1: return 'Faible';
      case 2: return 'Moyen';
      case 3: return 'Bon';
      case 4: return 'Fort';
      default: return '';
    }
  }

  function updateStrength(meter, val){
    if(!meter) return;
    const s = scorePassword(val);
    meter.dataset.score = String(s);
    meter.textContent = 'Force: ' + strengthText(s);
    meter.classList.remove('pw-s0','pw-s1','pw-s2','pw-s3','pw-s4');
    meter.classList.add('pw-s'+s);
  }

  async function copyToClipboard(text){
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch {}
    try {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position='fixed'; ta.style.opacity='0';
      document.body.appendChild(ta); ta.focus(); ta.select();
      const ok = document.execCommand('copy');
      ta.remove();
      return ok;
    } catch { return false; }
  }

  function initTools(container){
    const form = container.closest('form');
    if(!form) return;
    const pw = form.querySelector('input[name="password"]');
    const cf = form.querySelector('input[name="confirm"]');
    if(!pw) return;
    const genBtn = container.querySelector('[data-action="generate"]');
    const toggleBtn = container.querySelector('[data-action="toggle"]');
    const copyBtn = container.querySelector('[data-action="copy"]');
    const meter = container.querySelector('[data-role="strength"]');

    // Update meter on typing
    const onInput = () => updateStrength(meter, pw.value);
    pw.addEventListener('input', onInput);

    // Initial state
    updateStrength(meter, pw.value);

    genBtn?.addEventListener('click', (e)=>{
      e.preventDefault();
      const g = generatePassword(16);
      pw.value = g;
      if (cf) cf.value = g;
      updateStrength(meter, g);
      // focus password to make it easy to inspect
      pw.type = 'text';
      toggleBtn && (toggleBtn.dataset.state='show', toggleBtn.textContent='Masquer');
      pw.focus();
    });

    toggleBtn?.addEventListener('click', (e)=>{
      e.preventDefault();
      const show = (pw.type === 'password');
      pw.type = show ? 'text' : 'password';
      if (cf) cf.type = show ? 'text' : 'password';
      toggleBtn.dataset.state = show ? 'show' : 'hide';
      toggleBtn.textContent = show ? 'Masquer' : 'Afficher';
    });

    copyBtn?.addEventListener('click', async (e)=>{
      e.preventDefault();
      if (!pw.value) return;
      const ok = await copyToClipboard(pw.value);
      const orig = copyBtn.textContent;
      copyBtn.textContent = ok ? 'Copié ✓' : 'Erreur copie';
      copyBtn.disabled = true;
      setTimeout(()=>{ copyBtn.textContent = orig || 'Copier'; copyBtn.disabled = false; }, 1200);
    });
  }

  function boot(){
    document.querySelectorAll('[data-pass-tools]').forEach(initTools);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
