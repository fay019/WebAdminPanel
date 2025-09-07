<?php /* View copied from legacy dashboard.php body to preserve UI and JS */ ?>
<script>window.SYSINFO_URL = '/api/sysinfo'; window.POWER_ENDPOINT = '/dashboard/power';
// UI configuration (can be overridden server-side without changing JS)
window.UI_CONFIG = window.UI_CONFIG || { temp: { cpu: { cold:45, hot:70 }, ambient: { cold:10, hot:25 } }, icons: { set: 'lucide' }, a11y: { forceHighContrast: false } };
</script>
<?php $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<meta name="csrf-token" content="<?= $csrf ?>">

<div class="card">
    <h2>Dashboard</h2>

    <!-- Barre d'actions: Power à gauche, Écrans/Wi‑Fi/BT à droite -->
    <div class="ps-toolbar">
        <div class="ps-left actions">
            <a href="/system_power.php?stream=1"
               class="icon-link btn btn-icon"
               data-confirm="⚠️ Éteindre la machine dans ~30s ?"
               data-action="shutdown"
               data-ajax="1"
               data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <img src="/img/power.svg" alt="Éteindre" title="Éteindre" class="icon-btn" />
            </a>

            <a href="/system_power.php?stream=1"
               class="icon-link btn btn-icon"
               data-confirm="🔄 Redémarrer la machine dans ~30s ?"
               data-action="reboot"
               data-ajax="1"
               data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <img src="/img/reload.svg" alt="Redémarrer" title="Redémarrer" class="icon-btn" />
            </a>
        </div>

        <div class="ps-right ps-row">
            <button type="button" class="ps-hdmi btn" data-output="HDMI-A-1" aria-pressed="false" title="HDMI-A-1">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18v10H3zM8 19h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>HDMI-A-1</span>
            </button>
            <button type="button" class="ps-hdmi btn" data-output="HDMI-A-2" aria-pressed="false" title="HDMI-A-2">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18v10H3zM8 19h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>HDMI-A-2</span>
            </button>

            <button type="button" id="ps-wifi" class="btn" aria-pressed="false" title="Wi-Fi">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9a12 12 0 0 1 16 0M7 12a8 8 0 0 1 10 0M10 15a4 4 0 0 1 4 0M12 19h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Wi-Fi</span>
            </button>
            <button type="button" id="ps-bt" class="btn" aria-pressed="false" title="Bluetooth">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18l6-6-6-6 6-6-6 6-6-6m6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>BT</span>
            </button>
        </div>
    </div>
    <div id="ps-status" class="small">Chargement…</div>

    <?php show_flash(); ?>

    <div class="metrics">
        <!-- Stockage — Synthèse -->
        <div class="card" id="storageCard" style="grid-column: span 2">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:8px">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7M3 7h18M7 3v4M17 3v4M7 13h2M11 13h2M15 13h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <h4 style="margin:0">Stockage — Synthèse</h4>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span id="nvmeHealth" data-nvme-health class="chip-row"></span>
                    <button type="button" class="btn small" data-action="toggle-unit" title="Basculer % / GiB">% / GiB</button>
                </div>
            </div>
            <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
                <canvas id="storagePie" width="320" height="220" aria-label="Répartition de l'utilisé par volume"></canvas>
                <div class="smallmono" id="storageTotals" style="min-width:240px"></div>
            </div>
        </div>

        <!-- Volumes (N) -->
        <div class="card" id="volumesMiniDonuts">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <h4 style="margin:0" id="volumesTitle">Volumes (0)</h4>
            </div>
            <div id="storageGrid" class="mini-donuts-grid"></div>
        </div>

        <!-- Volumes — Tableau (repliable) -->
        <div class="card" id="volumesTableCard">
            <details>
                <summary class="small" style="cursor:pointer"><strong>Volumes — Tableau</strong> (replié)</summary>
                <div style="margin-top:8px">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:6px"><button type="button" class="btn small" id="copyStorageJson">Copier JSON storage</button></div>
                    <div class="table-responsive">
                        <table class="table compact" id="volumesTable">
                            <thead><tr><th>Mountpoint</th><th>FS</th><th>Utilisé</th><th>Libre</th><th>Taille</th><th>%</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </details>
        </div>

        <!-- NVMe Health -->
        <div class="card" id="nvmeHealthCard">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:8px">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm3 0h14v8H5zM7 11h2m3 0h2m3 0h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <h4 style="margin:0">Santé NVMe</h4>
                </div>
                <span class="badge" id="nvmeHealthStatus">NA</span>
            </div>
            <div class="small" id="nvmeHealthBanner" style="display:none;margin-bottom:6px"></div>
            <div class="grid grid-2" style="gap:8px">
                <div>Température</div><div class="smallmono" id="nvmeTemp">n/a</div>
                <div>Usure</div><div class="smallmono" id="nvmeWear">n/a</div>
                <div>Erreurs média</div><div class="smallmono" id="nvmeErrors">n/a</div>
                <div>Heures de marche</div><div class="smallmono" id="nvmePOH">n/a</div>
                <div>Actualisé</div><div class="smallmono" id="nvmeTs">n/a</div>
            </div>
        </div>

        <div class="metric">
            <h4>Sites</h4>
            <div class="value"><?= $sitesCount ?></div>
        </div>

        <div class="card">
            <div class="small" style="display:flex;align-items:center;gap:6px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true"><path d="M14 14.76V5a2 2 0 1 0-4 0v9.76a4 4 0 1 0 4 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>CPU Temp</span></div>
            <div class="value big gray" id="cpuTempVal">n/a</div>
        </div>

        <div class="card">
            <div class="small" style="display:flex;align-items:center;gap:6px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true"><path d="M14 14.76V5a2 2 0 1 0-4 0v9.76a4 4 0 1 0 4 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Temp Ext</span></div>
            <div class="value big gray" id="ambientTempVal">n/a</div>
        </div>

        <div class="card">
            <div class="small">RAM</div>
            <div class="value gray" id="ramVal">n/a</div>
        </div>

        <div class="metric">
            <h4>Uptime</h4>
            <div class="value smallmono" id="uptimeVal"><?= htmlspecialchars($sysinfo['uptime'] ?? 'n/a') ?></div>
        </div>

        <div class="card">
            <div class="small">CPU Load</div>
            <div class="value gray" id="cpuLoadVal">n/a</div>
        </div>

        <div class="metric">
            <h4>Disque</h4>
            <div class="value smallmono" data-metric="diskMain"><?= htmlspecialchars($sysinfo['disk'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>OS</h4>
            <div class="value smallmono" data-metric="osPretty"><?= htmlspecialchars($sysinfo['os'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>Kernel</h4>
            <div class="value smallmono" data-metric="osKernel"><?= htmlspecialchars($sysinfo['kernel'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>Processus actifs</h4>
            <div class="value" data-metric="procCount"><?= htmlspecialchars($sysinfo['processes']) ?></div>
        </div>

        <div class="metric">
            <h4>Cœurs CPU</h4>
            <div class="value" data-metric="cpuCores"><?= htmlspecialchars($sysinfo['cpu_cores'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>Top CPU</h4>
            <div class="value ellip smallmono" data-metric="topCpu" title="<?= htmlspecialchars($sysinfo['top_cpu'] ?? 'n/a') ?>">
                <?= htmlspecialchars($sysinfo['top_cpu'] ?? 'n/a') ?>
            </div>
        </div>

        <div class="metric">
            <h4>Top MEM</h4>
            <div class="value ellip smallmono" data-metric="topMem" title="<?= htmlspecialchars($sysinfo['top_mem'] ?? 'n/a') ?>">
                <?= htmlspecialchars($sysinfo['top_mem'] ?? 'n/a') ?>
            </div>
        </div>

        <div class="metric">
            <h4>Disque /var/www</h4>
            <div class="value smallmono" data-metric="diskWww"><?= htmlspecialchars($sysinfo['disk_www'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>Version PHP CLI</h4>
            <div class="value smallmono" data-metric="phpCli"><?= htmlspecialchars($sysinfo['php_cli'] ?? 'n/a') ?></div>
        </div>

        <div class="metric">
            <h4>PHP-FPM</h4>
            <div class="chip-row">
                <?php foreach ($php_fpm_compact as $m):
                    $title = sprintf("%s — socket: %s — service: %s",
                            $m['label'],
                            $m['sock'] ? 'oui' : 'non',
                            $m['svc']  ? 'actif' : 'inactif'
                    );
                    $cls = $m['ok'] ? 'ok' : (($m['sock'] || $m['svc']) ? 'warn' : 'err');
                    ?>
                    <span class="badge <?= $cls ?>"
                          data-tip="<?= htmlspecialchars($title) ?>"
                          title="<?= htmlspecialchars($title) ?>">
            <?= htmlspecialchars($m['label']) ?>
          </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="metric">
            <h4>Version Nginx</h4>
            <div class="value smallmono" data-metric="nginxVer"><?= htmlspecialchars($sysinfo['nginx_version'] ?? 'n/a') ?></div>
        </div>
    </div>
</div>

<!-- Overlay alimentation -->
<div class="overlay" id="powerOverlay" aria-hidden="true" style="display:none">
    <div class="overlay-card" role="dialog" aria-modal="true" aria-labelledby="powerTitle">
        <div class="overlay-header">
            <div class="ok-dot" style="background:#60a5fa"></div>
            <h3 class="overlay-title" id="powerTitle">Commande en cours…</h3>
        </div>
        <div class="overlay-body">
            <div class="small" id="powerHint">Envoi de la commande…</div>
            <pre class="pre-log" id="powerLog"></pre>
        </div>
        <div class="overlay-footer">
            <button type="button" class="btn" id="powerClose" disabled>Fermer</button>
        </div>
    </div>
</div>
<script src="/js/energy.js" defer></script>
<script src="/js/storage.js" defer></script>
