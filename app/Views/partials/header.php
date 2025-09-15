<?php
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLogin = ($path === '/login');
$loggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
$active = function(string $p) use ($path): string {
    if ($p === '/') return $path === '/' ? 'active' : '';
    return str_starts_with($path, $p) ? 'active' : '';
};
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo function_exists('__') ? __('app.title') : 'Mini Web Panel'; ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/tables.css">
    <?php if (!$isLogin && $loggedIn): ?>
      <script src="/js/app.js" defer></script>
      <script src="/js/sysinfo.js" defer></script>
      <script src="/js/dashboardRenderer.js" defer></script>
      <script src="/js/power.js" defer></script>
      <script src="/js/modules/passgen.js" defer></script>
      <script src="/js/tables.js" defer></script>
    <?php else: ?>
      <script>window.DISABLE_GLOBAL_AUTH_REDIRECT = true;</script>
      <script src="/js/app.js" defer></script>
      <!-- No dashboard scripts on login -->
    <?php endif; ?>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="brand">
            <button class="nav-toggle" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mainNav">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <img src="/img/logo.svg" alt="Logo" class="logo">
            <div class="brand-text">
                <strong>Mini Web Panel</strong>
                <span class="small">Nginx • PHP-FPM</span>
            </div>
        </div>
        <nav id="mainNav" class="nav">
            <?php if ($loggedIn): ?>
                <a href="/dashboard" class="nav-link <?= $active('/dashboard') ?>" title="Dashboard" aria-label="Dashboard">Dashboard</a>
                <a href="/dashboard/error-log" class="nav-link <?= $active('/dashboard/error-log') ?>" title="Error Log" aria-label="Error Log">Error Log</a>
                <a href="/php/manage" class="nav-link <?= $active('/php') ?>" title="Système" aria-label="Système">Système</a>
                <a href="/sites" class="nav-link <?= $active('/sites') ?>" title="Sites" aria-label="Sites">Sites</a>
                <a href="/users" class="nav-link <?= $active('/users') ?>" title="Utilisateurs" aria-label="Utilisateurs">Utilisateurs</a>
                <a href="/account" class="nav-link <?= $active('/account') ?>" title="Compte" aria-label="Compte">Compte</a>
                <a class="btn" href="/sites/create">+ Nouveau</a>
                <a class="btn danger ml-auto" href="/logout?_csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Déconnexion</a>
            <?php endif; ?>
            <div class="srv-led" id="srv-led" title="État du serveur (via /api/sysinfo)" aria-live="polite" aria-atomic="true">
                <span class="srv-led-dot" aria-hidden="true"></span>
                <span class="srv-led-label">Serveur: Inconnu</span>
            </div>
        </nav>
    </div>
