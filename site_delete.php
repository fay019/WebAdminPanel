<?php
declare(strict_types=1);
// Legacy endpoint moved to MVC. Keep bookmarks working with a 302 redirect.
header('Location: /sites', true, 302);
exit;
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit; }
csrf_check(); // le token est dans l’URL ou via bouton POST → adapte si besoin

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('err','ID invalide'); header('Location: /sites_list.php'); exit; }

$st = db()->prepare('SELECT * FROM sites WHERE id = :id');
$st->execute([':id' => $id]);
$site = $st->fetch();
if (!$site) { flash('err','Site introuvable'); header('Location: /sites_list.php'); exit; }

$delete_root = (isset($_GET['delete_root']) && $_GET['delete_root'] === '1') ? 'yes' : 'no';

// exécute le script (NOPASSWD requis)
$cmd = sprintf(
    '/usr/bin/sudo -n /var/www/adminpanel/bin/site_delete.sh %s %s 2>&1',
    escapeshellarg($site['name']),
    escapeshellarg($delete_root)
);
$out = shell_exec($cmd);
$_SESSION['last_cmd_output'] = $out ?: '(aucune sortie)';

// supprime la ligne en DB quoi qu’il arrive (le script a déjà retiré conf/symlink)
$del = db()->prepare('DELETE FROM sites WHERE id = :id');
$del->execute([':id' => $id]);

audit('site.delete', ['name'=>$site['name'], 'delete_root'=>$delete_root]);

$nameSafe = htmlspecialchars($site['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$outRaw   = $_SESSION['last_cmd_output'] ?? $out ?? '';
$outSafe  = htmlspecialchars($outRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html = <<<HTML
<strong>Site « {$nameSafe} » supprimé.</strong> Dossier&nbsp;: {$delete_root}.
<details class="log-details"><summary>Voir la sortie du script</summary>
<pre class="log-pre">{$outSafe}</pre>
</details>
HTML;

flash('ok', $html, true);  // <- HTML autorisé

header('Location: /sites_list.php'); exit;