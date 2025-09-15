<?php
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLogin = ($path === '/login');
$loggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
$active = function(string $p) use ($path): string {
    if ($p === '/dashboard') {
        return $path === '/dashboard' ? 'active' : '';
    }
    return ($path === $p || str_starts_with($path, $p . '/')) ? 'active' : '';
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
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
                <a href="/dashboard" class="nav-link <?= $active('/dashboard') ?>" title="Dashboard" aria-label="Dashboard">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12l9-9 9 9M5 10v10h14V10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Dashboard
                </a>
                <a href="/dashboard/error-log" class="nav-link <?= $active('/dashboard/error-log') ?>" title="Error Log" aria-label="Error Log">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-6M12 3v10M8 3h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Error Log
                </a>
                <a href="/php/manage" class="nav-link <?= $active('/php/manage') ?>" title="Système" aria-label="Système">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18M6 3v4m12-4v4M5 21h14a2 2 0 0 0 2-2V7H3v12a2 2 0 0 0 2 2z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Système
                </a>
                <a href="/sites" class="nav-link <?= $active('/sites') ?>" title="Sites" aria-label="Sites">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18M4 6h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1zm6 10h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Sites
                </a>
                <a href="/users" class="nav-link <?= $active('/users') ?>" title="Utilisateurs" aria-label="Utilisateurs">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18M4 6h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1zm6 10h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Utilisateurs
                </a>
                <a href="/account" class="nav-link <?= $active('/account') ?>" title="Compte" aria-label="Compte">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 0a9 9 0 0 0-9 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Compte
                </a>
                <a class="btn" href="/sites/create" title="Nouveau site" aria-label="Nouveau">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Nouveau
                </a>
                <a class="btn danger ml-auto" href="/logout?_csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Déconnexion" aria-label="Déconnexion">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Déconnexion
                </a>
            <?php endif; ?>
            <div class="srv-led" id="srv-led" title="État du serveur (via /api/sysinfo)" aria-live="polite" aria-atomic="true">
                <span class="srv-led-dot" aria-hidden="true"></span>
                <span class="srv-led-label">Serveur: Inconnu</span>
            </div>
        </nav>
    </div>
