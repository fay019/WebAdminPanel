(() => {
    const EP = {
        status: '/api/energy/status',
        hdmi:   '/api/energy/toggle/hdmi',
        wifi:   '/api/energy/toggle/wifi',
        bt:     '/api/energy/toggle/bt',
    };

    const $ = (s) => document.querySelector(s);
    const $$ = (s) => Array.from(document.querySelectorAll(s));
    const $status = $('#ps-status');
    const $wifi   = $('#ps-wifi');
    const $bt     = $('#ps-bt');
    const hdmiBtns = $$('.ps-hdmi');
    const show = (el, v) => el && (el.style.display = v ? '' : 'none');

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.CSRF_TOKEN||'');

    const setPressed = (el, on) => el && el.setAttribute('aria-pressed', on ? 'true' : 'false');
    const setDisabled = (el, dis) => el && (dis ? el.setAttribute('disabled','disabled') : el.removeAttribute('disabled'));

    const fetchJSON = async (url, opts={}) => {
        const r = await fetch(url, {
            credentials: 'same-origin',
            headers: {'Accept':'application/json', ...(opts.headers||{})},
            ...opts
        });
        const ct = r.headers.get('content-type')||'';
        if (!ct.includes('application/json')) throw new Error(await r.text() || `HTTP ${r.status}`);
        return r.json();
    };

    const render = (j) => {
        const hdmiTxt = (j.hdmi === 1 ? 'on' : (j.hdmi === 0 ? 'off' : 'n/a'));
        if ($status) $status.textContent = `HDMI: ${hdmiTxt} · Wi-Fi: ${j.wifi} · BT: ${j.bluetooth}`;

        const map = j.hdmi_map || {};
        hdmiBtns.forEach(btn => {
            const out = btn.dataset.output;
            const st  = map[out]; // "on" | "off" | undefined
            if (st === 'on') {
                setPressed(btn, true);
                btn.classList.add('ok');
                setDisabled(btn, false);
                show(btn, true);
            } else if (st === 'off') {
                setPressed(btn, false);
                btn.classList.remove('ok');
                setDisabled(btn, false);
                show(btn, true);
            } else {
                // Sortie inconnue/non connectée → masquer
                setPressed(btn, false);
                btn.classList.remove('ok');
                setDisabled(btn, true);
                show(btn, false);
            }
        });

        // Wi-Fi / BT coloration
        if ($wifi) {
            setPressed($wifi, j.wifi === 'on');
            j.wifi === 'on' ? $wifi.classList.add('ok') : $wifi.classList.remove('ok');
        }
        if ($bt) {
            setPressed($bt, j.bluetooth === 'on');
            j.bluetooth === 'on' ? $bt.classList.add('ok') : $bt.classList.remove('ok');
        }
    };

    const getStatus = () => fetchJSON(EP.status).then(render).catch(() => {
        if ($status) $status.textContent = 'Statut indisponible';
    });

    const postForm = (url, data) => {
        const body = new URLSearchParams({_token: CSRF, ...data});
        return fetchJSON(url, {
            method: 'POST',
            headers: {
                'Content-Type':'application/x-www-form-urlencoded',
                'X-Requested-With':'XMLHttpRequest',
                'X-CSRF-Token': CSRF
            },
            body
        }).then(render);
    };

    // HDMI (2 boutons, chacun cible sa sortie)
    hdmiBtns.forEach(btn => {
        btn.addEventListener('click', async () => {
            if (btn.hasAttribute('disabled')) {
                if ($status) $status.textContent = `Sortie ${btn.dataset.output} introuvable ou non connectée.`;
                return;
            }
            // Anti-flicker: ne toggler que si changement d'état souhaité
            const next = btn.getAttribute('aria-pressed') === 'true' ? '0' : '1';
            await postForm(EP.hdmi, { value: next, output: btn.dataset.output });
        });
    });

    if ($wifi) $wifi.addEventListener('click', () => {
        const next = $wifi.getAttribute('aria-pressed') === 'true' ? 'off' : 'on';
        return postForm(EP.wifi, { value: next });
    });

    if ($bt) $bt.addEventListener('click', () => {
        const next = $bt.getAttribute('aria-pressed') === 'true' ? 'off' : 'on';
        return postForm(EP.bt, { value: next });
    });

    document.addEventListener('DOMContentLoaded', () => {
        getStatus();
        setInterval(getStatus, 10000);
    });
})();
