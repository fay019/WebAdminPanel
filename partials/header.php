<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo function_exists('__') ? __('app.title') : 'Mini Web Panel'; ?></title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/tables.css">
    <script src="/public/js/app.js" defer></script>
    <script src="/public/js/modules/passgen.js" defer></script>
    <script src="/public/js/tables.js" defer></script>
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
            <img src="/public/img/logo.svg" alt="Logo" class="logo">
            <div class="brand-text">
                <strong>Mini Web Panel</strong>
                <span class="small">Nginx • PHP-FPM</span>
            </div>
        </div>
        <nav id="mainNav" class="nav">
            <?php if (is_logged_in()): ?>
                <a href="/dashboard.php" title="Dashboard" aria-label="Dashboard"><img src="/public/img/menu/dashboard.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/php_manage.php" title="Système" aria-label="Système"><img src="/public/img/menu/systemes.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/sites_list.php" title="Sites" aria-label="Sites"><img src="/public/img/menu/sites.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/users_list.php" title="Utilisateurs" aria-label="Utilisateurs"><img src="/public/img/menu/users.svg" class="nav-icon" alt="" role="presentation"></a>
                <a href="/account.php" title="Compte" aria-label="Compte"><img src="/public/img/menu/account.svg" class="nav-icon" alt="" role="presentation"></a>
                <a class="btn" href="/site_new.php">+ Nouveau</a>
                <a class="btn danger" href="/logout.php">Déconnexion</a>
            <?php endif; ?>
        </nav>
    </div>
