<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php'; require_login();
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/partials/flash.php';

// Sécurité
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$doReload = isset($_POST['reload']) && $_POST['reload'] === '1';

// 1) nginx -t
$testOut = shell_exec('/usr/bin/sudo -n /usr/sbin/nginx -t 2>&1') ?? '';
$okTest = (strpos($testOut, 'test is successful') !== false);

// 2) reload optionnel
$reloadOut = '';
$okReload = false;
if ($doReload && $okTest) {
    $reloadOut = shell_exec('/usr/bin/sudo -n /bin/systemctl reload nginx 2>&1') ?? '';
    // systemctl reload ne renvoie rien si OK ; on considère OK si pas d'erreur
    $okReload = true;
}

// flash
$pre = function(string $s): string {
    $safe = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<details class=\"log-details\"><summary>Détails</summary><pre class=\"log-pre\">$safe</pre></details>";
};

if ($okTest) {
    $msg = "Test de configuration exécuté." . $pre($testOut);
    if ($doReload) {
        $msg = "Test OK & Nginx rechargé." . $pre($testOut . ($reloadOut ? "\n--- reload ---\n".$reloadOut : ''));
    }
    flash('ok', $msg, true);
} else {
    flash('err', "Échec du test Nginx." . $pre($testOut), true);
}

// Redirection : si on vient d'edit, on y retourne, sinon vers la liste
if (!empty($_POST['from_edit']) && $_POST['from_edit'] === '1' && !empty($_POST['id'])) {
    header('Location: /site_edit.php?id='.(int)$_POST['id']); exit;
}
header('Location: /sites_list.php'); exit;