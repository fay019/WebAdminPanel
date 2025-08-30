// Modale de confirmation stylée + fallback confirm()
// Fonctionne avec <a data-confirm="..."> ou confirmDelete(event,'...')

(function () {
    function fallbackConfirm(ev, message) {
        if (!confirm(message || 'Confirmer ?')) {
            if (ev) ev.preventDefault();
            return false;
        }
        return true;
    }

    const modal = document.getElementById('confirm-modal');
    const msgEl = modal ? document.getElementById('cm-message') : null;
    const okBtn = modal ? document.getElementById('cm-okay') : null;
    const cancel = modal ? document.getElementById('cm-cancel') : null;

    let lastFocus = null;

    function openModal(message, href) {
        if (!modal || !msgEl || !okBtn) return false; // fallback plus tard
        msgEl.textContent = message || 'Confirmer ?';
        okBtn.setAttribute('href', href || '#');
        lastFocus = document.activeElement;
        modal.hidden = false;
        okBtn.focus();
        document.addEventListener('keydown', onKey);
        return true;
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.removeEventListener('keydown', onKey);
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function onKey(e) {
        if (e.key === 'Escape') closeModal();
    }

    if (modal) {
        cancel.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    // API globale (compat rétro de tes onclick="confirmDelete(...)")
    window.confirmDelete = function (ev, message) {
        const link = ev?.currentTarget || ev?.target;
        const href = link && link.closest ? (link.closest('a')?.getAttribute('href') || '#') : '#';
        if (openModal(message, href)) {
            if (ev) ev.preventDefault();
            return false;
        }
        // Fallback modal absente
        return fallbackConfirm(ev, message);
    };

    // Version sans inline JS : <a data-confirm="...">
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-confirm]');
        if (!a) return;

        const msg = a.getAttribute('data-confirm') || 'Confirmer ?';

        // On bloque la navigation d’abord
        e.preventDefault();

        // Si tu as un modal custom
        if (typeof openModal === 'function' && openModal(msg, a.href)) {
            return; // le modal gère la suite
        }

        // Fallback : confirm() natif OU ta fonction fallbackConfirm
        if (typeof fallbackConfirm === 'function') {
            if (fallbackConfirm(e, msg)) window.location.href = a.href;
            return;
        }

        if (window.confirm(msg)) {
            window.location.href = a.href;
        }
    });

    // Confirmation pour <form data-confirm="...">
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;
        const msg = form.getAttribute('data-confirm') || 'Confirmer ?';
        // Empêche l'envoi immédiat
        e.preventDefault();
        // Utilise le modal si présent
        if (typeof openModal === 'function' && openModal(msg)) {
            if (okBtn) {
                const onOk = function (ev) {
                    ev.preventDefault();
                    okBtn.removeEventListener('click', onOk);
                    closeModal();
                    form.submit();
                };
                okBtn.addEventListener('click', onOk, {once: true});
            }
            return;
        }
        // Fallback confirm natif
        if (typeof fallbackConfirm === 'function') {
            if (fallbackConfirm(e, msg)) form.submit();
            return;
        }
        if (window.confirm(msg)) form.submit();
    });
})();
// --- Dashboard live sysinfo (polling) ---
(function () {
    const url = window.SYSINFO_URL;
    if (!url) return; // pas sur le dashboard

    const elTemp = document.getElementById('cpuTempVal');
    const elRam = document.getElementById('ramVal');
    const elLoad = document.getElementById('cpuLoadVal');

    if (!elTemp && !elRam && !elLoad) return;

    function setText(el, txt) {
        if (!el) return;
        el.textContent = txt;
    }

    function fmtRam(obj) {
        if (obj && typeof obj === 'object' && 'used_mb' in obj && 'total_mb' in obj) {
            const used = Math.round(obj.used_mb);
            const total = Math.round(obj.total_mb);
            const pct = obj.percent ? String(obj.percent).replace(/\.0+$/, '') : Math.round(used * 100 / Math.max(total, 1)) + '%';
            return `${used}MB / ${total}MB (${pct})`;
        }
        if (typeof obj === 'string') return obj; // ex: "743MB / 16219MB (5%)"
        return 'n/a';
    }

    // Parse un éventuel format texte "clé=valeur" (une paire par ligne)
    function parseKV(text) {
        const out = {};
        text.split(/\r?\n/).forEach(line => {
            const i = line.indexOf('=');
            if (i <= 0) return;
            const k = line.slice(0, i).trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_');
            const v = line.slice(i + 1).trim();
            out[k] = v;
        });
        return out;
    }

    // Essaie JSON, sinon retombe sur clé=valeur
    async function fetchSysinfo() {
        const r = await fetch(url, {cache: 'no-store'});
        if (!r.ok) throw new Error('http ' + r.status);
        const raw = await r.text();
        try {
            return JSON.parse(raw);
        } catch {
            return parseKV(raw);
        }
    }

    function pickTemp(d) {
        // Accepte: cpu_temp | cpu-temp | cpu.temp_c | cpuTemp
        return d?.cpu_temp ?? d?.['cpu-temp'] ?? d?.cpu?.temp_c ?? d?.cpuTemp ?? 'n/a';
    }

    function pickLoad(d) {
        // Accepte: cpu_load | load | cpu.load_pct | cpuLoad
        return d?.cpu_load ?? d?.load ?? d?.cpu?.load_pct ?? d?.cpuLoad ?? 'n/a';
    }

    function pickRam(d) {
        return fmtRam(d?.ram ?? d?.mem ?? d?.memory);
    }

    async function tick() {
        try {
            const data = await fetchSysinfo();
            setText(elTemp, String(pickTemp(data)));
            setText(elRam, pickRam(data));
            setText(elLoad, String(pickLoad(data)));
        } catch (e) {
            // silencieux
        }
    }

    tick();
    setInterval(tick, 2000); // MAJ toutes les 2s
})();

// --- Install PHP: stream via fetch dans la modale ---
document.addEventListener('DOMContentLoaded', () => {
    // --- Nav responsive toggle ---
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('mainNav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const isOpen = nav.classList.toggle('show');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        // Ferme le menu après un clic sur un lien
        nav.addEventListener('click', (e) => {
            const a = e.target.closest('a');
            if (!a) return;
            if (window.matchMedia('(max-width: 768px)').matches) {
                nav.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    const form = document.getElementById('installForm');
    if (!form) return;

    const overlay = document.getElementById('busyOverlay');
    const preLog = document.getElementById('busyLog');
    const btnClose = document.getElementById('busyClose');
    const titleEl = document.getElementById('busyTitle');

    function openOverlay(title) {
        if (titleEl && title) titleEl.textContent = title;
        if (preLog) preLog.textContent = '';
        overlay?.setAttribute('aria-hidden', 'false');
        if (overlay) overlay.style.display = 'block';
        if (btnClose) btnClose.disabled = true;
    }

    function endOverlay() {
        if (btnClose) btnClose.disabled = false;
    }

    function closeOverlay() {
        overlay?.setAttribute('aria-hidden', 'true');
        if (overlay) overlay.style.display = 'none';
    }

    function setOverlayErrorState(on) {
        const dot = overlay?.querySelector('.ok-dot');
        if (!dot || !titleEl) return;
        if (on) {
            dot.style.background = '#ef4444';
            titleEl.textContent = 'Erreur pendant l\'installation';
        } else {
            dot.style.background = '#60a5fa';
            titleEl.textContent = 'Installation en cours…';
        }
    }

    async function streamInstall(submitterBtn) {
        // Normaliser URL de cible de façon sûre (éviter [object HTMLButtonElement])
        let actionAttr = (form.getAttribute('action') || '').trim();
        if (!actionAttr) actionAttr = '/php_manage.php?stream=1';
        // Construire une URL absolue pour fetch
        let targetUrl;
        try {
            targetUrl = new URL(actionAttr, window.location.origin).toString();
        } catch (e) {
            targetUrl = '/php_manage.php?stream=1';
            console.warn('installForm action invalide, fallback vers', targetUrl);
        }
        // Construire la FormData avec le "submitter" (le bouton cliqué)
        const fd = new FormData(form);

        // Ajouter le bouton cliqué pour avoir action=install
        if (submitterBtn && submitterBtn.name) {
            fd.append(submitterBtn.name, submitterBtn.value || 'install');
        }
        // Sécurité : si aucune action n’est passée, forcer install
        if (!fd.has('action')) fd.append('action', 'install');

        // Forcer le mode stream côté PHP
        fd.set('ajax', '1');

        try {
            const resp = await fetch(targetUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {'Accept': 'text/plain'}
            });

            // Si on a été redirigé ou si le serveur renvoie du HTML
            const ct = resp.headers.get('content-type') || '';
            if (resp.redirected || ct.includes('text/html')) {
                // Ne pas naviguer : garder la modale ouverte et afficher une explication
                setOverlayErrorState(true);
                endOverlay();
                const target = resp.url || '/';
                if (preLog) {
                    preLog.textContent += `\n[ERREUR] Réponse non-stream (HTML/redirect) reçue.\n`;
                    preLog.textContent += `Cible: ${target}\n`;
                    preLog.textContent += `Conseils: vérifiez la session (login), le token CSRF, ou les redirections de votre serveur.\n`;
                }
                return;
            }

            // Lire le flux en streaming (text/plain attendu)
            const reader = resp.body?.getReader();
            const decoder = new TextDecoder();
            let gotAny = false;
            while (reader) {
                const {value, done} = await reader.read();
                if (done) break;
                const chunk = decoder.decode(value, {stream: true});
                if (chunk) gotAny = true;
                if (preLog) {
                    preLog.textContent += chunk;
                    preLog.scrollTop = preLog.scrollHeight;
                }
                // Détecte immédiatement une ligne d'erreur
                if (chunk && /\[(ERREUR|ERROR)\]/i.test(chunk)) {
                    setOverlayErrorState(true);
                }
            }
            // Si aucune sortie reçue, suggérer la vérification sudoers
            if (!gotAny && preLog) {
                setOverlayErrorState(true);
                preLog.textContent += "\n[INFO] Aucune sortie reçue. Vérifiez que sudo autorise /var/www/adminpanel/bin/php_manage.sh (install.sh déploie sudoers).";
            }
        } catch (e) {
            if (preLog) preLog.textContent += `\n[ERREUR] ${e?.message || e}`;
        } finally {
            endOverlay();
        }
    }

    // Intercepter le submit du formulaire (y compris “Entrée”)
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitter = e.submitter; // bouton réellement cliqué
        openOverlay('Installation en cours…');
        // Lancer le stream
        streamInstall(submitter);
    });

    // Mémoriser le dernier bouton cliqué pour Safari qui ne renseigne pas toujours e.submitter
    let lastSubmitter = null;
    form.addEventListener('click', (ev) => {
        const btn = ev.target && ev.target.closest('button, input[type="submit"]');
        if (btn && form.contains(btn)) {
            lastSubmitter = btn;
        }
    }, true);

    // Intercepter le submit et utiliser lastSubmitter si e.submitter indisponible
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitter = e.submitter || lastSubmitter; // Safari fallback
        openOverlay('Installation en cours…');
        streamInstall(submitter);
    });

    // Au clic sur le bouton “Installer”, soumettre le formulaire proprement
    const installBtn = form.querySelector('[data-install]');
    if (installBtn) {
        installBtn.addEventListener('click', (ev) => {
            // Utiliser la méthode standard si dispo, sinon fallback
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(installBtn);
            } else {
                lastSubmitter = installBtn;
                form.dispatchEvent(new Event('submit', {cancelable: true}));
            }
        });
    }

    // Bouton “Fermer” : cache la modale et refresh la page
    btnClose?.addEventListener('click', () => {
        closeOverlay();
        // recharger pour rafraîchir la liste des versions détectées
        location.reload();
    });
});