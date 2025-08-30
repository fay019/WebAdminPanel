<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mini Web Panel</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <script src="/public/js/app.js" defer></script>
    <script src="/public/js/modules/passgen.js" defer></script>
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
                <a href="/dashboard.php">Dashboard</a>
                <a href="/php_manage.php">Système</a>
                <a href="/sites_list.php">Sites</a>
                <a href="/users_list.php">Utilisateurs</a>
                <a href="/account.php">Compte</a>
                <a class="btn" href="/site_new.php">+ Nouveau</a>
                <a class="btn danger" href="/logout.php">Déconnexion</a>
            <?php endif; ?>
        </nav>
    </div>
