<?php
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLogin = ($path === '/login');
$loggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
$active = function (string $p) use ($path): string {
    // Dashboard actif seulement sur /dashboard (évite le double "active")
    if ($p === '/dashboard') {
        return $path === '/dashboard' ? 'active' : '';
    }
    // Les autres: exact OU sous-routes
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
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 12l9-9 9 9M5 10v10h14V10" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="/dashboard/error-log" class="nav-link <?= $active('/dashboard/error-log') ?>" title="Error Log" aria-label="Error Log">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- icône "alerte" simple -->
                        <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Error Log</span>
                </a>

                <a href="/php/manage" class="nav-link <?= $active('/php') ?>" title="Système" aria-label="Système">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- icône "server" -->
                        <path d="M4 6h16v4H4zM4 14h16v4H4zM6 8h.01M6 16h.01" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Système</span>
                </a>

                <a href="/sites" class="nav-link <?= $active('/sites') ?>" title="Sites" aria-label="Sites">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- icône "globe" -->
                        <path d="M12 21a9 9 0 100-18 9 9 0 000 18zm0-18c3 3 3 15 0 18m0-18c-3 3-3 15 0 18M3 12h18" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Sites</span>
                </a>

                <a href="/users" class="nav-link <?= $active('/users') ?>" title="Utilisateurs" aria-label="Utilisateurs">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- icône "user" -->
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M16 7a4 4 0 11-8 0 4 4 0 018 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Utilisateurs</span>
                </a>

                <a href="/account" class="nav-link <?= $active('/account') ?>" title="Compte" aria-label="Compte">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <!-- icône "profil" -->
                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM3 21a9 9 0 1118 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Compte</span>
                </a>

                <a class="btn" href="/sites/create" title="Nouveau site" aria-label="Nouveau">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Nouveau</span>
                </a>

                <a class="btn danger ml-auto" href="/logout?_csrf=<?= htmlspecialchars($_SESSION['csrf'] ?? csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Déconnexion" aria-label="Déconnexion">
                    <svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/>
                    </svg>
                    <span>Déconnexion</span>
                </a>
            <?php endif; ?>
            <div class="srv-led" id="srv-led" title="État du serveur (via /api/sysinfo)" aria-live="polite" aria-atomic="true">
                <span class="srv-led-dot" aria-hidden="true"></span>
                <span class="srv-led-label">Serveur: Inconnu</span>
            </div>
        </nav>
    </div>
