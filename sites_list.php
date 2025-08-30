<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

// --- Liste des sites en DB
$sites = db()->query('SELECT * FROM sites ORDER BY created_at DESC')->fetchAll();

// --- Détection des dossiers orphelins
function list_orphan_dirs(): array {
    $out = [];
    $rows = db()->query('SELECT root FROM sites')->fetchAll(PDO::FETCH_COLUMN);
    $rootsInDb = array_map(fn($r) => rtrim($r, '/'), $rows ?: []);

    foreach (glob('/var/www/*', GLOB_ONLYDIR) as $dir) {
        $base = basename($dir);

        // exclusions système
        if (in_array($base, ['adminpanel','html'], true)) continue;

        // si ce dossier (ou son /public) est encore référencé => on ignore
        if (in_array(rtrim($dir, '/'), $rootsInDb, true)) continue;
        if (in_array(rtrim($dir, '/').'/public', $rootsInDb, true)) continue;

        $out[] = ['name'=>$base, 'path'=>$dir];
    }

    usort($out, fn($a,$b) => strcmp($a['name'], $b['name']));
    return $out;
}
$orphans = list_orphan_dirs();

include __DIR__ . '/partials/header.php';
?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <h2>Sites</h2>
            <a class="btn primary" href="/site_new.php">+ Nouveau site</a>
        </div>

        <?php show_flash(); ?>

        <table class="table">
            <tr><th>Nom</th><th>Server Names</th><th>Root</th><th>PHP</th><th>État</th><th>Actions</th></tr>
            <?php foreach ($sites as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['server_names']) ?></td>
                    <td class="small"><?= htmlspecialchars($s['root']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($s['php_version']) ?></span></td>
                    <td><?= $s['enabled'] ? '<span class="badge ok">activé</span>' : '<span class="badge err">désactivé</span>' ?></td>
                    <td class="actions">
                        <a class="btn" href="/site_edit.php?id=<?= (int)$s['id'] ?>">Éditer</a>
                        <?php if ($s['enabled']): ?>
                            <a class="btn" data-confirm="Désactiver ce site ?" href="/site_toggle.php?a=disable&id=<?= (int)$s['id'] ?>&csrf=<?= csrf_token() ?>">Désactiver</a>
                        <?php else: ?>
                            <a class="btn" data-confirm="Activer ce site ?" href="/site_toggle.php?a=enable&id=<?= (int)$s['id'] ?>&csrf=<?= csrf_token() ?>">Activer</a>
                        <?php endif; ?>
                        <a class="btn danger"
                           data-confirm="Supprimer ce site (mais garder les fichiers) ?"
                           href="/site_delete.php?id=<?= (int)$s['id'] ?>&csrf=<?= csrf_token() ?>">
                            Supprimer
                        </a>
                        <a class="btn danger"
                           data-confirm="⚠️ Supprimer ce site ET son dossier ? Action définitive."
                           href="/site_delete.php?id=<?= (int)$s['id'] ?>&csrf=<?= csrf_token() ?>&delete_root=1">
                            Supprimer + Dossier
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sites): ?>
                <tr><td colspan="6" class="small">Aucun site pour l’instant.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="card">
        <h3>Tester & recharger Nginx</h3>
        <form method="post" action="/test_reload.php">
            <?= csrf_input() ?>
            <button class="btn">Tester (nginx -t)</button>
            <button class="btn primary" name="reload" value="1">Recharger</button>
        </form>
    </div>

<?php if (!empty($_SESSION['hosts_tip'])):
    $tip = $_SESSION['hosts_tip']; unset($_SESSION['hosts_tip']); ?>
    <div class="modal" id="hostsModal">
        <div class="modal-card">
            <div class="modal-header">
                <span class="ok-dot"></span>
                <h3 class="modal-title">Site <?= htmlspecialchars($tip['name']) ?> créé</h3>
            </div>
            <div class="modal-body">
                <p>Ajoute ceci à <code>/etc/hosts</code> :</p>
                <pre><?php foreach ($tip['lines'] as $ln) echo htmlspecialchars($ln)."\n"; ?></pre>
                <p><strong>Commande rapide :</strong></p>
                <pre>sudo nano /etc/hosts</pre>
            </div>
            <div class="modal-footer">
                <button class="btn-ghost" data-close-modal>Fermer</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($orphans): ?>
    <div class="card">
        <h3>🧹 Dossiers orphelins</h3>
        <table class="table">
            <tr><th>Nom</th><th>Chemin</th><th>Action</th></tr>
            <?php foreach ($orphans as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['name']) ?></td>
                    <td class="small"><?= htmlspecialchars($o['path']) ?></td>
                    <td>
                        <a class="btn danger"
                           data-confirm="Supprimer définitivement le dossier « <?= htmlspecialchars($o['name']) ?> » ?"
                           href="/orphan_delete.php?dir=<?= urlencode($o['path']) ?>&csrf=<?= csrf_token() ?>">
                            Supprimer le dossier
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div class="small">⚠️ Supprime uniquement le répertoire. Pas d’entrée DB ni de conf Nginx.</div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>