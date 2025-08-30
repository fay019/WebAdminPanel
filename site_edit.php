<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/validators.php';
require_once __DIR__ . '/partials/flash.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('err','ID invalide'); header('Location: /sites_list.php'); exit; }

$st = db()->prepare('SELECT * FROM sites WHERE id = :id');
$st->execute([':id'=>$id]);
$site = $st->fetch();
if (!$site) { flash('err','Site introuvable'); header('Location: /sites_list.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        // Récup champs (nom non modifiable ici pour rester simple/fiable)
        $name = $site['name'];
        $server_names = clean_server_names($_POST['server_names'] ?? '');
        $root = trim($_POST['root'] ?? '');
        $php  = $_POST['php_version'] ?? $site['php_version'];
        $mb   = (int)($_POST['client_max_body_size'] ?? $site['client_max_body_size']);
        $with_logs = isset($_POST['with_logs']) ? 1 : 0;

        // Validations
        if (!v_server_names($server_names)) $errors[] = 'Server names invalides.';
        if (!v_root($root)) $errors[] = 'Root doit être sous /var/www/.';
        if (!v_php_version($php)) $errors[] = 'Version PHP invalide.';
        if ($mb < 1 || $mb > 1024) $errors[] = 'Taille upload invalide (1..1024 MB).';

        if (!$errors) {
            // Maj DB
            $upd = db()->prepare('UPDATE sites
        SET server_names=:s, root=:r, php_version=:p, client_max_body_size=:mb, with_logs=:wl, updated_at=:u
        WHERE id=:id');
            $upd->execute([
                ':s'=>$server_names, ':r'=>$root, ':p'=>$php, ':mb'=>$mb, ':wl'=>$with_logs,
                ':u'=>date('c'), ':id'=>$id
            ]);

            // Regénérer conf via script
            $cmd = sprintf(
                '/usr/bin/sudo -n /var/www/adminpanel/bin/site_edit.sh %s %s %s %s %d %d 2>&1',
                escapeshellarg($name),
                escapeshellarg($server_names),
                escapeshellarg($root),
                escapeshellarg($php),
                $mb,
                $with_logs
            );
            $out = shell_exec($cmd);
            $_SESSION['last_cmd_output'] = $out ?: '(aucune sortie)';

            audit('site.edit', ['name'=>$name]);
            $nameSafe = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $outSafe  = htmlspecialchars($_SESSION['last_cmd_output'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = <<<HTML
<strong>Modifications enregistrées pour « {$nameSafe} ».</strong>
<details class="log-details"><summary>Voir la sortie du script</summary>
<pre class="log-pre">{$outSafe}</pre>
</details>
HTML;
            flash('ok', $html, true);
            header('Location: /site_edit.php?id='.$id); exit;
        } else {
            flash('err', implode(' ', $errors));
            // On retombe sur le formulaire avec valeurs postées
            $site['server_names'] = $server_names;
            $site['root'] = $root;
            $site['php_version'] = $php;
            $site['client_max_body_size'] = $mb;
            $site['with_logs'] = $with_logs;
        }
    }
}

include __DIR__ . '/partials/header.php';
?>
    <style>
        /* petite barre d'actions collante en bas de la carte */
        .actions-bar{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:12px;border-top:1px solid #253154;padding-top:12px}
        .danger-zone{border:1px dashed #7f1d1d;padding:10px;border-radius:10px;margin-top:12px;background:rgba(127,29,29,.08)}
    </style>

    <div class="card">
        <h2>Éditer : <?= htmlspecialchars($site['name']) ?></h2>
        <?php show_flash(); ?>

        <form method="post">
            <?= csrf_input() ?>

            <label>Nom (slug)</label>
            <input value="<?= htmlspecialchars($site['name']) ?>" disabled>
            <div class="small">Le renommage est géré ailleurs pour éviter les incohérences fichier/NGINX.</div>

            <label>Server names (espace/virgule)</label>
            <input name="server_names" required value="<?= htmlspecialchars($site['server_names']) ?>">

            <div class="form-row">
                <div>
                    <label>Racine (root)</label>
                    <input name="root" required value="<?= htmlspecialchars($site['root']) ?>">
                    <div class="small">Doit être sous <code>/var/www/</code> (ex: <code>/var/www/<?= htmlspecialchars($site['name']) ?>/public</code>).</div>
                </div>
                <div>
                    <label>Version PHP-FPM</label>
                    <select name="php_version">
                        <?php foreach (['8.2','8.3','8.4'] as $v): ?>
                            <option <?= $site['php_version']===$v?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="small">Socket attendue : <code>/run/php/phpX.Y-fpm.sock</code></div>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>client_max_body_size (MB)</label>
                    <input type="number" name="client_max_body_size" value="<?= (int)$site['client_max_body_size'] ?>" min="1" max="1024">
                    <div class="small">Ajuste aussi <code>upload_max_filesize</code> / <code>post_max_size</code> si besoin.</div>
                </div>
                <div style="display:flex;align-items:end">
                    <label style="width:auto"><input type="checkbox" name="with_logs" <?= !empty($site['with_logs'])?'checked':''; ?>> Logs dédiés</label>
                </div>
            </div>

            <div class="actions-bar">
                <a class="btn" href="/sites_list.php">Annuler</a>
                <button class="btn primary" name="action" value="save">Enregistrer</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>⚡ Actions Nginx / Site</h3>

        <form method="post" action="/test_reload.php" style="display:inline">
            <?= csrf_input() ?>
            <input type="hidden" name="from_edit" value="1">
            <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
            <button class="btn">Tester (nginx -t)</button>
            <button class="btn primary" name="reload" value="1">Recharger</button>
        </form>

        <div style="margin-top:12px">
            <?php if ($site['enabled']): ?>
                <a class="btn danger"
                   data-confirm="Désactiver ce site ?"
                   href="/site_toggle.php?a=disable&id=<?= (int)$site['id'] ?>&ret=edit&csrf=<?= csrf_token() ?>">Désactiver</a>
            <?php else: ?>
                <a class="btn ok"
                   data-confirm="Activer ce site ?"
                   href="/site_toggle.php?a=enable&id=<?= (int)$site['id'] ?>&ret=edit&csrf=<?= csrf_token() ?>">Activer</a>
            <?php endif; ?>
        </div>

        <div class="danger-zone" style="margin-top:16px;padding:12px;border:1px solid #7f1d1d;border-radius:10px">
            <strong>Zone dangereuse : suppression définitive</strong><br><br>
            <a class="btn danger"
               data-confirm="Supprimer ce site (garder fichiers) ?"
               href="/site_delete.php?id=<?= (int)$site['id'] ?>&csrf=<?= csrf_token() ?>&ret=edit">
                Supprimer
            </a>

            <a class="btn danger"
               data-confirm="⚠️ Supprimer ce site ET son dossier ?"
               href="/site_delete.php?id=<?= (int)$site['id'] ?>&csrf=<?= csrf_token() ?>&delete_root=1&ret=edit">
                Supprimer + Dossier
            </a>
        </div>
    </div>

<?php include __DIR__ . '/partials/footer.php'; ?>