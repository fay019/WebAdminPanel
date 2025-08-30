<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/partials/flash.php';

// Endpoint AJAX pour le polling sysinfo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sysinfo') {
    header('Content-Type: application/json; charset=utf-8');
    $sysDeploy = '/var/www/adminpanel/bin/sysinfo.sh';
    $sysLocal  = __DIR__ . '/bin/sysinfo.sh';
    $sys = file_exists($sysDeploy) ? $sysDeploy : $sysLocal;
    passthru('sudo -n ' . escapeshellarg($sys));
    exit;
}
$sitesCount = (int)db()->query('SELECT COUNT(*) FROM sites')->fetchColumn();

$sysinfo = [
        'cpu_temp' => 'n/a',
        'ram'      => 'n/a',
        'disk'     => 'n/a',
];

$sysDeploy = '/var/www/adminpanel/bin/sysinfo.sh';
$sysLocal  = __DIR__ . '/bin/sysinfo.sh';
$sys = file_exists($sysDeploy) ? $sysDeploy : $sysLocal;
$out = shell_exec('sudo -n ' . escapeshellarg($sys) . ' 2>&1');
if ($out) {
    foreach (explode("\n", trim($out)) as $line) {
        if (str_contains($line,'=')) { [$k,$v]=explode('=',$line,2); $sysinfo[$k]=trim($v); }
    }
}

// Fallbacks
if (empty($sysinfo['processes']) || $sysinfo['processes']==='n/a') {
    $sysinfo['processes'] = trim(shell_exec('ps -e --no-headers | wc -l')) ?: 'n/a';
}
if (empty($sysinfo['php_sockets']) || $sysinfo['php_sockets']==='n/a') {
    $socks = glob('/run/php/php*-fpm.sock') ?: [];
    $sysinfo['php_sockets'] = $socks ? implode(' ', array_map('basename', $socks)) : 'n/a';
}
if (empty($sysinfo['nginx_version']) || $sysinfo['nginx_version']==='n/a') {
    $ver = trim(shell_exec('nginx -v 2>&1'));
    $sysinfo['nginx_version'] = $ver ? preg_replace('/^nginx version:\s*/','',$ver) : 'n/a';
}

// PHP-FPM compact
$php_fpm_compact = [];
$seen = [];
$socketList = [];
if (!empty($sysinfo['php_fpm_sockets']) && $sysinfo['php_fpm_sockets'] !== 'n/a') {
    foreach (preg_split('/\s*,\s*/', trim($sysinfo['php_fpm_sockets'])) as $s) {
        if ($s !== '') { $socketList[] = trim($s); }
    }
} else {
    foreach (glob('/run/php/php*-fpm.sock') ?: [] as $s) {
        $socketList[] = basename($s);
    }
}
foreach ($socketList as $sockName) {
    if (!preg_match('/^php(?:(\d+\.\d+))?-fpm\.sock$/', $sockName, $m)) continue;
    $ver   = $m[1] ?? null;
    $label = $ver ? "php{$ver}" : 'php';
    if (isset($seen[$label])) continue;

    $sockPath = "/run/php/{$sockName}";
    $sockOk   = file_exists($sockPath);
    $svcName  = $ver ? "php{$ver}-fpm" : 'php-fpm';
    $svcOk    = (trim(shell_exec("systemctl is-active {$svcName} 2>/dev/null || true")) === 'active');

    $php_fpm_compact[] = [
            'label' => $label,
            'v'     => $ver ?: '',
            'ok'    => ($sockOk && $svcOk),
            'sock'  => $sockOk,
            'svc'   => $svcOk,
    ];
    $seen[$label] = true;
}
usort($php_fpm_compact, fn($a,$b)=>strnatcmp($a['label'],$b['label']));
if (!$php_fpm_compact) {
    foreach (['8.2','8.3','8.4'] as $v) {
        $sockOk = file_exists("/run/php/php{$v}-fpm.sock");
        $svcOk  = (trim(shell_exec("systemctl is-active php{$v}-fpm 2>/dev/null || true")) === 'active');
        $php_fpm_compact[] = [
                'label' => "php{$v}",
                'v'     => $v,
                'ok'    => ($sockOk && $svcOk),
                'sock'  => $sockOk,
                'svc'   => $svcOk,
        ];
    }
}

include __DIR__.'/partials/header.php';
?>
    <script>window.SYSINFO_URL = '/dashboard.php?ajax=sysinfo';</script>

    <div class="card">
        <h2>Dashboard</h2>

        <!-- Commandes alimentation via ancres cliquables (confirm + POST JS) -->
        <div class="actions" style="margin:8px 0 16px">
            <a href="/system_power.php?stream=1"
               class="icon-link"
               data-confirm="⚠️ Éteindre la machine dans ~30s ?"
               data-action="shutdown"
               data-ajax="1"
               data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               style="display:inline-block;margin-right:8px">
                <img src="/public/img/power.svg" alt="Éteindre" title="Éteindre" class="icon-btn" />
            </a>

            <a href="/system_power.php?stream=1"
               class="icon-link"
               data-confirm="🔄 Redémarrer la machine dans ~30s ?"
               data-action="reboot"
               data-ajax="1"
               data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               style="display:inline-block">
                <img src="/public/img/reload.svg" alt="Redémarrer" title="Redémarrer" class="icon-btn" />
            </a>
        </div>

        <?php show_flash(); ?>

        <div class="metrics">
            <div class="metric">
                <h4>Sites</h4>
                <div class="value"><?= $sitesCount ?></div>
            </div>

            <div class="card">
                <div class="small">CPU Temp</div>
                <div class="value big gray" id="cpuTempVal">n/a</div>
            </div>

            <div class="card">
                <div class="small">RAM</div>
                <div class="value gray" id="ramVal">n/a</div>
            </div>

            <div class="metric">
                <h4>Uptime</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['uptime'] ?? 'n/a') ?></div>
            </div>

            <div class="card">
                <div class="small">CPU Load</div>
                <div class="value gray" id="cpuLoadVal">n/a</div>
            </div>

            <div class="metric">
                <h4>Disque</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['disk'] ?? 'n/a') ?></div>
            </div>

            <div class="metric">
                <h4>OS</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['os'] ?? 'n/a') ?></div>
            </div>

            <div class="metric">
                <h4>Kernel</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['kernel'] ?? 'n/a') ?></div>
            </div>

            <div class="metric">
                <h4>Processus actifs</h4>
                <div class="value"><?= htmlspecialchars($sysinfo['processes']) ?></div>
            </div>

            <div class="metric">
                <h4>Cœurs CPU</h4>
                <div class="value"><?= htmlspecialchars($sysinfo['cpu_cores'] ?? 'n/a') ?></div>
            </div>

            <div class="metric">
                <h4>Top CPU</h4>
                <div class="value ellip smallmono" title="<?= htmlspecialchars($sysinfo['top_cpu'] ?? 'n/a') ?>">
                    <?= htmlspecialchars($sysinfo['top_cpu'] ?? 'n/a') ?>
                </div>
            </div>

            <div class="metric">
                <h4>Top MEM</h4>
                <div class="value ellip smallmono" title="<?= htmlspecialchars($sysinfo['top_mem'] ?? 'n/a') ?>">
                    <?= htmlspecialchars($sysinfo['top_mem'] ?? 'n/a') ?>
                </div>
            </div>

            <div class="metric">
                <h4>Disque /var/www</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['disk_www'] ?? 'n/a') ?></div>
            </div>

            <div class="metric">
                <h4>Version PHP CLI</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['php_cli'] ?? 'n/a') ?></div>
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
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['nginx_version'] ?? 'n/a') ?></div>
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
<?php include __DIR__.'/partials/footer.php'; ?>