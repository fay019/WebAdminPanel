<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/validators.php';
require_once __DIR__ . '/partials/flash.php';

function nginx_conf_exists(string $name): bool {
    return is_file("/etc/nginx/sites-available/{$name}.conf");
}

function nginx_used_server_names(): array {
    $out = [];
    foreach (glob('/etc/nginx/sites-available/*.conf') as $f) {
        $txt = @file_get_contents($f);
        if ($txt === false) continue;
        if (preg_match_all('/server_name\s+([^;]+);/i', $txt, $m)) {
            foreach ($m[1] as $block) {
                foreach (preg_split('/\s+/', trim($block)) as $host) {
                    $host = strtolower(trim($host));
                    if ($host !== '') $out[$host] = true;
                }
            }
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $name = strtolower(trim($_POST['name'] ?? ''));
    $server_names = clean_server_names($_POST['server_names'] ?? '');
    $root = trim($_POST['root'] ?? '');
    if ($root === '') $root = "/var/www/{$name}/public";
    $php = $_POST['php_version'] ?? '8.3';
    $mb  = (int)($_POST['client_max_body_size'] ?? 20);
    $with_logs = isset($_POST['with_logs']) ? 1 : 0;
    $reset = isset($_POST['reset_root']) ? 1 : 0;
    $site_dir = preg_match('~/public/?$~', $root) ? rtrim(substr($root, 0, -7), '/') : rtrim($root, '/');

    $errors = [];

    // ✅ Validations de base
    if (!v_slug($name)) $errors[] = 'Nom (slug) invalide.';
    if (!v_server_names($server_names)) $errors[] = 'Server names invalides.';
    if (!v_root($root)) $errors[] = 'Root doit être sous /var/www/';
    if (!v_php_version($php)) $errors[] = 'Version PHP invalide.';
    if ($mb < 1 || $mb > 1024) $errors[] = 'Taille upload invalide (1..1024 MB).';

    // ✅ Unicité slug
    $exists = db()->prepare('SELECT COUNT(*) FROM sites WHERE name=:n');
    $exists->execute([':n' => $name]);
    if ($exists->fetchColumn() > 0) {
        $errors[] = 'Ce slug existe déjà dans le panel.';
    }
    if (nginx_conf_exists($name)) {
        $errors[] = "Un vhost Nginx « {$name} » existe déjà.";
    }

    // ✅ Unicité server_names
    $asked_hosts = preg_split('/[\s,]+/', strtolower($server_names), -1, PREG_SPLIT_NO_EMPTY);
    if ($asked_hosts) {
        $in = db()->query('SELECT server_names FROM sites')->fetchAll(PDO::FETCH_COLUMN);
        $taken_db = [];
        foreach ($in as $sn) {
            foreach (preg_split('/[\s,]+/', strtolower($sn), -1, PREG_SPLIT_NO_EMPTY) as $h) {
                $taken_db[$h] = true;
            }
        }
        $taken_ng = nginx_used_server_names();
        $conflicts = [];
        foreach ($asked_hosts as $h) {
            if (isset($taken_db[$h]) || isset($taken_ng[$h])) $conflicts[] = $h;
        }
        if ($conflicts) {
            $errors[] = 'Ces hostnames sont déjà utilisés : ' . htmlspecialchars(implode(', ', array_unique($conflicts)));
        }
    }

    // ✅ Unicité root
    $root_in_use = db()->prepare('SELECT COUNT(*) FROM sites WHERE root=:r');
    $root_in_use->execute([':r'=>$root]);
    if ($root_in_use->fetchColumn() > 0) {
        $errors[] = 'Ce document root est déjà utilisé par un autre site.';
    }
// ✅ A ce stade, $errors contient toutes les erreurs de validation
    if ($errors) {
        // Affichage formaté (liste <ul>)
        $html = '<ul style="margin:0;padding-left:20px">';
        foreach ($errors as $e) { $html .= '<li>'.htmlspecialchars($e, ENT_QUOTES).'</li>'; }
        $html .= '</ul>';
        flash('err', $html, true);
    } else {
        // ——— Modales éventuelles (et on sort aussitôt si nécessaire)
        if ($reset === 1 && is_dir($site_dir) && empty($_POST['confirmed'])) {
            $_SESSION['confirm_reset'] = [
                'name'=>$name,'site_dir'=>$site_dir,'root'=>$root,'server_names'=>$server_names,
                'php'=>$php,'mb'=>$mb,'with_logs'=>$with_logs,'reset'=>$reset,
            ];
            header('Location: /site_new.php?confirm=1'); exit;
        }
        if ($reset === 0 && is_dir($site_dir) && empty($_POST['confirmed_keep'])) {
            $_SESSION['confirm_keep'] = [
                'name'=>$name,'site_dir'=>$site_dir,'root'=>$root,'server_names'=>$server_names,
                'php'=>$php,'mb'=>$mb,'with_logs'=>$with_logs,
            ];
            header('Location: /site_new.php?keep=1'); exit;
        }

        // ——— Pas de modale → on crée vraiment le site
        $cmd = sprintf(
            "sudo -n /var/www/adminpanel/bin/site_add.sh %s %s %s %s %d %d %d 2>&1",
            escapeshellarg($name), escapeshellarg($server_names), escapeshellarg($root),
            escapeshellarg($php), $mb, $with_logs, $reset
        );
        $_SESSION['last_cmd_output'] = shell_exec($cmd);

        $st = db()->prepare(
            'INSERT INTO sites(name,server_names,root,php_version,client_max_body_size,with_logs,enabled,created_at,updated_at)
         VALUES(:n,:s,:r,:p,:mb,:wl,0,:c,:c)'
        );
        $st->execute([':n'=>$name,':s'=>$server_names,':r'=>$root,':p'=>$php,':mb'=>$mb,':wl'=>$with_logs,':c'=>date('c')]);

        audit('site.create', ['name'=>$name]);

        // Tip /etc/hosts
        $ip = trim(shell_exec("hostname -I | awk '{print $1}'"));
        $hosts = preg_split('/[\s,]+/', $server_names, -1, PREG_SPLIT_NO_EMPTY);
        $_SESSION['hosts_tip'] = [
            'name'=>$name,
            'lines'=>array_map(fn($h)=>"$ip\t$h", $hosts),
            'local'=>(bool)preg_grep('/\.local$/', $hosts),
        ];

        if (is_dir($site_dir) && $reset === 0) {
            flash('ok', 'Site créé. Les fichiers existants ont été conservés (<code>'.
                htmlspecialchars($site_dir,ENT_QUOTES).'</code>).', true);
        }

        header('Location: /sites_list.php'); exit;
    }
}

include __DIR__ . '/partials/header.php';
?>

<?php if (!empty($_SESSION['confirm_reset']) && isset($_GET['confirm'])):
    $c = $_SESSION['confirm_reset']; unset($_SESSION['confirm_reset']);
    $site_dir = htmlspecialchars($c['site_dir'], ENT_QUOTES);
    ?>
    <div class="modal" id="reset-confirm">
        <div class="modal-card">
            <div class="modal-header">
                <div class="ok-dot" style="background:#f59e0b"></div>
                <h3 class="modal-title">Réinitialiser le site</h3>
            </div>
            <div class="modal-body">
                <p>Le dossier <code><?= $site_dir ?></code> existe déjà.</p>
                <p>En confirmant, il sera <strong>déplacé en sauvegarde</strong> (<code>.old.TIMESTAMP</code>) puis un dossier propre sera recréé avec une page par défaut.</p>
            </div>
            <div class="modal-footer">
                <form method="post" style="margin:0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="confirmed" value="1">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($c['name'],ENT_QUOTES) ?>">
                    <input type="hidden" name="server_names" value="<?= htmlspecialchars($c['server_names'],ENT_QUOTES) ?>">
                    <input type="hidden" name="root" value="<?= htmlspecialchars($c['root'],ENT_QUOTES) ?>">
                    <input type="hidden" name="php_version" value="<?= htmlspecialchars($c['php'],ENT_QUOTES) ?>">
                    <input type="hidden" name="client_max_body_size" value="<?= (int)$c['mb'] ?>">
                    <input type="hidden" name="with_logs" value="<?= (int)$c['with_logs'] ?>">
                    <input type="hidden" name="reset_root" value="1">
                    <button type="button" class="btn-ghost" onclick="this.closest('.modal').remove()">Annuler</button>
                    <button class="btn danger">🗑️ Confirmer la réinitialisation</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['confirm_keep']) && isset($_GET['keep'])):
    $c = $_SESSION['confirm_keep']; unset($_SESSION['confirm_keep']);
    $site_dir = htmlspecialchars($c['site_dir'], ENT_QUOTES);
    $nameSafe = htmlspecialchars($c['name'], ENT_QUOTES);
    ?>
    <div class="modal" id="keep-confirm">
        <div class="modal-card">
            <div class="modal-header">
                <div class="ok-dot" style="background:#f59e0b"></div>
                <h3 class="modal-title">Le dossier existe déjà</h3>
            </div>
            <div class="modal-body">
                <p>Un dossier pour ce site existe déjà&nbsp;: <code><?= $site_dir ?></code></p>
                <p>Que souhaitez-vous faire&nbsp;?</p>
                <ul style="margin:8px 0 0 18px;color:#cbd5e1">
                    <li><strong>Utiliser le dossier existant</strong> — les fichiers actuels seront conservés et utilisés pour ce site.</li>
                    <li><strong>Réinitialiser le site</strong> — le dossier actuel sera <em>déplacé en sauvegarde</em> (<code>.old.TIMESTAMP</code>) puis un dossier propre sera recréé avec une page par défaut.</li>
                </ul>
            </div>
            <div class="modal-footer" style="display:flex;gap:12px;flex-wrap:wrap">
                <!-- ✅ Utiliser le dossier existant -->
                <form method="post" style="margin:0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="confirmed_keep" value="1">
                    <input type="hidden" name="name" value="<?= $nameSafe ?>">
                    <input type="hidden" name="server_names" value="<?= htmlspecialchars($c['server_names'],ENT_QUOTES) ?>">
                    <input type="hidden" name="root" value="<?= htmlspecialchars($c['root'],ENT_QUOTES) ?>">
                    <input type="hidden" name="php_version" value="<?= htmlspecialchars($c['php'],ENT_QUOTES) ?>">
                    <input type="hidden" name="client_max_body_size" value="<?= (int)$c['mb'] ?>">
                    <input type="hidden" name="with_logs" value="<?= (int)$c['with_logs'] ?>">
                    <input type="hidden" name="reset_root" value="0">
                    <button class="btn">✅ Utiliser le dossier existant</button>
                </form>

                <!-- 🗑️ Réinitialiser (sauvegarde) -->
                <form method="post" style="margin:0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="confirmed_keep" value="1">
                    <input type="hidden" name="name" value="<?= $nameSafe ?>">
                    <input type="hidden" name="server_names" value="<?= htmlspecialchars($c['server_names'],ENT_QUOTES) ?>">
                    <input type="hidden" name="root" value="<?= htmlspecialchars($c['root'],ENT_QUOTES) ?>">
                    <input type="hidden" name="php_version" value="<?= htmlspecialchars($c['php'],ENT_QUOTES) ?>">
                    <input type="hidden" name="client_max_body_size" value="<?= (int)$c['mb'] ?>">
                    <input type="hidden" name="with_logs" value="<?= (int)$c['with_logs'] ?>">
                    <input type="hidden" name="reset_root" value="1">
                    <button class="btn danger">🗑️ Réinitialiser le site (sauvegarder l’ancien)</button>
                </form>

                <!-- Annuler -->
                <button type="button" class="btn-ghost" onclick="this.closest('.modal').remove()">Annuler</button>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="card">
  <h2>Nouveau site</h2>
  <?php show_flash(); ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>Nom (slug)</label>
    <input name="name" required>

    <label>Server names (espace/virgule)</label>
    <input name="server_names" required placeholder="ex: site.local www.site.local">

    <div class="form-row">
      <div>
        <label>Racine (root)</label>
        <input name="root" placeholder="/var/www/{name}/public">
      </div>
      <div>
        <label>Version PHP-FPM</label>
        <select name="php_version">
          <option>8.2</option><option selected>8.3</option><option>8.4</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>client_max_body_size (MB)</label>
        <input type="number" name="client_max_body_size" value="20" min="1" max="1024">
        <div class="small">Limite d’upload côté Nginx. Si dépassée ⇒ erreur 413 avant PHP. Pense à aligner aussi <code>upload_max_filesize</code> et <code>post_max_size</code> en PHP.</div>
      </div>
      <div style="display:flex;align-items:end">
          <label style="width:auto">
              <input type="checkbox" name="with_logs" checked> Logs dédiés
          </label>
          <label style="width:auto">
              <input type="checkbox" name="reset_root" value="1"> Réinitialiser le dossier si existant
          </label>
      </div>
    </div>

    <button class="btn primary" style="margin-top:10px">Créer</button>
  </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
