// Modale de confirmation stylée + fallback confirm()
// Fonctionne avec <a data-confirm="...">, <form data-confirm="...">,
// <button data-confirm> et avec confirmDelete(event,'...')

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
        if (href) okBtn.setAttribute('href', href);
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
        if (okBtn) okBtn.removeAttribute('href');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function onKey(e) {
        if (e.key === 'Escape') closeModal();
    }

    if (modal) {
        cancel?.addEventListener('click', closeModal);
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

    // Confirmation pour <a data-confirm="...">
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-confirm]');
        if (!a) return;

        const msg = a.getAttribute('data-confirm') || 'Confirmer ?';
        const actionUrl = (a.getAttribute('href') || '#').trim();
        let isPower = false;
        try { isPower = new URL(actionUrl, window.location.origin).pathname.endsWith('/system_power.php'); }
        catch { isPower = actionUrl.includes('system_power.php'); }
        const action = a.getAttribute('data-action'); // shutdown|reboot
        const csrf   = a.getAttribute('data-csrf') || '';
        const ajax   = a.getAttribute('data-ajax') || '1';

        e.preventDefault();

        // Cas spécial: lien power → on ne navigue pas, on poste via JS après confirmation
        if (isPower && action) {
            if (openModal(msg)) {
                if (okBtn) {
                    const onOk = function (ev) {
                        ev.preventDefault();
                        closeModal();
                        // Construit un formulaire temporaire pour réutiliser le flux existant
                        const form = document.createElement('form');
                        form.setAttribute('method', 'POST');
                        form.setAttribute('action', actionUrl);
                        const f1 = document.createElement('input'); f1.type='hidden'; f1.name='csrf'; f1.value=csrf; form.appendChild(f1);
                        const f2 = document.createElement('input'); f2.type='hidden'; f2.name='ajax'; f2.value=ajax; form.appendChild(f2);
                        const f3 = document.createElement('input'); f3.type='hidden'; f3.name='action'; f3.value=action; form.appendChild(f3);
                        document.dispatchEvent(new CustomEvent('power:submit', { detail: { form, actionUrl } }));
                    };
                    okBtn.addEventListener('click', onOk, { once: true });
                }
                return;
            }
            // Fallback : confirm() natif
            if (fallbackConfirm(e, msg)) {
                const form = document.createElement('form');
                form.setAttribute('method', 'POST');
                form.setAttribute('action', actionUrl);
                const f1 = document.createElement('input'); f1.type='hidden'; f1.name='csrf'; f1.value=csrf; form.appendChild(f1);
                const f2 = document.createElement('input'); f2.type='hidden'; f2.name='ajax'; f2.value=ajax; form.appendChild(f2);
                const f3 = document.createElement('input'); f3.type='hidden'; f3.name='action'; f3.value=action; form.appendChild(f3);
                document.dispatchEvent(new CustomEvent('power:submit', { detail: { form, actionUrl } }));
            }
            return;
        }

        // Cas général: liens non-power → navigation après confirmation
        if (openModal(msg, a.href)) return;

        // Fallback : confirm() natif
        if (fallbackConfirm(e, msg)) window.location.href = a.href;
    });

    // Confirmation pour <form data-confirm="...">
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;

        const msg = form.getAttribute('data-confirm') || 'Confirmer ?';
        const submitter = e.submitter || null;
        e.preventDefault();

        if (openModal(msg)) {
            if (okBtn) {
                const onOk = function (ev) {
                    ev.preventDefault();
                    // Détermine si c'est un formulaire power
                    let actionUrl = (form.getAttribute('action') || '/system_power.php').trim();
                    let isPower = false;
                    try { isPower = new URL(actionUrl, window.location.origin).pathname.endsWith('/system_power.php'); }
                    catch { isPower = actionUrl.includes('system_power.php'); }

                    closeModal();
                    if (isPower && window.SYSINFO_URL) {
                        const detail = {
                            form,
                            actionUrl,
                            submitterName: submitter?.name || null,
                            submitterValue: submitter?.value || null
                        };
                        document.dispatchEvent(new CustomEvent('power:submit', { detail }));
                    } else {
                        // Préserver le bouton cliqué (name/value) pour le submit normal
                        let temp;
                        if (submitter && submitter.name) {
                            temp = document.createElement('input');
                            temp.type = 'hidden';
                            temp.name = submitter.name;
                            temp.value = submitter.value || '1';
                            form.appendChild(temp);
                        }
                        form.submit();
                        if (temp) temp.remove();
                    }
                };
                okBtn.addEventListener('click', onOk, { once: true });
            }
            return;
        }

        // Fallback
        if (fallbackConfirm(e, msg)) {
            let actionUrl = (form.getAttribute('action') || '/system_power.php').trim();
            let isPower = false;
            try { isPower = new URL(actionUrl, window.location.origin).pathname.endsWith('/system_power.php'); }
            catch { isPower = actionUrl.includes('system_power.php'); }
            if (isPower) {
                const detail = {
                    form,
                    actionUrl,
                    submitterName: submitter?.name || null,
                    submitterValue: submitter?.value || null
                };
                document.dispatchEvent(new CustomEvent('power:submit', { detail }));
            } else {
                let temp;
                if (submitter && submitter.name) {
                    temp = document.createElement('input');
                    temp.type = 'hidden';
                    temp.name = submitter.name;
                    temp.value = submitter.value || '1';
                    form.appendChild(temp);
                }
                form.submit();
                if (temp) temp.remove();
            }
        }
    });

    // ✅ Confirmation pour <button data-confirm> (et <input type="submit" data-confirm>)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-confirm], input[type="submit"][data-confirm]');
        if (!btn) return;
        // Ne pas intercepter les boutons power (gérés par un flux dédié)
        if (btn.hasAttribute('data-power')) return;
        // Laisser les boutons qui demandent un flux (data-stream) être gérés par leur module dédié (ex: php_manage.js)
        const form = btn.closest('form');
        if (btn.hasAttribute('data-stream') || (form && form.hasAttribute('data-stream'))) return;

        const msg = btn.getAttribute('data-confirm') || 'Confirmer ?';
        e.preventDefault();

        // Action après OK (soumettre le form ou naviguer)
        const proceed = () => {
            const form = btn.closest('form');
            if (form) {
                // Soumission standard: s'assurer que le name/value du bouton est transmis
                let temp;
                if (btn.name) {
                    temp = document.createElement('input');
                    temp.type = 'hidden';
                    temp.name = btn.name;
                    temp.value = btn.value || '1';
                    form.appendChild(temp);
                }
                form.submit();
                if (temp) temp.remove();
            } else {
                const href = btn.getAttribute('data-href') || btn.getAttribute('formaction') || btn.getAttribute('href');
                if (href) window.location.href = href;
            }
        };

        if (openModal(msg)) {
            if (okBtn) {
                const onOk = (ev) => {
                    ev.preventDefault();
                    closeModal();
                    proceed();
                };
                okBtn.addEventListener('click', onOk, { once: true });
            }
            return;
        }

        // Fallback
        if (fallbackConfirm(e, msg)) proceed();
    });
})();

// --- Gestion générique des modales ---
(function () {
    function hideModal(modal) {
        if (!modal) return;
        modal.hidden = true;
    }

    // Clic sur tout élément avec [data-close-modal]
    document.addEventListener('click', function (e) {
        const closeBtn = e.target.closest('[data-close-modal]');
        if (!closeBtn) return;
        const modal = closeBtn.closest('.modal');
        if (modal) hideModal(modal);
    });

    // Clic sur l’overlay (fond assombri)
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('modal')) {
            hideModal(e.target);
        }
    });

    // Touche Échap pour fermer la dernière modale visible
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const openModals = Array.from(document.querySelectorAll('.modal:not([hidden])'));
        if (!openModals.length) return;
        hideModal(openModals[openModals.length - 1]);
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
        const r = await fetch(url, { cache: 'no-store' });
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
        // Accepte: cpu_load | load | cpu.load_pct | cpuLoad | loadavg.1m
        if (d?.cpu_load != null) return d.cpu_load;
        if (d?.load != null) return d.load;
        if (d?.cpu?.load_pct != null) return d.cpu.load_pct;
        if (d?.cpuLoad != null) return d.cpuLoad;
        if (d?.loadavg && (d.loadavg['1m'] != null)) return d.loadavg['1m'];
        return 'n/a';
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
        // Normaliser l’URL cible (éviter [object HTMLButtonElement])
        let actionAttr = (form.getAttribute('action') || '').trim();
        if (!actionAttr) actionAttr = '/php_manage.php?stream=1';

        let targetUrl;
        try {
            targetUrl = new URL(actionAttr, window.location.origin).toString();
        } catch {
            targetUrl = '/php_manage.php?stream=1';
            console.warn('installForm action invalide, fallback vers', targetUrl);
        }

        const fd = new FormData(form);

        // Ajouter le bouton cliqué pour avoir action=install
        if (submitterBtn && submitterBtn.name) {
            fd.append(submitterBtn.name, submitterBtn.value || 'install');
        }
        if (!fd.has('action')) fd.append('action', 'install');

        // Forcer le mode stream côté PHP
        fd.set('ajax', '1');

        try {
            const resp = await fetch(targetUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'Accept': 'text/plain' }
            });

            // Si redirection ou HTML → on n’est pas en stream
            const ct = resp.headers.get('content-type') || '';
            if (resp.redirected || ct.includes('text/html')) {
                setOverlayErrorState(true);
                endOverlay();
                const target = resp.url || '/';
                if (preLog) {
                    preLog.textContent += `\n[ERREUR] Réponse non-stream (HTML/redirect) reçue.\n`;
                    preLog.textContent += `Cible: ${target}\n`;
                    preLog.textContent += `Conseils: vérifiez la session (login), le token CSRF, ou les redirections.\n`;
                }
                return;
            }

            // Lecture en streaming
            const reader = resp.body?.getReader();
            const decoder = new TextDecoder();
            let gotAny = false;
            while (reader) {
                const { value, done } = await reader.read();
                if (done) break;
                const chunk = decoder.decode(value, { stream: true });
                if (chunk) gotAny = true;
                if (preLog) {
                    preLog.textContent += chunk;
                    preLog.scrollTop = preLog.scrollHeight;
                }
                if (chunk && /\[(ERREUR|ERROR)\]/i.test(chunk)) {
                    setOverlayErrorState(true);
                }
            }
            if (!gotAny && preLog) {
                setOverlayErrorState(true);
                preLog.textContent += "\n[INFO] Aucune sortie reçue. Vérifiez sudoers pour php_manage.sh.";
            }
        } catch (e) {
            if (preLog) preLog.textContent += `\n[ERREUR] ${e?.message || e}`;
        } finally {
            endOverlay();
        }
    }

    // ✅ Un seul listener submit (gère aussi Safari via lastSubmitter)
    let lastSubmitter = null;

    form.addEventListener('click', (ev) => {
        const btn = ev.target && ev.target.closest('button, input[type="submit"]');
        if (btn && form.contains(btn)) {
            lastSubmitter = btn;
        }
    }, true);

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const submitter = e.submitter || lastSubmitter; // Safari fallback
        openOverlay('Installation en cours…');
        streamInstall(submitter);
    });

    // Bouton “Fermer” : cache la modale et refresh la page
    btnClose?.addEventListener('click', () => {
        closeOverlay();
        location.reload();
    });
});