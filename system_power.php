<?php
declare(strict_types=1);

require_once __DIR__.'/lib/auth.php'; require_login();
require_once __DIR__.'/lib/csrf.php';
require_once __DIR__.'/partials/flash.php';

function run(string $cmd): string {
    $out = shell_exec($cmd . ' 2>&1');
    return $out === null ? '' : $out;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Page inutilisée désormais: renvoyer 404 pour empêcher l'accès direct
    http_response_code(404);
    $file = __DIR__.'/public/404.html';
    if (is_readable($file)) {
        readfile($file);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "404 Not Found";
    }
    exit;
}

// POST: exécute la commande d'alimentation
csrf_check();

$action = $_POST['action'] ?? '';
$isAjax = ($_POST['ajax'] ?? '') === '1';
$back   = $_POST['back'] ?? ($_SERVER['HTTP_REFERER'] ?? '/dashboard');

if (!in_array($action, ['shutdown','reboot'], true)) {
    if ($isAjax) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "ERR: action invalide (attendu: shutdown|reboot)\n";
        exit;
    }
    flash('err', "Action invalide. Utilisation : shutdown ou reboot.");
    header("Location: $back"); exit;
}

// libère la session pour ne pas bloquer d'autres requêtes
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

$cmd = 'sudo -n /var/www/adminpanel/bin/power.sh ' . escapeshellarg($action);
$out = run($cmd);
$ok  = is_string($out) && str_starts_with($out, 'OK:');

if ($isAjax) {
    // Réponse brute pour l’overlay JS (pas de HTML)
    header('Content-Type: text/plain; charset=UTF-8');
    if ($ok) {
        // message court et explicite pour le log de l’overlay
        echo $out . "\n";
    } else {
        echo ($out !== '' ? $out : "ERR: aucune sortie") . "\n";
    }
    exit;
}

// Mode non-AJAX : flash + redirect
if ($ok) {
    if ($action === 'shutdown') {
        flash('ok', "OK: arrêt demandé. Le système va s’éteindre.");
    } else {
        flash('ok', "OK: redémarrage demandé. Le système va redémarrer.");
    }
} else {
    flash('err', nl2br(htmlspecialchars($out ?: "ERREUR: aucune sortie")), true);
}

header("Location: $back"); exit;