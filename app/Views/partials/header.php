<?php
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo function_exists('__') ? __('app.title') : 'Mini Web Panel'; ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/tables.css">
    <script src="/js/app.js" defer></script>
    <script src="/js/sysinfo.js" defer></script>
    <script src="/js/startReboot.js" defer></script>
    <script src="/js/modules/passgen.js" defer></script>
    <script src="/js/tables.js" defer></script>
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
            <?php if (is_logged_in()): ?>
                <a href="/dashboard" title="Dashboard" aria-label="Dashboard"><img src="/img/menu/dashboard.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/php/manage" title="Système" aria-label="Système"><img src="/img/menu/systemes.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/sites" title="Sites" aria-label="Sites"><img src="/img/menu/sites.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/users" title="Utilisateurs" aria-label="Utilisateurs"><img src="/img/menu/users.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/account.php" title="Compte" aria-label="Compte"><img src="/img/menu/account.svg" class="nav-icon" alt="" role="presentation"></a>
                <a class="btn" href="/sites/create">+ Nouveau</a>
                <a class="btn danger" href="/logout.php">Déconnexion</a>
            <?php endif; ?>
            <div class="srv-led" id="srv-led" title="État du serveur (via /api/sysinfo)" aria-live="polite" aria-atomic="true">
                <span class="srv-led-dot" aria-hidden="true"></span>
                <span class="srv-led-label">Serveur: Inconnu</span>
            </div>
        </nav>
    </div>
