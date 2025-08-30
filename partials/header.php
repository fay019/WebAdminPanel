<?php
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/csrf.php';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mini Web Panel</title>
  <link rel="stylesheet" href="/public/css/style.css">
  <script src="/public/js/app.js" defer></script>
</head>
<body>
<div class="container">
  <div class="header">
      <div style="display:flex;align-items:center;gap:8px">
          <img src="/public/img/logo.svg" alt="Logo" class="logo">
          <strong>Mini Web Panel</strong>
          <span class="small">Nginx • PHP-FPM</span>
      </div>
    <nav>
      <?php if(is_logged_in()): ?>
        <a href="/dashboard.php">Dashboard</a>
        <a href="/php_manage.php">Système</a>
        <a href="/sites_list.php">Sites</a>
        <a href="/account.php">Compte</a>
        <a class="btn" href="/site_new.php">+ Nouveau</a>
        <a class="btn danger" href="/logout.php">Déconnexion</a>
      <?php endif; ?>
    </nav>
  </div>
