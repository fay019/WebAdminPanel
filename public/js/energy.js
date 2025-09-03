(() => {
    const EP = {
        status: '/api/energy/status',
        hdmi:   '/api/energy/toggle/hdmi',
        wifi:   '/api/energy/toggle/wifi',
        bt:     '/api/energy/toggle/bt',
    };

    const $ = (sel) => document.querySelector(sel);
    const $status = $('#ps-status');
    const $hdmi   = $('#ps-hdmi');
    const $wifi   = $('#ps-wifi');
    const $bt     = $('#ps-bt');

    const CSRF =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        (window.CSRF_TOKEN || '');

    const setBtn = (el, on) => {
        if (!el) return;
        el.setAttribute('aria-pressed', on ? 'true' : 'false');
        el.dataset.on = on ? '1' : '0';
    };

    const render = (j) => {
        const hdmiTxt = j.hdmi === 1 ? 'on' : (j.hdmi === 0 ? 'off' : 'unsupported');
        if ($status) $status.textContent = `HDMI: ${hdmiTxt} · Wi-Fi: ${j.wifi} · Bluetooth: ${j.bluetooth}`;
        setBtn($hdmi, j.hdmi === 1);
        setBtn($wifi, j.wifi === 'on');
        setBtn($bt,   j.bluetooth === 'on');
    };

    const fetchJSON = async (url, opts = {}) => {
        const r = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', ...(opts.headers || {}) },
            ...opts
        });
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const t = await r.text();
            throw new Error(t || `HTTP ${r.status}`);
        }
        return r.json();
    };

    const getStatus = () =>
        fetchJSON(EP.status)
            .then(render)
            .catch(() => { if ($status) $status.textContent = 'Statut indisponible'; });

    const postValue = (url, value) => {
        const body = new URLSearchParams({ _token: CSRF, value });
        return fetchJSON(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CSRF
            },
            body
        }).then(render);
    };

    const withBusy = (el, fn) => async () => {
        if (!el) return;
        el.setAttribute('aria-busy', 'true');
        try { await fn(); } finally { el.removeAttribute('aria-busy'); }
    };

    if ($hdmi) $hdmi.addEventListener('click', withBusy($hdmi, () => {
        const next   = $hdmi.dataset.on === '1' ? '0' : '1';
        const output = (document.querySelector('#ps-output')?.value || $hdmi.dataset.output || '').trim();
        const body   = new URLSearchParams({_token: CSRF, value: next});
        if (output) body.append('output', output);
        return fetchJSON('/api/energy/toggle/hdmi', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF-Token': CSRF},
            credentials: 'same-origin',
            body
        }).then(render);
    }));


    if ($wifi) $wifi.addEventListener('click', withBusy($wifi, () => {
        const next = $wifi.dataset.on === '1' ? 'off' : 'on';
        return postValue(EP.wifi, next);
    }));

    if ($bt) $bt.addEventListener('click', withBusy($bt, () => {
        const next = $bt.dataset.on === '1' ? 'off' : 'on';
        return postValue(EP.bt, next);
    }));

    document.addEventListener('DOMContentLoaded', () => {
        getStatus();
        setInterval(getStatus, 10000);
    });
})();