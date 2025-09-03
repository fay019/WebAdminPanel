// public/js/energy.js
(() => {
    const EP = {
        status: '/energy/status',
        hdmi:   '/energy/toggle/hdmi',
        wifi:   '/energy/toggle/wifi',
        bt:     '/energy/toggle/bt',
    };

    const $ = (sel) => document.querySelector(sel);
    const $status = $('#ps-status');
    const $hdmi   = $('#ps-hdmi');
    const $wifi   = $('#ps-wifi');
    const $bt     = $('#ps-bt');
    const csrf    = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const setBtn = (el, on) => {
        if (!el) return;
        el.classList.toggle('is-on',  on);
        el.classList.toggle('is-off', !on);
        el.setAttribute('aria-pressed', on ? 'true' : 'false');
        el.dataset.on = on ? '1' : '0';
    };

    const render = (j) => {
        const hdmiTxt = j.hdmi === 1 ? 'on' : (j.hdmi === 0 ? 'off' : 'unsupported');
        $status.textContent = `HDMI: ${hdmiTxt} · Wi-Fi: ${j.wifi} · Bluetooth: ${j.bluetooth}`;
        setBtn($hdmi, j.hdmi === 1);
        setBtn($wifi, j.wifi === 'on');
        setBtn($bt,   j.bluetooth === 'on');
    };

    const fetchJSON = async (url, opts={}) => {
        const r = await fetch(url, opts);
        return r.json();
    };

    const getStatus = () => fetchJSON(EP.status).then(render).catch(() => {
        $status.textContent = 'Statut indisponible';
    });

    const postValue = (url, value) => {
        const body = new URLSearchParams({_token: csrf, value});
        return fetchJSON(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
            body
        }).then(render);
    };

    const withBusy = (el, fn) => async () => {
        if (!el) return;
        el.setAttribute('aria-busy', 'true');
        try { await fn(); } finally { el.removeAttribute('aria-busy'); }
    };

    if ($hdmi) $hdmi.addEventListener('click', withBusy($hdmi, () => {
        const next = $hdmi.dataset.on === '1' ? '0' : '1';
        return postValue(EP.hdmi, next);
    }));

    if ($wifi) $wifi.addEventListener('click', withBusy($wifi, () => {
        const next = $wifi.dataset.on === '1' ? 'off' : 'on';
        return postValue(EP.wifi, next);
    }));

    if ($bt) $bt.addEventListener('click', withBusy($bt, () => {
        const next = $bt.dataset.on === '1' ? 'off' : 'on';
        return postValue(EP.bt, next);
    }));

    // Init + refresh périodique
    document.addEventListener('DOMContentLoaded', () => {
        getStatus();
        setInterval(getStatus, 10000);
    });
})();