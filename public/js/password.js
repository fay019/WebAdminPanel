(function(){
  function randChar(pool){ return pool[Math.floor(Math.random()*pool.length)]; }
  function buildPassword(len){
    const lower='abcdefghjkmnpqrstuvwxyz';
    const upper='ABCDEFGHJKMNPQRSTUVWXYZ';
    const nums='23456789';
    const pools=[lower,upper,nums];
    const all=lower+upper+nums;
    let pass='';
    // ensure at least one from each
    pass += randChar(lower);
    pass += randChar(upper);
    pass += randChar(nums);
    for(let i=3;i<len;i++){ pass += randChar(all); }
    // shuffle
    return pass.split('').sort(()=>Math.random()-0.5).join('');
  }
  function fillInputs(pw){
    const p1 = document.querySelector('input[name="password"]');
    const p2 = document.querySelector('input[name="confirm"]');
    if(p1){ p1.value = pw; }
    if(p2){ p2.value = pw; }
    // small feedback
    const span = document.createElement('span');
    span.textContent = 'Mot de passe généré';
    span.className = 'small muted';
    const tools = document.querySelector('[data-pass-tools]');
    if(tools){ tools.appendChild(span); setTimeout(()=>span.remove(), 2000); }
  }
  window.generatePassword = function(opts){
    opts = opts || {}; var len = Math.max(8, parseInt(opts.length||12,10)||12);
    const pw = buildPassword(len);
    fillInputs(pw);
    return pw;
  };
})();
