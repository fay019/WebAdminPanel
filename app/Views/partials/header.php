<?php
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isLogin = ($path === '/login');
$loggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
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
        <?php include __DIR__ . '/nav.php'; ?>
    </div>
