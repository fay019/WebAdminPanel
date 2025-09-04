<?php
// Temporary legacy shim: redirect to new MVC route
header('Location: /php/manage', true, 302);
exit;

if (!function_exists('run')) {
    function run(string $cmd): string
    {
        $out = shell_exec($cmd . ' 2>&1');
        return $out === null ? '' : $out;
    }
}

// --- utilitaire : exécuter une commande en stream texte/plain
function stream_cmd(string $cmd): void
{
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Accel-Buffering: no');     // nginx: désactive le buffering
    header('Cache-Control: no-store');
    @ob_end_flush();
    ob_implicit_flush(true);
    ignore_user_abort(true);

    // Entête d'exécution
    $ts = date('c');
    echo "[INFO] $ts\n";
    echo "[INFO] Exécution: $cmd\n";
    echo "[INFO] Astuce: si rien ne s'affiche, vérifiez sudoers (install.sh) et /var/log/nginx/error.log\n\n";

    $des = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
    ];
    $proc = @proc_open($cmd, $des, $pipes);
    if (!is_resource($proc)) {
        echo "[ERREUR] Impossible de lancer la commande. Vérifiez que 'sudo' autorise le script et que le binaire existe.\n";
        exit(1);
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $lastFlush = microtime(true);
    // lire stdout/stderr en boucle et pousser dans la réponse
    while (true) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        if ($out !== '') {
            echo $out;
        }
        if ($err !== '') {
            echo $err;
        }

        $status = proc_get_status($proc);
        if (!$status['running']) break;

        // Keep-alive toutes les ~2s si aucune sortie
        $now = microtime(true);
        if ($now - $lastFlush > 2.0 && $out === '' && $err === '') {
            echo "."; // tick
            $lastFlush = $now;
        }

        usleep(80_000); // 80ms pour ne pas surcharger
    }

    $code = proc_close($proc);
    echo "\n-- Fin (code {$code}) --\n";
    if ($code !== 0) {
        echo "[ERREUR] La commande s'est terminée avec un code non nul ($code).\n";
    } else {
        echo "[OK] Terminé avec succès.\n";
    }
    exit; // ne pas rendre le HTML de la page
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Déterminer si appel AJAX/stream avant le check CSRF pour ajuster le Content-Type en cas d'erreur
    $isAjax = !empty($_POST['ajax']) || (isset($_GET['stream']) && $_GET['stream'] === '1');
    if ($isAjax) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    csrf_check();

    // On accepte stream forcé par GET (?stream=1) ou via champ POST ajax=1
    $forceStream = (isset($_GET['stream']) && $_GET['stream'] === '1') || !empty($_POST['ajax']);

    $action = $_POST['action'] ?? '';
    $sel = trim($_POST['version_sel'] ?? '');
    $custom = trim($_POST['version_custom'] ?? '');
    $ver = $custom !== '' ? $custom : $sel;
    if ($ver === '' && isset($_POST['version'])) {
        $ver = trim($_POST['version']);
    }

    // Filtre simple X.Y
    if ($ver !== '' && !preg_match('/^\d+\.\d+$/', $ver)) {
        if ($forceStream) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "[ERREUR] Version invalide: {$ver}\n";
            exit(1);
        }
        flash('err', "Version invalide : " . htmlspecialchars($ver));
        header('Location: /php_manage.php');
        exit;
    }

    // Commande à exécuter
    $cmd = null;
    // Résoudre le chemin du script bin (prod vs dev)
    $binDeploy = '/var/www/adminpanel/bin/php_manage.sh';
    $binLocal = __DIR__ . '/bin/php_manage.sh';
    $bin = file_exists($binDeploy) ? $binDeploy : $binLocal;

    if ($action === 'install' && $ver !== '') {
        $cmd = "sudo -n " . escapeshellarg($bin) . " install " . escapeshellarg($ver);
    } elseif ($action === 'remove' && $ver !== '') {
        $cmd = "sudo -n " . escapeshellarg($bin) . " remove " . escapeshellarg($ver);
    } elseif ($action === 'restart' && $ver !== '') {
        $cmd = "sudo -n " . escapeshellarg($bin) . " restart " . escapeshellarg($ver);
    }

    if ($cmd === null) {
        if ($forceStream) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "[ERREUR] Action manquante ou invalide.\n";
            exit(1);
        }
        flash('err', 'Action manquante ou invalide.');
        header('Location: /php_manage.php');
        exit;
    }

    // Libère le verrou de session si besoin (sécurité)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Mode stream (modale)
    if ($forceStream) {
        // libère le verrou de session pour éviter de bloquer d’autres requêtes
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Pré-vérification sudo pour messages plus clairs
        $sudoTest = shell_exec('sudo -n true 2>&1');
        if ($sudoTest === null) {
            echo "[ERREUR] 'sudo' non disponible.\n";
            exit(1);
        }
        // Tenter un ls sur le binaire pour capter un éventuel refus sudoers
        $check = shell_exec('sudo -n ' . escapeshellarg($bin) . ' --help 2>&1');
        if ($check !== null && (str_contains($check, 'a password is required') || str_contains($check, 'not in the sudoers'))) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "[ERREUR] sudo NOPASSWD manquant pour $bin.\n";
            echo "Astuce: relancez install.sh pour déployer /etc/sudoers.d/adminpanel.\n";
            exit(1);
        }
        stream_cmd($cmd); // ne revient pas
    }

    // Mode normal (flash + redirect)
    $out = shell_exec($cmd . ' 2>&1');
    $ok = (is_string($out) && str_starts_with($out, 'OK:'));
    flash($ok ? 'ok' : 'err', nl2br(htmlspecialchars($out ?? '')), true);
    header('Location: /php_manage.php');
    exit;
}

// Lister via le bon binaire (prod ou dev)
$binDeploy = '/var/www/adminpanel/bin/php_manage.sh';
$binLocal = __DIR__ . '/bin/php_manage.sh';
$bin = file_exists($binDeploy) ? $binDeploy : $binLocal;
$json = run('sudo -n ' . escapeshellarg($bin) . ' list --json');
$rows = json_decode($json, true);
if (!is_array($rows)) $rows = [];

// Versions courantes proposées par défaut (tu peux en ajouter/supprimer ici)
$commonChoices = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
$installed = array_map(fn($r) => $r['ver'], $rows);
// on ne repropose pas celles déjà présentes
$choices = array_values(array_diff($commonChoices, $installed));

include __DIR__ . '/partials/header.php';
?>
    <div class="card">
        <h2>PHP (Système)</h2>
        <?php show_flash(); ?>

        <div class="form-row">
            <div>
                <label>Installer une version</label>
                <form method="post"
                      class="actions" style="align-items:end"
                      id="installForm"
                      action="/php_manage.php?stream=1">
                    <?= csrf_input() ?>
                    <input type="hidden" name="ajax" value="1">
                    <div>
                        <label class="small">Choisir dans la liste</label>
                        <select name="version_sel">
                            <?php foreach ($choices as $v): ?>
                                <option><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="small" style="margin-top:6px">…ou saisir une version précise (ex : 7.4)</div>
                        <input name="version_custom" placeholder="ex: 7.4">
                    </div>
                    <button class="btn primary" name="action" value="install" data-install>Installer</button>
                </form>
            </div>
        </div>

        <h3 style="margin-top:16px">Versions détectées</h3>
        <div class="table-responsive">
            <table class="tbl tbl--hover tbl--sticky" role="table" data-default-sort="version" data-default-dir="desc">
              <thead>
                <tr>
                  <th class="col-name is-sortable" data-sort-key="version" scope="col">Version</th>
                  <th class="col-status" scope="col">Socket</th>
                  <th class="col-status" scope="col">Service</th>
                  <th class="col-actions" scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="4" class="small">Aucune version PHP-FPM détectée.</td>
                </tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="col-name" data-label="Version" data-sort="<?= htmlspecialchars($r['ver']) ?>"><strong>php<?= htmlspecialchars($r['ver']) ?></strong></td>
                    <td class="col-status" data-label="Socket">
                      <span class="badge <?= $r['socket'] ? 'ok' : 'err' ?>">
                        <?= $r['socket'] ? 'présent' : 'absent' ?>
                      </span>
                    </td>
                    <td class="col-status" data-label="Service">
                      <span class="badge <?= $r['service'] ? 'ok' : 'err' ?>">
                        <?= $r['service'] ? 'actif' : 'inactif' ?>
                      </span>
                    </td>
                    <td class="col-actions" data-label="Actions">
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="version" value="<?= htmlspecialchars($r['ver']) ?>">
                            <button class="btn" name="action" value="restart">Redémarrer</button>
                        </form>
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="version" value="<?= htmlspecialchars($r['ver']) ?>">
                            <button class="btn danger" name="action" value="remove"
                                    data-confirm="Désinstaller PHP <?= htmlspecialchars($r['ver']) ?> ?">
                                Désinstaller
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
              </tbody>
            </table>
        </div>
    </div>
    <div class="overlay" id="busyOverlay" aria-hidden="true">
        <div class="overlay-card" role="dialog" aria-modal="true" aria-labelledby="busyTitle">
            <div class="overlay-header">
                <div class="ok-dot" style="background:#60a5fa"></div>
                <h3 class="overlay-title" id="busyTitle">Installation en cours…</h3>
            </div>
            <div class="overlay-body">
                <div id="busyHint" class="small" style="margin-bottom:8px">Merci de patienter, ne fermez pas la page.
                </div>
                <pre id="busyLog" class="pre-log"></pre>
            </div>
            <div class="overlay-footer">
                <button type="button" class="btn" id="busyClose" disabled>Fermer</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/partials/footer.php'; ?>