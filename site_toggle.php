<?php
declare(strict_types=1);
// Legacy endpoint moved to MVC. Keep bookmarks working with a 302 redirect.
header('Location: /sites', true, 302);
exit;
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

// CSRF dans l'URL (GET) accepté par csrf_check()
csrf_check();

$a  = $_GET['a']  ?? '';
$id = (int)($_GET['id'] ?? 0);
$ret = ($_GET['ret'] ?? '') === 'edit' ? '/site_edit.php?id='.$id : '/sites_list.php';

if ($id <= 0 || !in_array($a, ['enable','disable'], true)) {
    flash('err','Requête invalide.');
    header('Location: '.$ret); exit;
}

$st = db()->prepare('SELECT * FROM sites WHERE id = :id');
$st->execute([':id'=>$id]);
$site = $st->fetch();
if (!$site) {
    flash('err','Site introuvable.');
    header('Location: '.$ret); exit;
}

$name   = $site['name'];
$enable = ($a === 'enable');

// Exécute le script sudo correspondant
$cmd = sprintf(
    '/usr/bin/sudo -n /var/www/adminpanel/bin/site_%s.sh %s 2>&1',
    $enable ? 'enable' : 'disable',
    escapeshellarg($name)
);
$out = shell_exec($cmd) ?? '';
$_SESSION['last_cmd_output'] = $out !== '' ? $out : '(aucune sortie)';

// MAJ DB
$upd = db()->prepare('UPDATE sites SET enabled=:e, updated_at=:u WHERE id=:id');
$upd->execute([':e'=>$enable?1:0, ':u'=>date('c'), ':id'=>$id]);

audit('site.toggle', ['name'=>$name, 'enabled'=>$enable]);

// Flash d’info + détails repliables
$statusTxt = $enable ? 'activé' : 'désactivé';
$nameSafe  = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$outSafe   = htmlspecialchars($_SESSION['last_cmd_output'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = <<<HTML
<strong>Le site « {$nameSafe} » a été {$statusTxt}.</strong>
<details class="log-details"><summary>Voir la sortie du script</summary>
<pre class="log-pre">{$outSafe}</pre>
</details>
HTML;
flash('ok', $html, true);

// Reste sur la page d’édition si demandé, sinon retourne à la liste
header('Location: '.$ret); exit;