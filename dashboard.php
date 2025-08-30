<?php
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/partials/flash.php';

// Endpoint AJAX pour le polling sysinfo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sysinfo') {
    header('Content-Type: application/json; charset=utf-8');
    // Renvoie le JSON de sysinfo.sh (NOPASSWD déjà en place)
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

// Récup via script
$sysDeploy = '/var/www/adminpanel/bin/sysinfo.sh';
$sysLocal  = __DIR__ . '/bin/sysinfo.sh';
$sys = file_exists($sysDeploy) ? $sysDeploy : $sysLocal;
$out = shell_exec('sudo -n ' . escapeshellarg($sys) . ' 2>&1');
if ($out) {
    foreach (explode("\n", trim($out)) as $line) {
        if (str_contains($line,'=')) { [$k,$v]=explode('=',$line,2); $sysinfo[$k]=trim($v); }
    }
}

/* Fallbacks fiables côté PHP pour les 3 métriques qui te manquaient */
if (empty($sysinfo['processes']) || $sysinfo['processes']==='n/a') {
    $sysinfo['processes'] = trim(shell_exec('ps -e --no-headers | wc -l')) ?: 'n/a';
}
if (empty($sysinfo['php_sockets']) || $sysinfo['php_sockets']==='n/a') {
    $socks = glob('/run/php/php*-fpm.sock') ?: [];
    $sysinfo['php_sockets'] = $socks ? implode(' ', array_map('basename', $socks)) : 'n/a';
}
if (empty($sysinfo['nginx_version']) || $sysinfo['nginx_version']==='n/a') {
    $ver = trim(shell_exec('nginx -v 2>&1')); // sort sur stderr
    $sysinfo['nginx_version'] = $ver ? preg_replace('/^nginx version:\s*/','',$ver) : 'n/a';
}

// Statut compact PHP‑FPM (détection dynamique à partir des sockets trouvés)
$php_fpm_compact = [];
$seen = [];

// Préférence: utiliser la liste fournie par sysinfo.sh si présente, sinon scanner /run/php
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
    // Ex: php8.3-fpm.sock  |  php-fpm.sock
    if (!preg_match('/^php(?:(\d+\.\d+))?-fpm\.sock$/', $sockName, $m)) continue;
    $ver   = $m[1] ?? null;                 // null pour le socket générique
    $label = $ver ? "php{$ver}" : 'php';
    if (isset($seen[$label])) continue;     // éviter les doublons

    $sockPath = "/run/php/{$sockName}";
    // Les sockets UNIX ne sont pas des "fichiers" réguliers : is_file() renvoie false.
    // file_exists() fonctionne pour les sockets.
    $sockOk   = file_exists($sockPath);

    // Service à vérifier: phpX.Y-fpm ou php-fpm
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

// Tri naturel par label (php, puis php8.2, php8.3, ...)
usort($php_fpm_compact, function($a,$b){ return strnatcmp($a['label'], $b['label']); });
// Si rien détecté, afficher au moins les versions usuelles
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

        <div class="metrics">
            <div class="metric">
                <h4>Sites</h4>
                <div class="value"><?= $sitesCount ?></div>
            </div>

            <!-- Température CPU -->
            <div class="card">
                <div class="small">CPU Temp</div>
                <div class="value big gray" id="cpuTempVal">n/a</div>
            </div>

            <!-- RAM -->
            <div class="card">
                <div class="small">RAM</div>
                <div class="value gray" id="ramVal">n/a</div>
            </div>

            <!-- Uptime -->
            <div class="metric">
                <h4>Uptime</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['uptime'] ?? 'n/a') ?></div>
            </div>

            <!-- Charge CPU -->
            <div class="card">
                <div class="small">CPU Load</div>
                <div class="value gray" id="cpuLoadVal">n/a</div>
            </div>

            <div class="metric">
                <h4>Disque</h4>
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['disk']) ?></div>
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
                <h4>PHP‑FPM</h4>
                <div class="chip-row">
                    <?php foreach ($php_fpm_compact as $m):
                        $title = sprintf(
                                "%s — socket: %s — service: %s",
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
                <div class="value smallmono"><?= htmlspecialchars($sysinfo['nginx_version']) ?></div>
            </div>
        </div>
    </div>
<?php include __DIR__.'/partials/footer.php'; ?>