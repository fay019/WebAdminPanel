<?php
declare(strict_types=1);
require_once __DIR__.'/lib/auth.php'; require_login();
require_once __DIR__.'/lib/csrf.php';
require_once __DIR__.'/partials/flash.php';
csrf_check();

$dirParam = $_GET['dir'] ?? '';
if ($dirParam === '') {
    flash('err','Chemin manquant.');
    header('Location: /sites_list.php'); exit;
}

$real = realpath($dirParam);
if ($real === false || $real === null) {
    flash('err','Dossier introuvable.');
    header('Location: /sites_list.php'); exit;
}

// ⚠️ Sécurité : doit être sous /var/www
if (strpos($real, '/var/www/') !== 0) {
    flash('err','Chemin non autorisé.');
    header('Location: /sites_list.php'); exit;
}

$base = basename($real);
if (in_array($base, ['adminpanel','html'], true)) {
    flash('err','Ce dossier est protégé.');
    header('Location: /sites_list.php'); exit;
}

// --- Appel du script sudo
$cmd = sprintf(
    "sudo -n /var/www/adminpanel/bin/orphan_delete.sh %s 2>&1",
    escapeshellarg($real)
);
$output = shell_exec($cmd);

if (is_dir($real)) {
    flash('err', "Échec de suppression du dossier.<br><pre>".htmlspecialchars($output)."</pre>", true);
} else {
    flash('ok', "Dossier supprimé : <code>".htmlspecialchars($real)."</code>", true);
}

header('Location: /sites_list.php'); exit;